<?php

declare(strict_types=1);

namespace Quvel\Tenant\Mail;

use Illuminate\Mail\MailManager;
use Illuminate\Mail\Mailer;
use InvalidArgumentException;
use Quvel\Tenant\Context\TenantContext;

/**
 * Mail manager that automatically applies tenant context to mail drivers.
 */
class TenantMailManager extends MailManager
{
    public function __construct(
        $app,
        protected TenantContext $tenantContext
    ) {
        parent::__construct($app);
    }

    /**
     * Resolve the given mailer.
     *
     * Overridden to create TenantMailer when tenant has mail configuration overrides.
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Mailer [{$name}] is not defined.");
        }

        if (!config('tenant.mail.auto_tenant_mail', false)) {
            return parent::resolve($name);
        }

        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return parent::resolve($name);
        }

        $hasMailOverrides = $tenant->hasConfig('mail.from.address') ||
            $tenant->hasConfig('mail.reply_to.address') ||
            $tenant->hasConfig('mail.return_path');

        if (!$hasMailOverrides) {
            return parent::resolve($name);
        }

        $mailer = new TenantMailer(
            $name,
            $this->app['view'],
            $this->createSymfonyTransport($config),
            $this->app['events'],
            $tenant
        );

        if ($this->app->bound('queue')) {
            $mailer->setQueue($this->app['queue']);
        }

        return $mailer;
    }
}