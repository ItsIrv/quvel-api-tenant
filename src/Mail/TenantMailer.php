<?php

declare(strict_types=1);

namespace Quvel\Tenant\Mail;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Quvel\Tenant\Context\TenantContext;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * Tenant-aware mailer.
 *
 * This class extends Laravel's Mailer to provide tenant-specific from/replyTo/returnPath
 * configuration that is read at send-time rather than cached at construction time.
 */
class TenantMailer extends Mailer
{
    public function __construct(
        string $name,
        Factory $views,
        TransportInterface $transport,
        ?Dispatcher $events,
        protected TenantContext $tenantContext
    ) {
        parent::__construct($name, $views, $transport, $events);
    }

    /**
     * Create a new message instance.
     *
     * Overridden to apply tenant-specific from/replyTo/returnPath at message creation time.
     */
    protected function createMessage(): Message
    {
        $message = new Message(new Email());

        $tenant = $this->tenantContext->current();

        if ($tenant) {
            $fromAddress = $tenant->getConfig('mail.from.address');
            $fromName = $tenant->getConfig('mail.from.name');

            if ($fromAddress) {
                $message->from($fromAddress, $fromName);
            } elseif (!empty($this->from['address'])) {
                $message->from($this->from['address'], $this->from['name']);
            }

            $replyToAddress = $tenant->getConfig('mail.reply_to.address');
            $replyToName = $tenant->getConfig('mail.reply_to.name');

            if ($replyToAddress) {
                $message->replyTo($replyToAddress, $replyToName);
            } elseif (!empty($this->replyTo['address'])) {
                $message->replyTo($this->replyTo['address'], $this->replyTo['name']);
            }

            $returnPath = $tenant->getConfig('mail.return_path');

            if ($returnPath) {
                $message->returnPath($returnPath);
            } elseif (!empty($this->returnPath['address'])) {
                $message->returnPath($this->returnPath['address']);
            }
        } else {
            if (!empty($this->from['address'])) {
                $message->from($this->from['address'], $this->from['name']);
            }

            if (!empty($this->replyTo['address'])) {
                $message->replyTo($this->replyTo['address'], $this->replyTo['name']);
            }

            if (!empty($this->returnPath['address'])) {
                $message->returnPath($this->returnPath['address']);
            }
        }

        return $message;
    }
}
