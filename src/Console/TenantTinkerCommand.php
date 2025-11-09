<?php

declare(strict_types=1);

namespace Quvel\Tenant\Console;

use Laravel\Tinker\Console\TinkerCommand;
use Quvel\Tenant\Console\Concerns\HasTenantCommands;
use Quvel\Tenant\Contracts\PipelineRegistry;
use Quvel\Tenant\Facades\TenantContext;

/**
 * Tenant-aware Tinker command.
 *
 * Extends Laravel's tinker command to support tenant context.
 *
 * Usage:
 *   php artisan tinker --tenant=customer-123
 *   php artisan tinker --tenant=customer-123 --hard
 */
class TenantTinkerCommand extends TinkerCommand
{
    use HasTenantCommands;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interact with your application (with tenant support)';

    public function __construct()
    {
        parent::__construct();

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

            $this->info('Tinker session started with tenant context:');
            $this->line(sprintf('  → Tenant: <fg=cyan>%s</>', $tenant->name));
            $this->line(sprintf('  → Identifier: <fg=cyan>%s</>', $tenant->identifier));
            $this->line(sprintf('  → ID: <fg=cyan>%s</>', $tenant->public_id));

            if ($this->shouldApplyTenantConfig()) {
                $this->line('  → <fg=yellow>Applying tenant configuration pipeline...</>');

                app(PipelineRegistry::class)->applyPipes($tenant);

                $this->info('  → <fg=green>Configuration pipeline applied (database, mail, etc.)</>');
            } else {
                $this->line('  → <fg=yellow>Soft mode: tenant set in context only</>');
                $this->line('  → <fg=gray>Use --hard to apply full configuration pipeline</>');
            }

            $this->newLine();
        }

        return parent::handle();
    }
}
