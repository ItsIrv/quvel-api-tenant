<?php

namespace Quvel\Tenant\Queue;

use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJobRecord;
use Quvel\Tenant\Facades\TenantContext;

class TenantDatabaseQueue extends DatabaseQueue
{
    /**
     * The tenant ID to filter jobs by (set by queue:work --tenant command).
     *
     * @var int|null
     */
    protected $filterByTenantId;

    /**
     * Set the tenant ID to filter jobs by.
     */
    public function setFilterTenantId(?int $tenantId): void
    {
        $this->filterByTenantId = $tenantId;
    }

    /**
     * Pop the next job off of the queue.
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        return $this->database->transaction(function () use ($queue) {
            $job = $this->getNextAvailableJob($queue);

            if ($job) {
                return $this->marshalJob($queue, $job);
            }

            return null;
        });
    }

    /**
     * Get the next available job for the queue.
     */
    protected function getNextAvailableJob($queue)
    {
        $job = $this->database->table($this->table)
            ->lock($this->getLockForPopping())
            ->where('queue', $this->getQueue($queue))
            ->where(function ($query): void {
                $this->isAvailable($query);
                $this->isReservedButExpired($query);
            })
            ->when($this->filterByTenantId !== null, fn ($query) => $query->where('tenant_id', $this->filterByTenantId))
            ->orderBy('id')
            ->first();

        return $job ? new DatabaseJobRecord($job) : null;
    }

    /**
     * Create an array to insert for the given job.
     */
    protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0): array
    {
        $record = [
            'queue' => $queue,
            'attempts' => $attempts,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => $this->currentTime(),
            'payload' => $payload,
        ];

        if (config('tenant.queue.auto_tenant_id', true) && TenantContext::needsTenantIdScope()) {
            $tenant = TenantContext::current();

            if ($tenant) {
                $record['tenant_id'] = $tenant->id;
            }
        }

        return $record;
    }
}
