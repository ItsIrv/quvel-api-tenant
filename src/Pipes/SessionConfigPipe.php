<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Exception;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use RuntimeException;

/**
 * Handles session configuration for tenants.
 */
class SessionConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $oldCookie = $this->config->get('session.cookie');

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

        if ($this->config->get('session.driver') === 'database') {
            $dbConnection = $this->config->get('database.default');
            $this->config->set('session.connection', $dbConnection);
        }

        if ($oldCookie !== $newCookie) {
            $this->updateSessionManager($newCookie);
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

        return "tenant_{$tenantForCookie->public_id}_session";
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
     * Update the session manager with the new cookie name.
     */
    protected function updateSessionManager(string $cookieName): void
    {
        if (!app()->bound(SessionManager::class)) {
            return;
        }

        try {
            /** @var SessionManager $sessionManager */
            $sessionManager = app(SessionManager::class);

            /** @var Store $driver */
            $driver = $sessionManager->driver();
            $driver->setName($cookieName);
        } catch (Exception) {
            throw new RuntimeException('Failed to update session manager');
        }
    }
}