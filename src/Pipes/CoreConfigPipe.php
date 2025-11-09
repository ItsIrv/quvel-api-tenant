<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Context;

/**
 * Handles core application configuration for tenants.
 */
class CoreConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'app.name', // required
            'app.env',
            'app.key',
            'app.debug',
            'app.url', // required
            'app.timezone',
            'app.locale',
            'app.fallback_locale',
            'frontend.url', // required
            'frontend.internal_api_url', // highly recommended
            'frontend.capacitor_scheme',
        ]);

        $this->configureCors();
        $this->handleForwardedPrefix();

        if ($this->tenant->hasConfig('app.url')) {
            $this->refreshUrlGenerator();
        }

        if ($this->tenant->hasConfig('app.locale')) {
            $this->refreshLocale();
        }

        Context::add('tenant_id', $this->getContextTenantId());
    }

    /**
     * Configure CORS based on tenant URLs.
     */
    protected function configureCors(): void
    {
        $allowedOrigins = [];

        if ($this->tenant->hasConfig('app.url')) {
            $allowedOrigins[] = $this->tenant->getConfig('app.url');
        }

        if ($this->tenant->hasConfig('frontend.url')) {
            $allowedOrigins[] = $this->tenant->getConfig('frontend.url');
        }

        if ($allowedOrigins !== []) {
            $allowedOrigins = $this->getCustomCorsOrigins($allowedOrigins);
            $this->config->set('cors.allowed_origins', $allowedOrigins);
        }
    }

    /**
     * Refresh URL generator with new app URL.
     */
    protected function refreshUrlGenerator(): void
    {
        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = app(UrlGenerator::class);
        $appUrl = $this->config->get('app.url');

        if ($appUrl !== null) {
            $urlGenerator->useOrigin($appUrl);
        }
    }

    /**
     * Update locale in Laravel runtime.
     */
    protected function refreshLocale(): void
    {
        $locale = $this->config->get('app.locale');

        if ($locale) {
            App::setLocale($locale);
        }
    }

    /**
     * Handle X-Forwarded-Prefix header for proxy setups.
     */
    protected function handleForwardedPrefix(): void
    {
        /** @psalm-suppress TypeDoesNotContainType bound() can return false when binding doesn't exist */
        if (!app()->bound(Request::class)) {
            return;
        }

        $request = app(Request::class);
        $prefix = $request->header('X-Forwarded-Prefix');

        if ($prefix !== null && $request->isFromTrustedProxy()) {
            /** @var UrlGenerator $urlGenerator */
            $urlGenerator = app(UrlGenerator::class);
            $urlGenerator->useOrigin(
                $request->getSchemeAndHttpHost() . $prefix
            );
        }
    }

    /**
     * Configure context tenant ID generator.
     */
    public static function withContextTenantId(Closure $callback): string
    {
        static::registerConfigurator('context_tenant_id', $callback);

        return static::class;
    }

    /**
     * Configure CORS origins customizer.
     */
    public static function withCorsOrigins(Closure $callback): string
    {
        static::registerConfigurator('cors_origins', $callback);

        return static::class;
    }

    /**
     * Get context tenant ID using configurator or default.
     */
    protected function getContextTenantId(): string
    {
        return $this->applyConfigurator('context_tenant_id', $this->tenant->public_id);
    }

    /**
     * Get custom CORS origins using configurator or default.
     */
    protected function getCustomCorsOrigins(array $defaultOrigins): array
    {
        if ($this->hasConfigurator('cors_origins')) {
            return static::$configurators['cors_origins']($this, $defaultOrigins);
        }

        return $defaultOrigins;
    }
}
