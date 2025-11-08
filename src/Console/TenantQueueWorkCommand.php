<?php

declare(strict_types=1);

namespace Quvel\Tenant\Console;

use Illuminate\Queue\Console\WorkCommand;
use Quvel\Tenant\Console\Concerns\HasTenantCommands;
use Quvel\Tenant\Contracts\PipelineRegistry;
use Quvel\Tenant\Facades\TenantContext;
use Quvel\Tenant\Queue\TenantDatabaseQueue;

/**
 * Tenant-aware queue work command.
 *
 * Extends Laravel's queue:work command to support tenant context.
 * When a tenant is specified, the worker will ONLY process jobs for that tenant.
 *
 * Usage:
 *   php artisan queue:work --tenant=customer-123
 *   php artisan queue:work --tenant=customer-123 --hard
 *   php artisan queue:work (processes all jobs)
 */
class TenantQueueWorkCommand extends WorkCommand
{
    use HasTenantCommands;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start processing jobs on the queue as a daemon (with tenant support)';

    public function __construct($worker, $cache)
    {
        parent::__construct($worker, $cache);

        $this->addTenantOptions();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if ($tenantIdentifier = $this->option('tenant')) {
            $tenant = $this->findTenant($tenantIdentifier);

            if (!$tenant) {
                return 1;
            }

            TenantContext::setCurrent($tenant);

            $this->info('Queue worker started with tenant context:');
            $this->line("  → Tenant: <fg=cyan>$tenant->name</>");
            $this->line("  → Identifier: <fg=cyan>$tenant->identifier</>");
            $this->line("  → ID: <fg=cyan>$tenant->public_id</>");
            $this->line('  → <fg=yellow>Only jobs for this tenant will be processed</>');

            if ($this->shouldApplyTenantConfig()) {
                $this->line('  → <fg=yellow>Applying tenant configuration pipeline...</>');

                app(PipelineRegistry::class)->applyPipes($tenant);

                $this->info('  → <fg=green>Configuration pipeline applied (database, mail, etc.)</>');
            } else {
                $this->line('  → <fg=yellow>Soft mode: tenant set in context only</>');
                $this->line('  → <fg=gray>Use --hard to apply full configuration pipeline</>');
            }

            $this->setQueueTenantFilter($tenant->id);

            $this->newLine();
        }

        return parent::handle();
    }

    /**
     * Set the tenant filter on all database queue connections.
     *
     * @param int $tenantId
     * @return void
     */
    protected function setQueueTenantFilter(int $tenantId): void
    {
        $queueManager = app('queue');

        foreach (config('queue.connections', []) as $name => $config) {
            if (($config['driver'] ?? null) === 'database') {
                $connection = $queueManager->connection($name);

                if ($connection instanceof TenantDatabaseQueue) {
                    $connection->setFilterTenantId($tenantId);
                }
            }
        }

        $defaultConnection = $queueManager->connection();

        if ($defaultConnection instanceof TenantDatabaseQueue) {
            $defaultConnection->setFilterTenantId($tenantId);
        }
    }
}
