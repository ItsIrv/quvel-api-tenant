<?php

declare(strict_types=1);

namespace Quvel\Tenant\Console\Concerns;

use Exception;
use Illuminate\Console\Command;
use Quvel\Tenant\Concerns\TenantAware as BaseTenantAware;
use Quvel\Tenant\Contracts\PipelineRegistry;
use Quvel\Tenant\Facades\TenantContext;
use Symfony\Component\Console\Input\InputOption;

/**
 * Trait TenantAware
 *
 * Add tenant functionality to Artisan commands.
 * Requires the using class to be a Command instance.
 *
 * @mixin Command
 */
trait HasTenantCommands
{
    use BaseTenantAware;
    protected function addTenantOptions(): void
    {
        $this->getDefinition()->addOptions([
            new InputOption(
                'tenant',
                't',
                InputOption::VALUE_OPTIONAL,
                'Run command for specific tenant (ID, public_id, identifier, or name)'
            ),
            new InputOption(
                'all-tenants',
                null,
                InputOption::VALUE_NONE,
                'Run command for all active tenants'
            ),
            new InputOption(
                'apply-tenant-config',
                null,
                InputOption::VALUE_NONE,
                'Apply tenant configuration pipes (database, mail, etc.)'
            ),
            new InputOption(
                'hard',
                null,
                InputOption::VALUE_NONE,
                'Apply tenant configuration pipes (alias for --apply-tenant-config)'
            ),
        ]);
    }

    protected function withTenant($callback): int
    {
        if ($this->option('all-tenants')) {
            return $this->runForAllTenants($callback);
        }

        if ($tenantIdentifier = $this->option('tenant')) {
            return $this->runForTenant($tenantIdentifier, $callback);
        }

        return $callback(null);
    }

    protected function runForAllTenants($callback): int
    {
        $tenants = tenant_class()::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->error('No active tenants found.');

            return 1;
        }

        $this->info('Running command for ' . $tenants->count() . ' tenants...');

        $failures = 0;
        foreach ($tenants as $tenant) {
            $this->line('Running for tenant: ' . $tenant->name . ' (' . $tenant->identifier . ')');

            if ($this->executeWithTenant($tenant, $callback) !== 0) {
                $failures++;
            }
        }

        if ($failures > 0) {
            $this->error('Command failed for ' . $failures . ' tenants.');

            return 1;
        }

        $this->info('Command completed successfully for all tenants.');

        return 0;
    }

    protected function runForTenant(string $identifier, $callback): int
    {
        $tenant = $this->findTenant($identifier);
        if (!$tenant) {
            return 1;
        }

        $this->info('Running for tenant: ' . $tenant->name . ' (' . $tenant->identifier . ')');

        return $this->executeWithTenant($tenant, $callback);
    }

    protected function executeWithTenant($tenant, $callback): int
    {
        $originalTenant = TenantContext::current();

        try {
            TenantContext::setCurrent($tenant);

            if ($this->shouldApplyTenantConfig()) {
                app(PipelineRegistry::class)->applyPipes($tenant);

                if ($this->output->isVerbose()) {
                    $this->line('  → Applied tenant configuration pipes');
                }
            }

            return $callback($tenant) ?? 0;
        } catch (Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return 1;
        } finally {
            TenantContext::setCurrent($originalTenant);
        }
    }

    protected function shouldApplyTenantConfig(): bool
    {
        return $this->option('apply-tenant-config') || $this->option('hard');
    }

    protected function findTenant(string $identifier): mixed
    {
        $tenant = tenant_class()::where('identifier', $identifier)
            ->orWhere('id', $identifier)
            ->orWhere('public_id', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        if (!$tenant) {
            $this->error('Tenant not found: ' . $identifier);

            return null;
        }

        return $tenant;
    }

    protected function requiresTenant(): bool
    {
        return $this->option('tenant') || $this->option('all-tenants');
    }
}
