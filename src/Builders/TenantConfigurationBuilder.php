<?php

declare(strict_types=1);

namespace Quvel\Tenant\Builders;

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
     *
     * @param string $key
     * @param bool|int|string $value
     * @return TenantConfigurationBuilder
     */
    public function setConfig(string $key, string|bool|int $value): self
    {
        data_set($this->config, $key, $value);

        return $this;
    }

    /**
     * Configure Core pipe settings - matches what CoreConfigPipe actually handles.
     */
    public function withCoreConfig(
        string $appName,
        string $appUrl,
        ?string $frontendUrl = null,
        ?string $frontendInternalApiUrl = null,
        ?string $frontendCapacitorScheme = null,
        ?string $appEnv = null,
        ?string $appKey = null,
        ?bool $appDebug = null,
        ?string $appTimezone = null,
        ?string $appLocale = null,
        ?string $appFallbackLocale = null,
        ?string $pusherKey = null,
        ?string $pusherSecret = null,
        ?string $pusherAppId = null,
        ?string $pusherCluster = null,
        ?string $recaptchaSecretKey = null,
        ?string $recaptchaSiteKey = null
    ): self {
        $this->setConfig('app.name', $appName)
            ->setConfig('app.url', $appUrl);

        if ($frontendUrl) {
            $this->setConfig('frontend.url', $frontendUrl);
        }

        if ($frontendInternalApiUrl) {
            $this->setConfig('frontend.internal_api_url', $frontendInternalApiUrl);
        }

        if ($frontendCapacitorScheme) {
            $this->setConfig('frontend.capacitor_scheme', $frontendCapacitorScheme);
        }

        if ($appEnv) {
            $this->setConfig('app.env', $appEnv);
        }

        if ($appKey) {
            $this->setConfig('app.key', $appKey);
        }

        if ($appDebug !== null) {
            $this->setConfig('app.debug', $appDebug);
        }

        if ($appTimezone) {
            $this->setConfig('app.timezone', $appTimezone);
        }

        if ($appLocale) {
            $this->setConfig('app.locale', $appLocale);
        }

        if ($appFallbackLocale) {
            $this->setConfig('app.fallback_locale', $appFallbackLocale);
        }

        if ($pusherKey) {
            $this->setConfig('broadcasting.connections.pusher.key', $pusherKey);
        }

        if ($pusherSecret) {
            $this->setConfig('broadcasting.connections.pusher.secret', $pusherSecret);
        }

        if ($pusherAppId) {
            $this->setConfig('broadcasting.connections.pusher.app_id', $pusherAppId);
        }

        if ($pusherCluster) {
            $this->setConfig('broadcasting.connections.pusher.options.cluster', $pusherCluster);
        }

        if ($recaptchaSecretKey) {
            $this->setConfig('recaptcha_secret_key', $recaptchaSecretKey);
        }

        if ($recaptchaSiteKey) {
            $this->setConfig('recaptcha_site_key', $recaptchaSiteKey);
        }

        return $this;
    }

    /**
     * Configure dedicated database (different database name on same server).
     * Based on DatabaseConfigPipe - changes database name only.
     */
    public function withDedicatedDatabase(string $database, string $connection = 'mysql'): self
    {
        return $this->setConfig("database.connections.$connection.database", $database);
    }

    /**
     * Configure isolated database (completely separate database server).
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
        $this->setConfig("database.connections.$connection.host", $host)
            ->setConfig("database.connections.$connection.database", $database)
            ->setConfig("database.connections.$connection.username", $username)
            ->setConfig("database.connections.$connection.password", $password);

        if ($port) {
            $this->setConfig("database.connections.$connection.port", $port);
        }

        return $this;
    }

    /**
     * Add custom configuration key-value pair.
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
