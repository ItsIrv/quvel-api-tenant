<?php

declare(strict_types=1);

namespace Quvel\Tenant\Notifications\Concerns;

use Quvel\Tenant\Concerns\TenantAware as BaseTenantAware;

/**
 * This trait provides helper methods for working with tenant-specific notification
 * settings while preserving the standard Laravel notification interface.
 *
 * Usage Options:
 *
 * 1. Tenant-specific notification channels:
 *    public function via($notifiable) {
 *        return $this->getTenantChannels(['mail', 'database']);
 *    }
 *
 * 2. Conditional tenant notifications:
 *    public function toMail($notifiable) {
 *        return $this->hasTenantContext()
 *            ? $this->buildTenantMail($notifiable)
 *            : $this->buildDefaultMail($notifiable);
 *    }
 *
 * 3. Tenant-scoped database notifications:
 *    public function toDatabase($notifiable) {
 *        return [
 *            'message' => $this->message,
 *            'tenant' => $this->getTenantData(),
 *        ];
 *    }
 *
 * 4. Tenant-aware broadcast channels:
 *    public function broadcastOn() {
 *        return $this->getTenantBroadcastChannels(['notifications']);
 *    }
 */
trait HasTenantNotifications
{
    use BaseTenantAware;

    /**
     * Get tenant-specific notification channels.
     * Filters and modifies channels based on tenant configuration.
     */
    protected function getTenantChannels(array $defaultChannels): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return $defaultChannels;
        }

        $enabledChannels = $tenant->getConfig('notifications.channels', $defaultChannels);

        return array_intersect($defaultChannels, $enabledChannels);
    }

    /**
     * Get tenant-specific mail settings for notifications.
     */
    protected function getTenantMailSettings(): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [
                'from' => [
                    'address' => config('mail.from.address'),
                    'name' => config('mail.from.name'),
                ],
                'mailer' => config('mail.default'),
            ];
        }

        return [
            'from' => [
                'address' => $tenant->getConfig('mail.from.address', config('mail.from.address')),
                'name' => $tenant->getConfig('mail.from.name', config('mail.from.name')),
            ],
            'mailer' => $tenant->getConfig('mail.default', config('mail.default')),
            'reply_to' => [
                'address' => $tenant->getConfig('mail.reply_to.address'),
                'name' => $tenant->getConfig('mail.reply_to.name'),
            ],
        ];
    }

    /**
     * Get tenant-specific broadcast channels for notifications.
     */
    protected function getTenantBroadcastChannels(array $channels): array
    {
        return array_map(tenant_channel(...), $channels);
    }

    /**
     * Get tenant-specific database notification data.
     */
    protected function getTenantDatabaseData(array $data = []): array
    {
        $tenantData = $this->getTenantData();

        return array_merge($data, [
            'tenant_context' => $tenantData,
        ]);
    }

    /**
     * Get tenant-specific notification preferences.
     */
    protected function getTenantNotificationPreferences(string $type): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [];
        }

        return $tenant->getConfig('notifications.preferences.' . $type, []);
    }

    /**
     * Check if a specific notification type is enabled for the tenant.
     */
    protected function isNotificationTypeEnabled(string $type): bool
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return true; // Default to enabled if no tenant context
        }

        return $tenant->getConfig(sprintf('notifications.types.%s.enabled', $type), true);
    }
}
