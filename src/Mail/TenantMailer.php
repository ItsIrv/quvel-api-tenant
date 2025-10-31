<?php

declare(strict_types=1);

namespace Quvel\Tenant\Mail;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\View\Factory;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Message;
use Quvel\Tenant\Models\Tenant;
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
        protected Tenant $tenant
    ) {
        parent::__construct($name, $views, $transport, $events);
    }

    /**
     * Create a new message instance.
     */
    protected function createMessage(): Message
    {
        $message = new Message(new Email());

        $fromAddress = $this->tenant->getConfig('mail.from.address');
        $fromName = $this->tenant->getConfig('mail.from.name');

        if ($fromAddress) {
            $message->from($fromAddress, $fromName);
        } elseif (!empty($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        $replyToAddress = $this->tenant->getConfig('mail.reply_to.address');
        $replyToName = $this->tenant->getConfig('mail.reply_to.name');

        if ($replyToAddress) {
            $message->replyTo($replyToAddress, $replyToName);
        } elseif (!empty($this->replyTo['address'])) {
            $message->replyTo($this->replyTo['address'], $this->replyTo['name']);
        }

        $returnPath = $this->tenant->getConfig('mail.return_path');

        if ($returnPath) {
            $message->returnPath($returnPath);
        } elseif (!empty($this->returnPath['address'])) {
            $message->returnPath($this->returnPath['address']);
        }

        return $message;
    }
}
