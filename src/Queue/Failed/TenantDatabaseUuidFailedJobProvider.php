<?php

namespace Quvel\Tenant\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Carbon;
use Quvel\Tenant\Facades\TenantContext;
use Throwable;

class TenantDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @param  Throwable  $exception
     * @return int
     */
    public function log($connection, $queue, $payload, $exception): int
    {
        $failed_at = Carbon::now();

        $record = [
            'uuid' => (string) str()->uuid(),
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'exception' => (string) $exception,
            'failed_at' => $failed_at,
        ];

        if (config('tenant.queue.auto_tenant_id', true)) {
            $tenant = TenantContext::current();
            if ($tenant) {
                $record['tenant_id'] = $tenant->id;
            }
        }

        return $this->getTable()->insertGetId($record);
    }
}
