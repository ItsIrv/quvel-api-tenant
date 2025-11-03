<?php

declare(strict_types=1);

namespace Quvel\Tenant\Tests;

use Quvel\Tenant\TenantServiceProvider;

class TenantServiceProviderTest extends TestCase
{
    public function test_service_provider_is_loaded(): void
    {
        $loadedProviders = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(TenantServiceProvider::class, $loadedProviders);
    }

    public function test_example(): void
    {
        $this->assertTrue(true);
    }
}
