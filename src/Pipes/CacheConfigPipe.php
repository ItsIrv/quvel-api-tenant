<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Handles cache configuration for tenants.
 */
class CacheConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $hasCacheOverride = $this->tenant->hasConfig('cache.default');

        $this->setMany([
            'cache.default',
        ]);

        $prefix = $this->tenant->hasConfig('cache.prefix')
            ? $this->tenant->getConfig('cache.prefix')
            : $this->getDefaultPrefix();

        $this->config->set('cache.prefix', $prefix);

        if ($hasCacheOverride) {
            $defaultDriver = $this->config->get('cache.default');

            Cache::setDefaultDriver($defaultDriver);
        }
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
        return $this->applyConfigurator('default_prefix', 'tenant_' . $this->tenant->public_id);
    }
}
