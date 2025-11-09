<?php

declare(strict_types=1);

namespace Quvel\Tenant\Builders;

use InvalidArgumentException;

/**
 * Builder for tenant configuration arrays.
 */
class TenantConfigurationBuilder
{
    protected array $config = [];

    /**
     * Create a new builder instance.
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set a config value using dot notation.
     */
    public function setConfig(string $key, string|bool|int $value): self
    {
        data_set($this->config, $key, $value);

        return $this;
    }

    /**
     * Configure Core pipe settings - matches what CoreConfigPipe actually handles.
     *
     * @param array<string, mixed> $config Configuration array using dot notation keys:
     *   - app.name (required)
     *   - app.url (required)
     *   - Any other config keys in dot notation (app.env, app.key, app.debug, etc.)
     */
    public function withCoreConfig(array $config): self
    {
        $required = ['app.name', 'app.url', 'frontend.url'];
        $missing = array_diff($required, array_keys($config));

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'Missing required core config keys: ' . implode(', ', $missing)
            );
        }

        foreach ($config as $key => $value) {
            if ($value !== null) {
                $this->setConfig($key, $value);
            }
        }

        return $this;
    }

    /**
     * Configure a dedicated database.
     * Based on DatabaseConfigPipe - changes database name only.
     */
    public function withDedicatedDatabase(string $database, string $connection = 'mysql'): self
    {
        return $this->setConfig(sprintf('database.connections.%s.database', $connection), $database);
    }

    /**
     * Configure an isolated database (completely separate database server).
     * Based on DatabaseConfigPipe - configures host, port, database, credentials.
     */
    public function withIsolatedDatabase(
        string $host,
        string $database,
        string $username,
        string $password,
        string $connection = 'mysql',
        ?int $port = null
    ): self {
        $this->setConfig(sprintf('database.connections.%s.host', $connection), $host)
            ->setConfig(sprintf('database.connections.%s.database', $connection), $database)
            ->setConfig(sprintf('database.connections.%s.username', $connection), $username)
            ->setConfig(sprintf('database.connections.%s.password', $connection), $password);

        if ($port) {
            $this->setConfig(sprintf('database.connections.%s.port', $connection), $port);
        }

        return $this;
    }

    /**
     * Add a custom configuration key-value pair.
     */
    public function withConfig(string $key, mixed $value): self
    {
        return $this->setConfig($key, $value);
    }

    /**
     * Merge additional configuration array.
     */
    public function withConfigArray(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * Get the built configuration array.
     */
    public function toArray(): array
    {
        return $this->config;
    }
}
