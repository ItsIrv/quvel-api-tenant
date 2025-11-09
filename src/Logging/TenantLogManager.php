<?php

declare(strict_types=1);

namespace Quvel\Tenant\Logging;

use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Monolog\Logger as Monolog;
use Monolog\Processor\ProcessorInterface;
use Quvel\Tenant\Context\TenantContext;
use RuntimeException;

class TenantLogManager extends LogManager
{
    /**
     * The tenant context instance.
     */
    protected TenantContext $tenantContext;

    /**
     * Create a new Log manager instance.
     */
    public function __construct($app)
    {
        parent::__construct($app);

        $this->tenantContext = $app->make(TenantContext::class);
    }

    /**
     * Prepare the logger for the configured channel.
     *
     * Adds tenant context processor to all loggers.
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed
     */
    protected function tap($name, Logger $logger)
    {
        if (config('tenant.logging.auto_tenant_context', true)) {
            foreach ($this->getHandlers($logger) as $handler) {
                $handler->pushProcessor($this->getTenantContextProcessor());
            }
        }

        return $logger;
    }

    /**
     * Get the Monolog handler from the logger.
     */
    protected function getHandlers(\Psr\Log\LoggerInterface $logger): array
    {
        return $logger instanceof Monolog ? $logger->getHandlers() : [];
    }

    /**
     * Get the tenant context processor.
     */
    protected function getTenantContextProcessor(): ProcessorInterface
    {
        return new TenantContextProcessor($this->tenantContext);
    }

    /**
     * Parse the driver configuration and override paths for tenant isolation.
     */
    protected function configurationFor($name)
    {
        $config = parent::configurationFor($name);

        if (!isset($config['driver'])) {
            return $config;
        }

        if (in_array($config['driver'], ['single', 'daily', 'stack'], true) && isset($config['path'])) {
            $config['path'] = $this->getTenantLogPath($config['path']);
        }

        return $config;
    }

    /**
     * Get the tenant-specific log path based on an isolation strategy.
     */
    protected function getTenantLogPath(string $path): string
    {
        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return $path;
        }

        $strategy = config('tenant.logging.isolation_strategy', 'prefix');

        return match ($strategy) {
            'prefix' => $this->getPrefixedPath($path, $tenant->public_id),
            'directory' => $this->getDirectoryPath($path, $tenant->public_id),
            'shared' => $path,
            default => $path,
        };
    }

    /**
     * Get prefixed log path (e.g., {public_id}.laravel.log).
     */
    protected function getPrefixedPath(string $path, string $tenantPublicId): string
    {
        $dir = dirname($path);
        $filename = basename($path);

        return $dir . '/' . $tenantPublicId . '.' . $filename;
    }

    /**
     * Get directory-based log path (e.g., tenants/tenant-id/laravel.log).
     */
    protected function getDirectoryPath(string $path, string $tenantPublicId): string
    {
        $dir = dirname($path);
        $filename = basename($path);

        $tenantDir = $dir . '/tenants/' . $tenantPublicId;

        if (
            !is_dir($tenantDir)
            && !mkdir($tenantDir, 0755, true)
            && !is_dir($tenantDir)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tenantDir));
        }

        return $tenantDir . '/' . $filename;
    }
}
