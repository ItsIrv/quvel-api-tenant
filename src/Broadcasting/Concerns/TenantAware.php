<?php

declare(strict_types=1);

namespace Quvel\Tenant\Broadcasting\Concerns;

use Quvel\Tenant\Context\TenantContext;

/**
 * Trait for making broadcast events optionally tenant-aware.
 *
 * This trait provides helper methods for working with tenant-scoped channels
 * while preserving the standard Laravel broadcasting interface.
 *
 * Usage Options:
 *
 * 1. Mix tenant and global channels:
 *    public function broadcastOn() {
 *        return [
 *            $this->tenantChannel('notifications'),  // Tenant-specific
 *            'global-announcements'                  // Global channel
 *        ];
 *    }
 *
 * 2. Use helper methods for common patterns:
 *    public function broadcastOn() {
 *        return $this->tenantChannels(['orders', 'notifications']);
 *    }
 *
 * 3. Conditional tenant scoping:
 *    public function broadcastOn() {
 *        return $this->shouldBeTenantScoped()
 *            ? $this->tenantChannels(['orders'])
 *            : ['global-orders'];
 *    }
 */
trait TenantAware
{
    /**
     * Get tenant-prefixed versions of the given channels.
     *
     * @param array $channels
     * @return array
     */
    protected function tenantChannels(array $channels): array
    {
        return array_map([$this, 'tenantChannel'], $channels);
    }

    /**
     * Get a tenant-prefixed version of a single channel.
     *
     * @param object|string $channel
     * @return string|object
     */
    protected function tenantChannel(object|string $channel): object|string
    {
        $tenant = app(TenantContext::class)->current();
        if (!$tenant) {
            return $channel;
        }

        $channelName = is_object($channel) ? $channel->name : $channel;
        $prefix = "tenant.$tenant->public_id.";

        if (str_starts_with($channelName, 'tenant.')) {
            return $channel;
        }

        if (str_starts_with($channelName, 'presence-')) {
            $newName = 'presence-' . $prefix . substr($channelName, 9);
        } elseif (str_starts_with($channelName, 'private-')) {
            $newName = 'private-' . $prefix . substr($channelName, 8);
        } else {
            $newName = $prefix . $channelName;
        }

        if (is_object($channel)) {
            return new $channel($newName);
        }

        return $newName;
    }

    /**
     * Get a tenant-specific presence channel.
     *
     * @param string $channel
     * @return string
     */
    protected function tenantPresenceChannel(string $channel): string
    {
        return $this->tenantChannel('presence-' . $channel);
    }

    /**
     * Get a tenant-specific private channel.
     *
     * @param string $channel
     * @return string
     */
    protected function tenantPrivateChannel(string $channel): string
    {
        return $this->tenantChannel('private-' . $channel);
    }

    /**
     * Check if the current event should be tenant-scoped.
     * Override this method to implement custom logic.
     *
     * @return bool
     */
    protected function shouldBeTenantScoped(): bool
    {
        return tenant() !== null;
    }
}