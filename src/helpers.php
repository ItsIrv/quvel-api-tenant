<?php

declare(strict_types=1);

use Quvel\Tenant\Context\TenantContext;
use Quvel\Tenant\Models\Tenant;

if (!function_exists('tenant')) {
    /**
     * Get the current tenant from context.
     */
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->current();
    }
}

if (!function_exists('tenant_id')) {
    /**
     * Get the current tenant ID.
     */
    function tenant_id(): ?int
    {
        return tenant()?->id;
    }
}

if (!function_exists('tenant_public_id')) {
    /**
     * Get the current tenant public ID.
     */
    function tenant_public_id(): ?string
    {
        return tenant()?->public_id;
    }
}

if (!function_exists('tenant_config')) {
    /**
     * Get a tenant config value with optional default.
     */
    function tenant_config(string $key, mixed $default = null): mixed
    {
        return tenant()?->getConfig($key, $default) ?? $default;
    }
}

if (!function_exists('tenant_bypassed')) {
    /**
     * Check if tenant resolution is bypassed.
     */
    function tenant_bypassed(): bool
    {
        return app(TenantContext::class)->isBypassed();
    }
}

if (!function_exists('with_tenant')) {
    /**
     * Execute callback with specific tenant context.
     */
    function with_tenant(?Tenant $tenant, callable $callback): mixed
    {
        $context = app(TenantContext::class);
        $original = $context->current();

        try {
            $context->setCurrent($tenant);
            return $callback();
        } finally {
            $context->setCurrent($original);
        }
    }
}

if (!function_exists('without_tenant')) {
    /**
     * Execute callback without tenant scoping (bypassed).
     */
    function without_tenant(callable $callback): mixed
    {
        $context = app(TenantContext::class);
        $wasBypassed = $context->isBypassed();

        try {
            $context->bypass();
            return $callback();
        } finally {
            if (!$wasBypassed) {
                $context->clearBypassed();
            }
        }
    }
}

if (!function_exists('tenant_channel')) {
    /**
     * Get a tenant-specific channel name.
     */
    function tenant_channel(string $channel): string
    {
        $tenant = tenant();

        if (!$tenant) {
            return $channel;
        }

        $prefix = "tenant.$tenant->public_id.";

        if (str_starts_with($channel, 'tenant.')) {
            return $channel;
        }

        if (str_starts_with($channel, 'presence-')) {
            return 'presence-' . $prefix . substr($channel, 9);
        }

        if (str_starts_with($channel, 'private-')) {
            return 'private-' . $prefix . substr($channel, 8);
        }

        return $prefix . $channel;
    }
}

if (!function_exists('tenant_broadcast')) {
    /**
     * Broadcast an event to a tenant-specific channel.
     */
    function tenant_broadcast($channels, string $event, array $data = []): void
    {
        $channels = is_array($channels) ? $channels : [$channels];
        $tenantChannels = array_map('tenant_channel', $channels);

        broadcast($event)->to($tenantChannels)->with($data);
    }
}
