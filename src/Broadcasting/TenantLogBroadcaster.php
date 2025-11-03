<?php

declare(strict_types=1);

namespace Quvel\Tenant\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\LogBroadcaster;
use Quvel\Tenant\Contracts\TenantContext;

/**
 * Log broadcaster that automatically prefixes channels with tenant identifiers.
 */
class TenantLogBroadcaster extends LogBroadcaster
{
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        protected TenantContext $tenantContext
    ) {
        parent::__construct($logger);
    }

    /**
     * Broadcast the given event.
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $channels = $this->formatChannels($channels);

        parent::broadcast($channels, $event, $payload);
    }

    /**
     * Format the channel array with tenant prefixes using existing helper.
     */
    protected function formatChannels(array $channels): array
    {
        return array_map(static function ($channel) {
            $channelName = is_object($channel) ? $channel->name : $channel;
            $prefixedName = tenant_channel($channelName);

            if (str_starts_with($prefixedName, 'private-')) {
                $prefixedName = substr($prefixedName, 8);
            }

            return is_object($channel) ?
                new $channel($prefixedName) :
                $prefixedName;
        }, $channels);
    }
}
