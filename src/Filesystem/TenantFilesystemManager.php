<?php

declare(strict_types=1);

namespace Quvel\Tenant\Filesystem;

use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Contracts\Foundation\Application;
use Quvel\Tenant\Context\TenantContext;

/**
 * Tenant-aware filesystem manager.
 *
 * This manager extends Laravel's FilesystemManager to provide automatic tenant-aware
 * path scoping when enabled. It modifies filesystem operations to include tenant
 * context automatically.
 *
 * Features:
 * - Automatic path prefixing with tenant scope
 * - Tenant-specific disk configurations
 * - Preserves all standard Laravel filesystem functionality
 * - Configurable enable/disable via config
 */
class TenantFilesystemManager extends FilesystemManager
{
    protected TenantContext $tenantContext;

    public function __construct(Application $app, TenantContext $tenantContext)
    {
        parent::__construct($app);
        $this->tenantContext = $tenantContext;
    }

    /**
     * Get a filesystem instance with tenant awareness.
     */
    public function disk($name = null)
    {
        $disk = parent::disk($name);

        if (!config('tenant.filesystems.auto_tenant_scoping', false)) {
            return $disk;
        }

        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return $disk;
        }

        return new TenantFilesystemAdapter($disk, $tenant);
    }

    /**
     * Get the default driver name for the current tenant.
     */
    public function getDefaultDriver()
    {
        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return parent::getDefaultDriver();
        }

        $tenantDriver = $tenant->getConfig('filesystems.default');

        return $tenantDriver ?: parent::getDefaultDriver();
    }

    /**
     * Get the configuration for a disk, with tenant overrides.
     */
    protected function getConfig($name)
    {
        $config = parent::getConfig($name);
        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return $config;
        }

        // Apply tenant-specific disk configuration overrides
        $tenantConfig = $tenant->getConfig("filesystems.disks.$name", []);

        return array_merge($config, $tenantConfig);
    }
}