<?php

namespace Quvel\Tenant\Queue;

use Illuminate\Queue\DatabaseQueue;
use Quvel\Tenant\Facades\TenantContext;

class TenantDatabaseQueue extends DatabaseQueue
{
    /**
     * Create an array to insert for the given job.
     *
     * @param  string|null  $queue
     * @param  string  $payload
     * @param  int  $availableAt
     * @param  int  $attempts
     * @return array
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
