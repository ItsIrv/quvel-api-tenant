<?php

namespace Quvel\Tenant\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Quvel\Tenant\Facades\TenantContext;
use SensitiveParameter;

class TenantDatabaseTokenRepository extends DatabaseTokenRepository
{
    /**
     * Determine if a token record exists and is valid.
     *
     * @param CanResetPasswordContract $user
     * @param  string  $token
     * @return bool
     */
    public function exists(CanResetPasswordContract $user, $token): bool
    {
        $record = (array) $this->getToken($user->getEmailForPasswordReset());

        return $record &&
               !$this->tokenExpired($record['created_at']) &&
               $this->hasher->check($token, $record['token']);
    }

    /**
     * Delete all existing reset tokens from the database.
     *
     * @param CanResetPasswordContract $user
     * @return int
     */
    protected function deleteExisting(CanResetPasswordContract $user): int
    {
        $query = $this->getTable()->where('email', $user->getEmailForPasswordReset());

        // Add tenant scoping if enabled and tenant context is available
        if (config('tenant.password_reset_tokens.auto_tenant_id', false)) {
            $tenant = TenantContext::current();
            if ($tenant) {
                $query = $query->where('tenant_id', $tenant->id);
            }
        }

        return $query->delete();
    }

    /**
     * Build the record payload for the table.
     *
     * @param  string  $email
     * @param  string  $token
     * @return array
     */
    protected function getPayload($email, #[SensitiveParameter] $token): array
    {
        $payload = parent::getPayload($email, $token);

        if (config('tenant.password_reset_tokens.auto_tenant_id', false)) {
            $tenant = TenantContext::current();
            if ($tenant) {
                $payload['tenant_id'] = $tenant->id;
            }
        }

        return $payload;
    }

    /**
     * Find a token record by email address.
     *
     * @param string $email
     * @return object
     */
    protected function getToken(string $email): object
    {
        $query = $this->getTable()->where('email', $email);

        if (config('tenant.password_reset_tokens.auto_tenant_id', false)) {
            $tenant = TenantContext::current();
            if ($tenant) {
                $query = $query->where('tenant_id', $tenant->id);
            }
        }

        return $query->first();
    }
}
