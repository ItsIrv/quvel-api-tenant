<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;

/**
 * Handles broadcasting configuration for tenants.
 */
class BroadcastingConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'broadcasting.default',
            'broadcasting.connections.pusher.app_id',
            'broadcasting.connections.pusher.key',
            'broadcasting.connections.pusher.secret',
            'broadcasting.connections.pusher.options.cluster',
            'broadcasting.connections.pusher.options.scheme',
            'broadcasting.connections.pusher.options.host',
            'broadcasting.connections.pusher.options.port',
            'broadcasting.connections.reverb.app_id',
            'broadcasting.connections.reverb.key',
            'broadcasting.connections.reverb.secret',
            'broadcasting.connections.reverb.options.host',
            'broadcasting.connections.reverb.options.port',
            'broadcasting.connections.ably.key',
        ]);

        $isRedisDefault = $this->config->get('broadcasting.default') === 'redis';
        $hasRedisPrefix = $this->tenant->hasConfig('broadcasting.connections.redis.prefix');

        if ($isRedisDefault || $hasRedisPrefix) {
            $prefix = $this->tenant->getConfig('broadcasting.connections.redis.prefix')
                ?? $this->getRedisPrefix();

            $this->config->set('broadcasting.connections.redis.prefix', $prefix);
        }
    }

    /**
     * Configure Redis prefix generator.
     */
    public static function withRedisPrefix(Closure $callback): string
    {
        static::registerConfigurator('redis_prefix', $callback);

        return static::class;
    }

    /**
     * Get Redis prefix using configurator or default.
     */
    protected function getRedisPrefix(): string
    {
        return $this->applyConfigurator('redis_prefix', 'tenant_' . $this->tenant->public_id);
    }
}
