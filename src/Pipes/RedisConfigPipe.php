<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;

/**
 * Handles Redis configuration for tenants.
 */
class RedisConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'database.redis.client',
            'database.redis.default.host',
            'database.redis.default.password',
            'database.redis.default.port',
        ]);

        if ($this->tenant->hasConfig('database.redis.default.prefix')) {
            $prefix = $this->tenant->getConfig('database.redis.default.prefix');
        } else {
            $prefix = $this->getDefaultPrefix();
        }

        $this->config->set('database.redis.default.prefix', $prefix);
        $this->config->set('database.redis.cache.prefix', $prefix);
    }

    /**
     * Configure default prefix generator.
     */
    public static function withDefaultPrefix(Closure $callback): string
    {
        static::registerConfigurator('default_prefix', $callback);

        return static::class;
    }

    /**
     * Get default prefix using configurator or default.
     */
    protected function getDefaultPrefix(): string
    {
        return $this->applyConfigurator('default_prefix', "tenant_{$this->tenant->public_id}:");
    }
}
