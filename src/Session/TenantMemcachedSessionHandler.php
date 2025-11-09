<?php

declare(strict_types=1);

namespace Quvel\Tenant\Session;

use Illuminate\Session\CacheBasedSessionHandler;
use Quvel\Tenant\Contracts\TenantContext;
use RuntimeException;

/**
 * Tenant-aware Memcached session handler that isolates sessions by tenant.
 */
class TenantMemcachedSessionHandler extends CacheBasedSessionHandler
{
    public function __construct(
        protected $cache,
        protected $minutes,
        protected TenantContext $tenantContext
    ) {
        parent::__construct($cache, $minutes);
    }

    /**
     * Get tenant-specific cache key for session.
     */
    protected function getTenantKey(
        string $sessionId
    ): string {
        if (!config('tenant.sessions.auto_tenant_id', false)) {
            return $sessionId;
        }

        // Throw error for an untested driver
        throw new RuntimeException(
            'TenantMemcachedSessionHandler has not been tested yet. ' .
            'Please use database, file, or redis session drivers instead. ' .
            'If you need Memcached support, please test and verify this implementation.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function read(
        $sessionId
    ): string {
        $key = $this->getTenantKey($sessionId);

        return $this->cache->get($key, '');
    }

    /**
     * {@inheritdoc}
     */
    public function write(
        $sessionId,
        $data
    ): bool {
        $key = $this->getTenantKey($sessionId);

        return $this->cache->put($key, $data, $this->minutes);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy(
        $sessionId
    ): bool {
        $key = $this->getTenantKey($sessionId);

        return $this->cache->forget($key);
    }

    /**
     * Clear all sessions for a specific tenant.
     *
     * Note: Memcached doesn't support key pattern matching like Redis,
     * so this is limited. Consider using a tenant session registry if needed.
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
     */
    public function clearTenantSessions(
        ?int $tenantId = null
    ): int {
        // Memcached doesn't have native pattern matching
        // This would require maintaining a separate registry of session keys
        // For now, we'll just return 0 and log a warning

        if (config('app.debug')) {
            logger()->warning(
                'TenantMemcachedSessionHandler::clearTenantSessions() is not fully supported. '
                . 'Consider using Redis for better tenant session management.'
            );
        }

        return 0;
    }

    /**
     * Get a tenant-specific session prefix for debugging.
     */
    public function getTenantPrefix(
        ?int $tenantId = null
    ): string {
        $tenantId ??= $this->tenantContext->current()?->id;

        if (!$tenantId) {
            return '';
        }

        return sprintf('tenant_%s:session:', $tenantId);
    }
}
