<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

/**
 * Scopes Quvel Core services with tenant-specific configuration.
 * Only executes if Core package is available.
 */
class CoreServicesScopingPipe extends BasePipe
{
    public function apply(): void
    {
        if (!$this->corePackageAvailable()) {
            return;
        }

        $this->scopeRedirectService();
        $this->scopeInternalRequestValidator();
        $this->scopeCaptchaService();
    }

    /**
     * Check if Core package classes are available.
     */
    protected function corePackageAvailable(): bool
    {
        return class_exists(\Quvel\Core\Contracts\RedirectServiceInterface::class);
    }

    /**
     * Scope RedirectService with tenant frontend configuration.
     */
    protected function scopeRedirectService(): void
    {
        if (!app()->bound(\Quvel\Core\Contracts\RedirectServiceInterface::class)) {
            return;
        }

        app()->extend(\Quvel\Core\Contracts\RedirectServiceInterface::class, function ($service) {
            if ($this->tenant->hasConfig('frontend.url')) {
                $service->setBaseUrl($this->tenant->getConfig('frontend.url'));
            }

            if ($this->tenant->hasConfig('frontend.capacitor_scheme')) {
                $service->setCustomScheme($this->tenant->getConfig('frontend.capacitor_scheme'));
            }

            return $service;
        });
    }

    /**
     * Scope InternalRequestValidator with tenant security configuration.
     */
    protected function scopeInternalRequestValidator(): void
    {
        $this->setMany([
            'security.internal_requests.trusted_ips' => 'quvel-core.security.internal_requests.trusted_ips',
            'security.internal_requests.api_key' => 'quvel-core.security.internal_requests.api_key',
        ]);
    }

    /**
     * Scope CaptchaService with tenant captcha configuration.
     */
    protected function scopeCaptchaService(): void
    {
        $this->setMany([
            'captcha.recaptcha.site_key' => 'quvel-core.captcha.recaptcha.site_key',
            'captcha.recaptcha.secret_key' => 'quvel-core.captcha.recaptcha.secret_key',
        ]);
    }
}