<?php

namespace Quvel\Tenant\Cache;

use Exception;
use Illuminate\Cache\DatabaseLock;
use Quvel\Tenant\Facades\TenantContext;

class TenantDatabaseLock extends DatabaseLock
{
    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function acquire(): bool
    {
        $record = [
            'key' => $this->name,
            'owner' => $this->owner,
            'expiration' => $this->expiresAt(),
        ];

        if (config('tenant.cache.auto_tenant_id', false)) {
            $tenant = TenantContext::current();

            if ($tenant) {
                $record['tenant_id'] = $tenant->id;
            }
        }

        try {
            $this->connection->table($this->table)->insert($record);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release(): bool
    {
        if ($this->isOwnedByCurrentProcess()) {
            $query = $this->connection->table($this->table)
                ->where('key', $this->name)
                ->where('owner', $this->owner);

            if (config('tenant.cache.auto_tenant_id', false)) {
                $tenant = TenantContext::current();
                if ($tenant) {
                    $query = $query->where('tenant_id', $tenant->id);
                }
            }

            return $query->delete();
        }

        return false;
    }
}