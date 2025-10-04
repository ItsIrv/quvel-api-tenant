<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Context;
use Illuminate\Routing\UrlGenerator;

/**
 * Handles core application configuration for tenants.
 */
class CoreConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'app_name' => 'app.name',
            'app_env' => 'app.env',
            'app_key' => 'app.key',
            'app_debug' => 'app.debug',
            'app_url' => 'app.url',
            'app_timezone' => 'app.timezone',
            'app_locale' => 'app.locale',
            'app_fallback_locale' => 'app.fallback_locale',
        ]);

        $this->configureCors();
        $this->handleForwardedPrefix();

        if ($this->tenant->hasConfig('app_url')) {
            $this->refreshUrlGenerator();
        }

        if ($this->tenant->hasConfig('app_locale')) {
            $this->refreshLocale();
        }

        Context::add('tenant_id', $this->tenant->public_id);
    }

    /**
     * Configure CORS based on tenant URLs.
     */
    protected function configureCors(): void
    {
        $allowedOrigins = [];

        if ($this->tenant->hasConfig('app_url')) {
            $allowedOrigins[] = $this->tenant->getConfig('app_url');
        }

        if ($this->tenant->hasConfig('frontend_url')) {
            $allowedOrigins[] = $this->tenant->getConfig('frontend_url');
        }

        if (!empty($allowedOrigins)) {
            $this->config->set('cors.allowed_origins', $allowedOrigins);
        }
    }

    /**
     * Refresh URL generator with new app URL.
     */
    protected function refreshUrlGenerator(): void
    {
        try {
            /** @var UrlGenerator $urlGenerator */
            $urlGenerator = app(UrlGenerator::class);
            $appUrl = $this->config->get('app.url');

            if ($appUrl !== null) {
                $urlGenerator->useOrigin($appUrl);
            }
        } catch (Exception) {
            // Log error if needed
        }
    }

    /**
     * Update locale in Laravel runtime.
     */
    protected function refreshLocale(): void
    {
        try {
            $locale = $this->config->get('app.locale');

            if ($locale) {
                App::setLocale($locale);
            }
        } catch (Exception) {
            // Log error if needed
        }
    }

    /**
     * Handle X-Forwarded-Prefix header for proxy setups.
     */
    protected function handleForwardedPrefix(): void
    {
        try {
            if (!app()->bound('request')) {
                return;
            }

            $request = app('request');
            $prefix = $request->header('X-Forwarded-Prefix');

            if ($prefix !== null && $request->isFromTrustedProxy()) {
                /** @var UrlGenerator $urlGenerator */
                $urlGenerator = app(UrlGenerator::class);
                $urlGenerator->useOrigin(
                    $request->getSchemeAndHttpHost() . $prefix
                );
            }
        } catch (Exception) {
            // Log error if needed
        }
    }
}