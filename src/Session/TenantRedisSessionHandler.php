<?php

declare(strict_types=1);

namespace Quvel\Tenant\Session;

use Illuminate\Session\CacheBasedSessionHandler;
use SessionHandlerInterface;
use Quvel\Tenant\Contracts\TenantContext;

/**
 * Tenant-aware Redis session handler that isolates sessions by tenant.
 */
class TenantRedisSessionHandler implements SessionHandlerInterface
{
    protected CacheBasedSessionHandler $handler;

    public function __construct(
        protected $cache,
        protected int $minutes,
        protected TenantContext $tenantContext
    ) {
        $this->handler = new CacheBasedSessionHandler($cache, $minutes);
    }

    /**
     * Get tenant-specific cache key for session.
     */
    protected function getTenantKey(string $sessionId): string
    {
        if (!config('tenant.sessions.auto_tenant_id', false)) {
            return $sessionId;
        }

        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return $sessionId;
        }

        return "tenant_$tenant->id:session:$sessionId";
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $name): bool
    {
        return $this->handler->open($path, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return $this->handler->close();
    }

    /**
     * {@inheritdoc}
     */
    public function read($id): string
    {
        $key = $this->getTenantKey($id);
        return $this->cache->get($key, '');
    }

    /**
     * {@inheritdoc}
     */
    public function write($id, $data): bool
    {
        $key = $this->getTenantKey($id);
        return (bool) $this->cache->put($key, $data, $this->minutes);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id): bool
    {
        $key = $this->getTenantKey($id);
        return $this->cache->forget($key);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($max_lifetime): int
    {
        // For Redis, we rely on TTL expiration rather than manual garbage collection
        // The CacheBasedSessionHandler handles this automatically with cache TTL
        return 0;
    }

    /**
     * Get all session keys for a specific tenant.
     */
    public function getTenantSessionKeys(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? $this->tenantContext->current()?->id;

        if (!$tenantId) {
            return [];
        }

        $pattern = "tenant_$tenantId:session:*";

        if (method_exists($this->cache->getStore(), 'connection')) {
            return $this->cache->getStore()->connection()->keys($pattern);
        }

        return [];
    }

    /**
     * Clear all sessions for a specific tenant.
     */
    public function clearTenantSessions(?int $tenantId = null): int
    {
        $keys = $this->getTenantSessionKeys($tenantId);
        $cleared = 0;

        foreach ($keys as $key) {
            if ($this->cache->forget($key)) {
                $cleared++;
            }
        }

        return $cleared;
    }
}