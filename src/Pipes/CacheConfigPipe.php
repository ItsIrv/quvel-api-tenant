<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Exception;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;

/**
 * Handles cache configuration for tenants.
 */
class CacheConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'cache.default',
        ]);

        if ($this->tenant->hasConfig('cache.prefix')) {
            $this->setIfExists('cache.prefix', 'cache.prefix');
        } else {
            $this->config->set('cache.prefix', "tenant_{$this->tenant->public_id}");
        }

        $this->rebindCacheManager();
    }

    /**
     * Rebind cache manager to pick up new configuration.
     */
    protected function rebindCacheManager(): void
    {
        try {
            app()->forgetInstance(CacheManager::class);
            app()->forgetInstance(CacheRepository::class);

            app()->extend(CacheManager::class, function ($cacheManager, $app): CacheManager {
                return new CacheManager($app);
            });
        } catch (Exception $e) {
            throw new RuntimeException('Failed to override cache instance', 0, $e);
        }
    }
}