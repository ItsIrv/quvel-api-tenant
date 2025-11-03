<?php

declare(strict_types=1);

namespace Quvel\Tenant\Events\Concerns;

use Quvel\Tenant\Concerns\TenantAware as BaseTenantAware;

/**
 * Trait for making event classes optionally tenant-aware.
 *
 * This trait provides helper methods for working with tenant-specific event data
 * and context while preserving the standard Laravel event interface.
 *
 * Usage Options:
 *
 * 1. Basic tenant context in event:
 *    class UserRegistered {
 *        use HasTenantEvents;
 *
 *        public function broadcastWith() {
 *            return [
 *                'user' => $this->user,
 *                'tenant' => $this->getCurrentTenant(),
 *            ];
 *        }
 *    }
 *
 * 2. Conditional tenant event handling:
 *    class DataExported {
 *        use HasTenantEvents;
 *
 *        public function handle() {
 *            if ($this->shouldProcessForTenant()) {
 *                // Process tenant-specific logic
 *            }
 *        }
 *    }
 *
 * 3. Tenant-scoped event listeners:
 *    class SendWelcomeEmail {
 *        use HasTenantEvents;
 *
 *        public function handle($event) {
 *            if ($this->isTenantEvent($event)) {
 *                // Send tenant-branded email
 *            }
 *        }
 *    }
 */
trait HasTenantEvents
{
    use BaseTenantAware;

    /**
     * Check if an event belongs to a tenant.
     * Useful for event listeners that need to verify tenant context.
     */
    protected function isTenantEvent($event): bool
    {
        if (method_exists($event, 'getCurrentTenant')) {
            return $event->getCurrentTenant() !== null;
        }

        if (property_exists($event, 'tenant_id')) {
            return $event->tenant_id !== null;
        }

        if (property_exists($event, 'tenant')) {
            return $event->tenant !== null;
        }

        return $this->hasTenantContext();
    }

    /**
     * Get tenant-specific event data for broadcasting or logging.
     */
    protected function getTenantEventData(): array
    {
        return $this->getTenantData();
    }

    /**
     * Get a tenant-specific event name prefix.
     * Useful for creating tenant-scoped event names.
     */
    protected function getTenantEventPrefix(): string
    {
        return $this->getTenantPrefix();
    }

    /**
     * Create a tenant-scoped event name.
     */
    protected function getTenantEventName(string $eventName): string
    {
        return $this->getTenantScopedName($eventName);
    }
}
