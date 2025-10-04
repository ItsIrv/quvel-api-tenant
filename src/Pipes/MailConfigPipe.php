<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

/**
 * Handles mail configuration for tenants.
 */
class MailConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'mail.default',
            'mail.mailers.smtp.host',
            'mail.mailers.smtp.port',
            'mail.mailers.smtp.username',
            'mail.mailers.smtp.password',
            'mail.mailers.smtp.encryption',
            'mail.from.address',
            'mail.from.name',
        ]);
    }
}