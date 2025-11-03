<?php

declare(strict_types=1);

namespace Quvel\Tenant\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Pusher\Pusher;
use Quvel\Tenant\Contracts\TenantContext;

/**
 * Pusher broadcaster that automatically prefixes channels with tenant identifiers.
 */
class TenantPusherBroadcaster extends PusherBroadcaster
{
    public function __construct(
        protected $pusher,
        protected TenantContext $tenantContext
    ) {
        parent::__construct($pusher);
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

            return is_object($channel) ?
                new $channel($prefixedName) :
                $prefixedName;
        }, $channels);
    }
}
