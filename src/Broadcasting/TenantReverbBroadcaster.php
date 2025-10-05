<?php

declare(strict_types=1);

namespace Quvel\Tenant\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\ReverseProxyBroadcaster;
use Quvel\Tenant\Context\TenantContext;

/**
 * Reverb broadcaster that automatically prefixes channels with tenant identifiers.
 */
class TenantReverbBroadcaster extends ReverseProxyBroadcaster
{
    public function __construct(
        array $config,
        protected TenantContext $tenantContext
    ) {
        parent::__construct($config);
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