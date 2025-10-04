<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

/**
 * Handles broadcasting configuration for tenants.
 */
class BroadcastingConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'broadcasting.default',
            'broadcasting.connections.pusher.app_id',
            'broadcasting.connections.pusher.key',
            'broadcasting.connections.pusher.secret',
            'broadcasting.connections.pusher.options.cluster',
            'broadcasting.connections.pusher.options.scheme',
            'broadcasting.connections.pusher.options.host',
            'broadcasting.connections.pusher.options.port',
            'broadcasting.connections.reverb.app_id',
            'broadcasting.connections.reverb.key',
            'broadcasting.connections.reverb.secret',
            'broadcasting.connections.reverb.options.host',
            'broadcasting.connections.reverb.options.port',
            'broadcasting.connections.ably.key',
        ]);

        if ($this->config->get('broadcasting.default') === 'redis' || $this->tenant->hasConfig('broadcasting.connections.redis.prefix')) {
            $prefix = $this->tenant->getConfig('broadcasting.connections.redis.prefix') ?? 'tenant_' . $this->tenant->public_id;

            $this->config->set('broadcasting.connections.redis.prefix', $prefix);
        }
    }
}