<?php

declare(strict_types=1);

namespace Quvel\Tenant\Mail\Concerns;

use Quvel\Tenant\Context\TenantContext;

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
 *        if ($this->shouldUseTenantMail()) {
 *            $mail->from($this->tenantFrom())
 *                 ->replyTo($this->tenantReplyTo());
 *        }
 *
 *        return $mail;
 *    }
 */
trait TenantAware
{
    /**
     * Get tenant-specific from address and name.
     *
     * @return array
     */
    protected function tenantFrom(): array
    {
        $tenant = app(TenantContext::class)->current();

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
     *
     * @return string
     */
    protected function tenantMailer(): string
    {
        $tenant = app(TenantContext::class)->current();

        if (!$tenant) {
            return config('mail.default');
        }

        return $tenant->getConfig('mail.default', config('mail.default'));
    }

    /**
     * Get tenant-specific reply-to address and name.
     *
     * @return array|null
     */
    protected function tenantReplyTo(): ?array
    {
        $tenant = app(TenantContext::class)->current();

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
     *
     * @return string|null
     */
    protected function tenantReturnPath(): ?string
    {
        $tenant = app(TenantContext::class)->current();

        if (!$tenant) {
            return null;
        }

        return $tenant->getConfig('mail.return_path');
    }

    /**
     * Check if the current mail should use tenant-specific settings.
     * Override this method to implement custom logic.
     *
     * @return bool
     */
    protected function shouldUseTenantMail(): bool
    {
        return tenant() !== null;
    }
}