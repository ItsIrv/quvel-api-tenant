<?php

declare(strict_types=1);

namespace Quvel\Tenant\Mail;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\MailManager;
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
     */
    protected function resolve($name): Mailer
    {
        $mailer = parent::resolve($name);

        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return $mailer;
        }

        $fromAddress = $tenant->getConfig('mail.from.address');
        $fromName = $tenant->getConfig('mail.from.name');

        if ($fromAddress) {
            $mailer->alwaysFrom($fromAddress, $fromName);
        }

        $replyToAddress = $tenant->getConfig('mail.reply_to.address');
        $replyToName = $tenant->getConfig('mail.reply_to.name');

        if ($replyToAddress) {
            $mailer->alwaysReplyTo($replyToAddress, $replyToName);
        }

        $returnPath = $tenant->getConfig('mail.return_path');

        if ($returnPath) {
            $mailer->alwaysReturnPath($returnPath);
        }

        return $mailer;
    }
}