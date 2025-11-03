<?php

declare(strict_types=1);

use Illuminate\Contracts\Mail\Mailer;
use Quvel\Tenant\Facades\TenantContext;
use Quvel\Tenant\Models\Tenant;

if (!function_exists('tenant')) {
    /**
     * Get the current tenant from context.
     */
    function tenant(): ?Tenant
    {
        return TenantContext::current();
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
     * Check if the tenant resolution is bypassed.
     */
    function tenant_bypassed(): bool
    {
        return TenantContext::isBypassed();
    }
}

if (!function_exists('with_tenant')) {
    /**
     * Execute callback with a specific tenant context.
     */
    function with_tenant(?Tenant $tenant, callable $callback): mixed
    {
        $original = TenantContext::current();

        try {
            TenantContext::setCurrent($tenant);
            return $callback();
        } finally {
            TenantContext::setCurrent($original);
        }
    }
}

if (!function_exists('without_tenant')) {
    /**
     * Execute callback without tenant scoping (bypassed).
     */
    function without_tenant(callable $callback): mixed
    {
        $wasBypassed = TenantContext::isBypassed();

        try {
            TenantContext::bypass();
            return $callback();
        } finally {
            if (!$wasBypassed) {
                TenantContext::clearBypassed();
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

        if (str_starts_with($channel, 'tenant.') ||
            str_contains($channel, "tenant.$tenant->public_id.")) {
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


if (!function_exists('tenant_mail')) {
    /**
     * Get a tenant-specific mail manager instance.
     */
    function tenant_mail(): Mailer
    {
        $mailerName = tenant_mailer();
        return app('mail.manager')->mailer($mailerName);
    }
}

if (!function_exists('tenant_mailer')) {
    /**
     * Get the tenant-specific mailer driver name.
     */
    function tenant_mailer(): string
    {
        $tenant = tenant();

        if (!$tenant) {
            return config('mail.default');
        }

        return $tenant->getConfig('mail.default', config('mail.default'));
    }
}

if (!function_exists('tenant_from')) {
    /**
     * Get tenant-specific from address and name.
     */
    function tenant_from(): array
    {
        $tenant = tenant();

        if (!$tenant) {
            return [
                'address' => config('mail.from.address'),
                'name' => config('mail.from.name'),
            ];
        }

        return [
            'address' => $tenant->getConfig('mail.from.address', config('mail.from.address')),
            'name' => $tenant->getConfig('mail.from.name', config('mail.from.name')),
        ];
    }
}

if (!function_exists('tenant_reply_to')) {
    /**
     * Get tenant-specific reply-to address and name.
     */
    function tenant_reply_to(): ?array
    {
        $tenant = tenant();

        if (!$tenant) {
            return null;
        }

        $address = $tenant->getConfig('mail.reply_to.address');
        $name = $tenant->getConfig('mail.reply_to.name');

        if (!$address) {
            return null;
        }

        return [
            'address' => $address,
            'name' => $name,
        ];
    }
}

if (!function_exists('tenant_class')) {
    /**
     * Get the tenant model class.
     *
     * @return class-string<Tenant>
     */
    function tenant_class(): string
    {
        return config('tenant.model', Tenant::class);
    }
}

if (!function_exists('tenant_model')) {
    /**
     * Get a new instance of the configured tenant model.
     */
    function tenant_model(): mixed
    {
        return app(tenant_class());
    }
}

if (!function_exists('tenant_event')) {
    /**
     * Dispatch an event with a tenant context.
     */
    function tenant_event(object|string $event, array $payload = [], bool $halt = false): ?array
    {
        return event($event, $payload, $halt);
    }
}

if (!function_exists('tenant_event_name')) {
    /**
     * Get a tenant-scoped event name.
     */
    function tenant_event_name(string $eventName): string
    {
        $tenant = tenant();

        if (!$tenant) {
            return $eventName;
        }

        return "tenant.$tenant->public_id.$eventName";
    }
}

if (!function_exists('with_tenant_events')) {
    /**
     * Execute callback with a tenant-aware event context.
     */
    function with_tenant_events(?Tenant $tenant, callable $callback): mixed
    {
        return with_tenant($tenant, $callback);
    }
}

if (!function_exists('tenant_event_data')) {
    /**
     * Get tenant context data for events.
     */
    function tenant_event_data(): array
    {
        $tenant = tenant();

        if (!$tenant) {
            return [];
        }

        return [
            'tenant_id' => $tenant->id,
            'tenant_public_id' => $tenant->public_id,
            'tenant_name' => $tenant->name,
        ];
    }
}

if (!function_exists('tenant_cache_key')) {
    /**
     * Get a tenant-scoped cache key.
     */
    function tenant_cache_key(string $key): string
    {
        $tenant = tenant();

        if (!$tenant) {
            return $key;
        }

        return "tenant.$tenant->public_id.$key";
    }
}

if (!function_exists('tenant_cache_tags')) {
    /**
     * Get tenant-scoped cache tags.
     */
    function tenant_cache_tags(array $tags = []): array
    {
        $tenant = tenant();

        if (!$tenant) {
            return $tags;
        }

        $tenantTag = "tenant.$tenant->public_id";

        return array_merge([$tenantTag], $tags);
    }
}

if (!function_exists('tenant_cache_remember')) {
    /**
     * Remember a value in the cache with tenant scoping.
     */
    function tenant_cache_remember(string $key, $ttl, Closure $callback): mixed
    {
        return Cache::remember(tenant_cache_key($key), $ttl, $callback);
    }
}

if (!function_exists('tenant_cache_get')) {
    /**
     * Get a value from the cache with tenant scoping.
     */
    function tenant_cache_get(string $key, mixed $default = null): mixed
    {
        return Cache::get(tenant_cache_key($key), $default);
    }
}

if (!function_exists('tenant_cache_put')) {
    /**
     * Put a value in the cache with tenant scoping.
     */
    function tenant_cache_put(string $key, mixed $value, $ttl = null): bool
    {
        return Cache::put(tenant_cache_key($key), $value, $ttl);
    }
}

if (!function_exists('tenant_cache_forget')) {
    /**
     * Remove a value from the cache with tenant scoping.
     */
    function tenant_cache_forget(string $key): bool
    {
        return Cache::forget(tenant_cache_key($key));
    }
}

if (!function_exists('tenant_cache_flush')) {
    /**
     * Flush all cache for the current tenant.
     * Only works with tag-capable cache drivers (Redis, Memcached).
     */
    function tenant_cache_flush(): bool
    {
        $tenant = tenant();

        if (!$tenant) {
            return false;
        }

        // Check if the cache driver supports tags
        $driver = config('cache.default');
        $tagCapableDrivers = ['redis', 'memcached'];

        if (!in_array($driver, $tagCapableDrivers, true)) {
            return false;
        }

        return Cache::tags(["tenant.$tenant->public_id"])->flush();
    }
}

if (!function_exists('tenant_notification_channels')) {
    /**
     * Get tenant-specific notification channels.
     */
    function tenant_notification_channels(array $defaultChannels): array
    {
        $tenant = tenant();

        if (!$tenant) {
            return $defaultChannels;
        }

        // Get tenant-specific notification preferences
        $enabledChannels = $tenant->getConfig('notifications.channels', $defaultChannels);

        // Filter to only enabled channels
        return array_intersect($defaultChannels, $enabledChannels);
    }
}

if (!function_exists('tenant_notification_enabled')) {
    /**
     * Check if a specific notification type is enabled for the tenant.
     */
    function tenant_notification_enabled(string $type): bool
    {
        $tenant = tenant();

        if (!$tenant) {
            return true; // Default to enabled if no tenant context
        }

        return $tenant->getConfig("notifications.types.$type.enabled", true);
    }
}

if (!function_exists('tenant_notification_preferences')) {
    /**
     * Get tenant-specific notification preferences.
     */
    function tenant_notification_preferences(string $type): array
    {
        $tenant = tenant();

        if (!$tenant) {
            return [];
        }

        return $tenant->getConfig("notifications.preferences.$type", []);
    }
}

if (!function_exists('tenant_notification_data')) {
    /**
     * Get tenant context data for database notifications.
     */
    function tenant_notification_data(array $data = []): array
    {
        $tenantData = tenant_event_data(); // Reuse the event data helper

        return array_merge($data, [
            'tenant_context' => $tenantData,
        ]);
    }
}

if (!function_exists('tenant_storage_path')) {
    /**
     * Get a tenant-scoped storage path.
     */
    function tenant_storage_path(string $path = ''): string
    {
        $tenant = tenant();

        if (!$tenant) {
            return $path;
        }

        $tenantFolder = "tenant-$tenant->public_id";

        return $path ? "$tenantFolder/$path" : $tenantFolder;
    }
}

if (!function_exists('tenant_storage_disk')) {
    /**
     * Get the tenant-specific disk name.
     */
    function tenant_storage_disk(): string
    {
        $tenant = tenant();

        if (!$tenant) {
            return config('filesystems.default');
        }

        return $tenant->getConfig('filesystems.default', config('filesystems.default'));
    }
}

if (!function_exists('tenant_storage_put')) {
    /**
     * Store a file in tenant-scoped storage.
     */
    function tenant_storage_put(string $path, mixed $contents, mixed $options = []): bool
    {
        return Storage::disk(tenant_storage_disk())->put(tenant_storage_path($path), $contents, $options);
    }
}

if (!function_exists('tenant_storage_get')) {
    /**
     * Get the contents of a file from tenant-scoped storage.
     */
    function tenant_storage_get(string $path): string
    {
        return Storage::disk(tenant_storage_disk())->get(tenant_storage_path($path));
    }
}

if (!function_exists('tenant_storage_exists')) {
    /**
     * Check if a file exists in tenant-scoped storage.
     */
    function tenant_storage_exists(string $path): bool
    {
        return Storage::disk(tenant_storage_disk())->exists(tenant_storage_path($path));
    }
}

if (!function_exists('tenant_storage_delete')) {
    /**
     * Delete a file from tenant-scoped storage.
     */
    function tenant_storage_delete(string|array $paths): bool
    {
        $disk = Storage::disk(tenant_storage_disk());

        if (is_array($paths)) {
            $tenantPaths = array_map('tenant_storage_path', $paths);
            return $disk->delete($tenantPaths);
        }

        return $disk->delete(tenant_storage_path($paths));
    }
}

if (!function_exists('tenant_storage_url')) {
    /**
     * Get a URL for a file in tenant-scoped storage.
     */
    function tenant_storage_url(string $path): string
    {
        return Storage::disk(tenant_storage_disk())->url(tenant_storage_path($path));
    }
}

if (!function_exists('tenant_storage_temporary_url')) {
    /**
     * Get a temporary URL for a file in tenant-scoped storage.
     */
    function tenant_storage_temporary_url(string $path, DateTimeInterface $expiration, array $options = []): string
    {
        $disk = Storage::disk(tenant_storage_disk());

        if (!method_exists($disk, 'temporaryUrl')) {
            throw new RuntimeException("Disk [" . tenant_storage_disk() . "] does not support temporary URLs.");
        }

        return $disk->temporaryUrl(tenant_storage_path($path), $expiration, $options);
    }
}
