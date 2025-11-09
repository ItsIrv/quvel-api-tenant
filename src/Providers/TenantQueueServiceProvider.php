<?php

namespace Quvel\Tenant\Providers;

use Illuminate\Queue\QueueManager;
use Illuminate\Queue\QueueServiceProvider;
use Quvel\Tenant\Queue\Connectors\TenantDatabaseConnector;

class TenantQueueServiceProvider extends QueueServiceProvider
{
    /**
     * Register the database queue connector.
     *
     * @param QueueManager $manager
     */
    protected function registerDatabaseConnector($manager): void
    {
        $manager->addConnector(
            'database',
            fn (): TenantDatabaseConnector => new TenantDatabaseConnector(
                $this->app['db']
            )
        );
    }
}
