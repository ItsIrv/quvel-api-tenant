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
     * @param  QueueManager  $manager
     * @return void
     */
    protected function registerDatabaseConnector($manager): void
    {
        $manager->addConnector('database', function () {
            return new TenantDatabaseConnector($this->app['db']);
        });
    }
}