<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

/**
 * Handles queue configuration for tenants.
 */
class QueueConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'queue.default',
            'queue.failed.table',
        ]);

        if ($this->tenant->hasConfig('queue.connections.database.table') || $this->tenant->hasConfig('queue.connections.redis.queue') || $this->tenant->hasConfig('queue.connections.sqs.queue')) {
            $this->configureQueueConnection();
        }
    }

    /**
     * Configure queue connection based on tenant settings.
     */
    protected function configureQueueConnection(): void
    {
        $connection = $this->tenant->getConfig('queue_connection');

        match ($connection) {
            'database' => $this->configureDatabaseQueue(),
            'redis' => $this->configureRedisQueue(),
            'sqs' => $this->configureSqsQueue(),
            default => null,
        };
    }

    /**
     * Configure database queue connection.
     */
    protected function configureDatabaseQueue(): void
    {
        $this->setIfExists('queue_database_table', 'queue.connections.database.table');

        $queueName = $this->tenant->getConfig('queue_name') ?? 'default';
        $retryAfter = $this->tenant->getConfig('queue_retry_after') ?? 90;

        $this->config->set('queue.connections.database.queue', $queueName);
        $this->config->set('queue.connections.database.retry_after', $retryAfter);
    }

    /**
     * Configure Redis queue connection.
     */
    protected function configureRedisQueue(): void
    {
        $queueName = $this->tenant->getConfig('queue_name') ?? 'default';
        $retryAfter = $this->tenant->getConfig('queue_retry_after') ?? 90;

        $this->config->set('queue.connections.redis.queue', $queueName);
        $this->config->set('queue.connections.redis.retry_after', $retryAfter);

        if ($this->tenant->hasConfig('redis_queue_database')) {
            $this->config->set('queue.connections.redis.connection', 'queue');
            $this->setIfExists('redis_queue_database', 'database.redis.queue.database');
        }
    }

    /**
     * Configure SQS queue connection for enterprise tenants.
     */
    protected function configureSqsQueue(): void
    {
        if (!$this->tenant->hasConfig('aws_sqs_queue')) {
            return;
        }

        $this->setIfExists('aws_sqs_queue', 'queue.connections.sqs.queue');

        $region = $this->tenant->getConfig('aws_sqs_region') ?? 'us-east-1';
        $this->config->set('queue.connections.sqs.region', $region);

        if ($this->tenant->hasConfig('aws_sqs_key')) {
            $this->setIfExists('aws_sqs_key', 'queue.connections.sqs.key');
            $this->setIfExists('aws_sqs_secret', 'queue.connections.sqs.secret');
        }
    }
}