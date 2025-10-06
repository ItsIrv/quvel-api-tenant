<?php

namespace Quvel\Tenant\Queue;

use Illuminate\Bus\Batch;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Str;
use Quvel\Tenant\Facades\TenantContext;

class TenantDatabaseBatchRepository extends DatabaseBatchRepository
{
    /**
     * Store a new pending batch.
     *
     * @param PendingBatch $batch
     * @return Batch
     */
    public function store(PendingBatch $batch): Batch
    {
        $id = (string) Str::orderedUuid();

        $record = [
            'id' => $id,
            'name' => $batch->name,
            'total_jobs' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => $this->serialize($batch->options),
            'created_at' => time(),
            'cancelled_at' => null,
            'finished_at' => null,
        ];

        if (config('tenant.queue.auto_tenant_id', true)) {
            $tenant = TenantContext::current();
            if ($tenant) {
                $record['tenant_id'] = $tenant->id;
            }
        }

        $this->connection->table($this->table)->insert($record);

        return $this->find($id);
    }
}