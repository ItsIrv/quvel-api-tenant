<?php

declare(strict_types=1);

namespace Quvel\Tenant\Mail\Concerns;

use Quvel\Tenant\Concerns\TenantAware as BaseTenantAware;

/**
 * Trait for making mail classes optionally tenant-aware.
 *
 * This trait provides helper methods for working with tenant-specific mail settings
 * while preserving the standard Laravel mail interface.
 *
 * Usage Options:
 *
 * 1. Basic tenant from address:
 *    public function build() {
 *        return $this->from($this->tenantFrom())
 *                   ->view('emails.welcome');
 *    }
 *
 * 2. Tenant-specific mailer:
 *    public function build() {
 *        return $this->mailer($this->tenantMailer())
 *                   ->from($this->tenantFrom())
 *                   ->view('emails.welcome');
 *    }
 *
 * 3. Conditional tenant settings:
 *    public function build() {
 *        $mail = $this->view('emails.welcome');
 *
 *        if ($this->hasTenantContext()) {
 *            $mail->from($this->tenantFrom())
 *                 ->replyTo($this->tenantReplyTo());
 *        }
 *
 *        return $mail;
 *    }
 */
trait HasTenantMail
{
    use BaseTenantAware;

    /**
     * Get tenant-specific from address and name.
     */
    protected function tenantFrom(): array
    {
        $tenant = $this->getCurrentTenant();

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

    /**
     * Get tenant-specific mailer driver.
     */
    protected function tenantMailer(): string
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return config('mail.default');
        }

        return $tenant->getConfig('mail.default', config('mail.default'));
    }

    /**
     * Get tenant-specific reply-to address and name.
     */
    protected function tenantReplyTo(): ?array
    {
        $tenant = $this->getCurrentTenant();

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

    /**
     * Get tenant-specific return path.
     */
    protected function tenantReturnPath(): ?string
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return null;
        }

        return $tenant->getConfig('mail.return_path');
    }
}
