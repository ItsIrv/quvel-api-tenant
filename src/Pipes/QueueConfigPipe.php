<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;

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

        $queueName = $this->tenant->getConfig('queue_name') ?? $this->getDefaultQueueName();
        $retryAfter = $this->tenant->getConfig('queue_retry_after') ?? $this->getDefaultRetryAfter();

        $this->config->set('queue.connections.database.queue', $queueName);
        $this->config->set('queue.connections.database.retry_after', $retryAfter);
    }

    /**
     * Configure Redis queue connection.
     */
    protected function configureRedisQueue(): void
    {
        $queueName = $this->tenant->getConfig('queue_name') ?? $this->getDefaultQueueName();
        $retryAfter = $this->tenant->getConfig('queue_retry_after') ?? $this->getDefaultRetryAfter();

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

        $region = $this->tenant->getConfig('aws_sqs_region') ?? $this->getDefaultSqsRegion();
        $this->config->set('queue.connections.sqs.region', $region);

        if ($this->tenant->hasConfig('aws_sqs_key')) {
            $this->setIfExists('aws_sqs_key', 'queue.connections.sqs.key');
            $this->setIfExists('aws_sqs_secret', 'queue.connections.sqs.secret');
        }
    }

    /**
     * Configure default queue name.
     */
    public static function withDefaultQueueName(Closure $callback): string
    {
        static::registerConfigurator('default_queue_name', $callback);

        return static::class;
    }

    /**
     * Configure default retry after seconds.
     */
    public static function withDefaultRetryAfter(Closure $callback): string
    {
        static::registerConfigurator('default_retry_after', $callback);

        return static::class;
    }

    /**
     * Configure default SQS region.
     */
    public static function withDefaultSqsRegion(Closure $callback): string
    {
        static::registerConfigurator('default_sqs_region', $callback);

        return static::class;
    }

    /**
     * Get default queue name using configurator or default.
     */
    protected function getDefaultQueueName(): string
    {
        return $this->applyConfigurator('default_queue_name', 'default');
    }

    /**
     * Get default retry after using configurator or default.
     */
    protected function getDefaultRetryAfter(): int
    {
        $result = $this->applyConfigurator('default_retry_after', '90');

        return is_numeric($result) ? (int) $result : 90;
    }

    /**
     * Get default SQS region using configurator or default.
     */
    protected function getDefaultSqsRegion(): string
    {
        return $this->applyConfigurator('default_sqs_region', 'us-east-1');
    }
}