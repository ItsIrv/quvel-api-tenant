<?php

declare(strict_types=1);

namespace Quvel\Tenant\Commands;

use Illuminate\Console\Command;

class TenantInstallCommand extends Command
{
    protected $signature = 'tenant:install';
    protected $description = 'Install the Tenant package';

    public function handle(): int
    {
        $this->info('Installing Tenant package...');

        $this->call('vendor:publish', [
            '--tag' => 'tenant-config',
            '--force' => true,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'tenant-migrations',
            '--force' => true,
        ]);

        $this->info('Tenant package installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Configure your tables in config/tenant.php');
        $this->info('2. Run: php artisan migrate');

        return 0;
    }
}
