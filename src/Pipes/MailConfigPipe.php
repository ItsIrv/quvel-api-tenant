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
            // Default mailer
            'mail.default',

            // From address settings
            'mail.from.address',
            'mail.from.name',

            // Reply-to settings
            'mail.reply_to.address',
            'mail.reply_to.name',

            // Return path
            'mail.return_path',

            // SMTP mailer settings
            'mail.mailers.smtp.host',
            'mail.mailers.smtp.port',
            'mail.mailers.smtp.username',
            'mail.mailers.smtp.password',
            'mail.mailers.smtp.encryption',
            'mail.mailers.smtp.timeout',
            'mail.mailers.smtp.local_domain',

            // AWS SES settings
            'mail.mailers.ses.key',
            'mail.mailers.ses.secret',
            'mail.mailers.ses.region',
            'mail.mailers.ses.configuration_set',

            // Mailgun settings
            'mail.mailers.mailgun.domain',
            'mail.mailers.mailgun.secret',
            'mail.mailers.mailgun.endpoint',

            // Postmark settings
            'mail.mailers.postmark.token',

            // Sendmail settings
            'mail.mailers.sendmail.path',

            // Log mailer settings
            'mail.mailers.log.channel',
        ]);
    }
}
