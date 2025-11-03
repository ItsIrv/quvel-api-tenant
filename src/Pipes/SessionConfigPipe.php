<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;
use Illuminate\Session\SessionManager;

/**
 * Handles session configuration for tenants.
 */
class SessionConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'session.driver',
            'session.lifetime',
            'session.encrypt',
            'session.path',
        ]);

        if ($this->tenant->hasConfig('session.domain')) {
            $this->setIfExists('session.domain', 'session.domain');
        } else {
            $sessionDomain = $this->extractSessionDomain();

            if ($sessionDomain !== null) {
                $this->config->set('session.domain', $sessionDomain);
            }
        }

        $newCookie = $this->calculateSessionCookie();
        $this->config->set('session.cookie', $newCookie);

        $xsrfCookie = $this->calculateXsrfCookie();
        $this->config->set('session.xsrf_cookie', $xsrfCookie);

        if ($this->config->get('session.driver') === 'database') {
            $dbConnection = $this->config->get('database.default');
            $this->config->set('session.connection', $dbConnection);
        }

        $hasDriverOverride = $this->tenant->hasConfig('session.driver');

        if ($hasDriverOverride) {
            $sessionManager = app(SessionManager::class);
            $sessionManager->setDefaultDriver($this->config->get('session.driver'));
        }
    }

    /**
     * Calculate session cookie name for tenant isolation.
     */
    protected function calculateSessionCookie(): string
    {
        if ($this->tenant->hasConfig('session.cookie')) {
            return $this->tenant->getConfig('session.cookie');
        }

        $tenantForCookie = $this->tenant->parent ?? $this->tenant;

        return $this->getDefaultCookieName($tenantForCookie);
    }

    /**
     * Calculate XSRF cookie name for tenant isolation.
     */
    protected function calculateXsrfCookie(): string
    {
        if ($this->tenant->hasConfig('session.xsrf_cookie')) {
            return $this->tenant->getConfig('session.xsrf_cookie');
        }

        $tenantForCookie = $this->tenant->parent ?? $this->tenant;

        return "tenant_{$tenantForCookie->public_id}_xsrf";
    }

    /**
     * Extract session domain from tenant configuration.
     */
    protected function extractSessionDomain(): ?string
    {
        $apiUrl = $this->tenant->getConfig('app.url');
        $frontendUrl = $this->tenant->getConfig('frontend.url');

        if ($apiUrl !== null) {
            $domain = parse_url($apiUrl, PHP_URL_HOST);
        } elseif ($frontendUrl !== null) {
            $domain = parse_url($frontendUrl, PHP_URL_HOST);
        } else {
            $domain = $this->tenant->identifier;
        }

        if ($domain === null || $domain === false || $domain === '') {
            return null;
        }

        $parts = explode('.', $domain);

        if (count($parts) > 2) {
            array_shift($parts);
            $rootDomain = '.' . implode('.', $parts);
        } else {
            $rootDomain = '.' . $domain;
        }

        return $rootDomain;
    }

    /**
     * Configure session cookie name generator.
     */
    public static function withDefaultCookieName(Closure $callback): string
    {
        static::registerConfigurator('default_cookie_name', $callback);

        return static::class;
    }

    /**
     * Get the default cookie name using configurator or default.
     */
    protected function getDefaultCookieName($tenantForCookie): string
    {
        if ($this->hasConfigurator('default_cookie_name')) {
            return static::$configurators['default_cookie_name']($this, $tenantForCookie);
        }

        return "tenant_{$tenantForCookie->public_id}_session";
    }
}
