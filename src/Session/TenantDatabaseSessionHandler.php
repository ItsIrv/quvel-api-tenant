<?php

namespace Quvel\Tenant\Session;

use Illuminate\Session\DatabaseSessionHandler;
use Quvel\Tenant\Context\TenantContext;

class TenantDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        if (config('tenant.sessions.auto_tenant_id', false)) {
            $tenant = app(TenantContext::class)->current();
            if ($tenant) {
                $payload['tenant_id'] = $tenant->id;
            }
        }

        if (!$this->exists) {
            $this->read($sessionId);
        }

        if ($this->exists) {
            $this->performUpdate($sessionId, $payload);
        } else {
            $this->performInsert($sessionId, $payload);
        }

        return $this->exists = true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId): string
    {
        $session = $this->getQuery()->where('id', $sessionId);

        if (config('tenant.sessions.auto_tenant_id', false)) {
            $tenant = app(TenantContext::class)->current();
            if ($tenant) {
                $session = $session->where('tenant_id', $tenant->id);
            }
        }

        $session = $session->first();

        if ($this->expired($session)) {
            $this->exists = false;

            return '';
        }

        if (isset($session->payload)) {
            $this->exists = true;

            return base64_decode($session->payload);
        }

        return '';
    }
}