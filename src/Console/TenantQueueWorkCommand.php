<?php

declare(strict_types=1);

namespace Quvel\Tenant\Console;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Worker;
use Quvel\Tenant\Console\Concerns\HasTenantCommands;
use Quvel\Tenant\Contracts\PipelineRegistry;
use Quvel\Tenant\Facades\TenantContext;
use Quvel\Tenant\Queue\TenantDatabaseQueue;
use Quvel\Tenant\Queue\TenantHorizonRedisQueue;
use Quvel\Tenant\Queue\TenantRedisQueue;

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

    public function __construct(Worker $worker, Repository $cache)
    {
        parent::__construct($worker, $cache);

        $this->addTenantOptions();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int|null
    {
        $tenantIdentifier = $this->option('tenant');
        if ($tenantIdentifier) {
            $identifier = is_array($tenantIdentifier) ? $tenantIdentifier[0] : $tenantIdentifier;
            $tenant = $this->findTenant((string)$identifier);

            if (!$tenant) {
                return 1;
            }

            TenantContext::setCurrent($tenant);

            $this->info('Queue worker started with tenant context:');
            $this->line(sprintf('  → Tenant: <fg=cyan>%s</>', $tenant->name));
            $this->line(sprintf('  → Identifier: <fg=cyan>%s</>', $tenant->identifier));
            $this->line(sprintf('  → ID: <fg=cyan>%s</>', $tenant->public_id));
            $this->line('  → <fg=yellow>Only jobs for this tenant will be processed</>');

            if ($this->shouldApplyTenantConfig()) {
                $this->line('  → <fg=yellow>Applying tenant configuration pipeline...</>');
                app(PipelineRegistry::class)->applyPipes($tenant);
                $this->info('  → <fg=green>Configuration pipeline applied (database, mail, etc.)</>');

                $this->setQueueTenantFilter($tenant->id);
                $this->newLine();

                return parent::handle();
            }

            $this->line('  → <fg=yellow>Soft mode: tenant set in context only</>');
            $this->line('  → <fg=gray>Use --hard to apply full configuration pipeline</>');

            $this->setQueueTenantFilter($tenant->id);
            $this->newLine();

            return parent::handle();
        }

        return parent::handle();
    }

    /**
     * Set the tenant filter on all queue connections.
     *
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     */
    protected function setQueueTenantFilter(int $tenantId): void
    {
        $queueManager = app('queue');

        foreach (config('queue.connections', []) as $name => $config) {
            $driver = $config['driver'] ?? null;

            if ($driver === 'database' || $driver === 'redis') {
                $connection = $queueManager->connection($name);

                if (
                    $connection instanceof TenantDatabaseQueue
                    || $connection instanceof TenantRedisQueue
                    || $connection instanceof TenantHorizonRedisQueue
                ) {
                    $connection->setFilterTenantId($tenantId);
                }
            }
        }

        $defaultConnection = $queueManager->connection();

        if (
            $defaultConnection instanceof TenantDatabaseQueue
            || $defaultConnection instanceof TenantRedisQueue
            || $defaultConnection instanceof TenantHorizonRedisQueue
        ) {
            $defaultConnection->setFilterTenantId($tenantId);
        }
    }
}
