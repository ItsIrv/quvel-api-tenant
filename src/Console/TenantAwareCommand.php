<?php

declare(strict_types=1);

namespace Quvel\Tenant\Console;

use Illuminate\Console\Command;
use Quvel\Tenant\Console\Concerns\HasTenantCommands;

abstract class TenantAwareCommand extends Command
{
    use HasTenantCommands;

    public function __construct()
    {
        parent::__construct();
        $this->addTenantOptions();
    }

    public function handle(): int
    {
        return $this->withTenant(function ($tenant) {
            if ($tenant) {
                return $this->handleForTenant($tenant);
            }

            return $this->runWithoutTenant();
        });
    }

    /**
     * Handle the command for a specific tenant.
     * Override this method in your command.
     */
    protected function handleForTenant($tenant): int
    {
        return $this->runWithoutTenant();
    }

    /**
     * Handle the command without tenant context.
     * Override this method in your command.
     */
    protected function runWithoutTenant(): int
    {
        $this->error('This command requires tenant context. Use --tenant or --all-tenants.');
        return 1;
    }
}