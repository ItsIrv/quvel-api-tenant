<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Exception;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use RuntimeException;

/**
 * Handles Redis configuration for tenants.
 */
class RedisConfigPipe extends BasePipe
{
    public function apply(): void
    {
        if (!$this->isRedisAvailable()) {
            return;
        }

        $this->setMany([
            'database.redis.client',
            'database.redis.default.host',
            'database.redis.default.password',
            'database.redis.default.port',
        ]);

        if ($this->tenant->hasConfig('database.redis.default.prefix')) {
            $prefix = $this->tenant->getConfig('database.redis.default.prefix');
        } else {
            $prefix = "tenant_{$this->tenant->public_id}:";
        }

        $this->config->set('database.redis.default.prefix', $prefix);
        $this->config->set('database.redis.cache.prefix', $prefix);

        $this->refreshRedisConnections();
    }

    /**
     * Check if Redis is available in the application.
     */
    protected function isRedisAvailable(): bool
    {
        return app()->bound(RedisFactory::class) &&
            extension_loaded('redis') &&
            class_exists(\Illuminate\Support\Facades\Redis::class);
    }

    /**
     * Refresh Redis connections to pick up new configuration.
     */
    protected function refreshRedisConnections(): void
    {
        try {
            if (!$this->isRedisAvailable()) {
                return;
            }

            app()->extend(RedisFactory::class, function ($redisFactory, $app) {
                return new \Illuminate\Redis\RedisManager(
                    $app,
                    $app['config']['database.redis.client'] ?? 'phpredis',
                    $app['config']['database.redis'] ?? []
                );
            });

            app()->forgetInstance(RedisFactory::class);
            app()->forgetInstance('redis');
        } catch (Exception $e) {
            throw new RuntimeException('Failed to refresh Redis connections', 0, $e);
        }
    }
}