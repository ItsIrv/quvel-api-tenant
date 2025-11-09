<?php

namespace Quvel\Tenant\Queue;

use Illuminate\Redis\RedisManager;
use Laravel\Horizon\RedisQueue as HorizonRedisQueue;
use Quvel\Tenant\Facades\TenantContext;

/**
 * @property string $default
 */
class TenantHorizonRedisQueue extends HorizonRedisQueue
{
    /**
     * The tenant ID to filter jobs by (set by queue:work --tenant command).
     *
     * @var int|null
     */
    protected $filterByTenantId;

    /**
     * Create a new TenantHorizonRedisQueue instance.
     */
    public function __construct(
        RedisManager $redis,
        string $default,
        string $connection,
        int $retryAfter = 60,
        ?int $blockFor = null
    ) {
        parent::__construct($redis, $default, $connection, $retryAfter, $blockFor);
    }

    /**
     * Set the tenant ID to filter jobs by.
     */
    public function setFilterTenantId(?int $tenantId): void
    {
        $this->filterByTenantId = $tenantId;
    }

    /**
     * Get the queue or return the default.
     *
     * Prefixes queue name with tenant ID when tenant context exists.
     *
     * @param  string|null  $queue
     * @return string
     */
    public function getQueue($queue)
    {
        $queue = $queue ?: $this->default;

        if ($this->filterByTenantId !== null) {
            return $this->getTenantQueueName($queue, $this->filterByTenantId);
        }

        if (config('tenant.queue.auto_tenant_id', true) && TenantContext::needsTenantIdScope()) {
            $tenant = TenantContext::current();

            if ($tenant) {
                return $this->getTenantQueueName($queue, $tenant->id);
            }
        }

        return $queue;
    }

    /**
     * Get the tenant-specific queue name.
     */
    protected function getTenantQueueName(string $queue, int $tenantId): string
    {
        return sprintf('tenant_%d_%s', $tenantId, $queue);
    }
}
