<?php

declare(strict_types=1);

namespace Quvel\Tenant\Filesystem\Concerns;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Quvel\Tenant\Concerns\TenantAware as BaseTenantAware;
use RuntimeException;

/**
 * Trait for making filesystem operations optionally tenant-aware.
 *
 * This trait provides helper methods for working with tenant-scoped storage paths
 * while preserving the standard Laravel filesystem interface.
 *
 * Usage Options:
 *
 * 1. Basic tenant-scoped storage paths:
 *    Storage::disk('local')->put($this->tenantPath('documents/file.pdf'), $content);
 *    $content = Storage::disk('local')->get($this->tenantPath('documents/file.pdf'));
 *
 * 2. Multiple tenant-scoped paths:
 *    $paths = $this->tenantPaths(['uploads/', 'documents/', 'temp/']);
 *    foreach ($paths as $path) {
 *        Storage::disk('local')->makeDirectory($path);
 *    }
 *
 * 3. Conditional tenant scoping:
 *    $path = $this->hasTenantContext()
 *        ? $this->tenantPath('uploads/avatar.jpg')
 *        : 'global/avatar.jpg';
 *    Storage::put($path, $content);
 *
 * 4. Tenant-specific disk:
 *    $disk = Storage::disk($this->tenantDisk());
 *    $disk->put('file.txt', $content);
 */
trait HasTenantStorage
{
    use BaseTenantAware;

    /**
     * Get a tenant-scoped storage path.
     */
    protected function tenantPath(string $path = ''): string
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return $path;
        }

        $tenantFolder = "tenant-$tenant->public_id";

        return $path ? "$tenantFolder/$path" : $tenantFolder;
    }

    /**
     * Get multiple tenant-scoped storage paths.
     */
    protected function tenantPaths(array $paths): array
    {
        return array_map([$this, 'tenantPath'], $paths);
    }

    /**
     * Get the tenant-specific disk name.
     */
    protected function tenantDisk(): string
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return config('filesystems.default');
        }

        return $tenant->getConfig('filesystems.default', config('filesystems.default'));
    }

    /**
     * Get tenant-specific disk configuration.
     */
    protected function tenantDiskConfig(): array
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return [];
        }

        $diskName = $this->tenantDisk();
        $defaultConfig = config("filesystems.disks.$diskName", []);

        // Get tenant-specific overrides
        $tenantConfig = $tenant->getConfig("filesystems.disks.$diskName", []);

        return array_merge($defaultConfig, $tenantConfig);
    }

    /**
     * Get a tenant-scoped URL for a file.
     */
    protected function tenantUrl(string $path): string
    {
        return Storage::disk($this->tenantDisk())->url($this->tenantPath($path));
    }

    /**
     * Get a tenant-scoped temporary URL for a file.
     */
    protected function tenantTemporaryUrl(string $path, DateTimeInterface $expiration, array $options = []): string
    {
        $disk = Storage::disk($this->tenantDisk());

        if (!method_exists($disk, 'temporaryUrl')) {
            throw new RuntimeException("Disk [{$this->tenantDisk()}] does not support temporary URLs.");
        }

        return $disk->temporaryUrl($this->tenantPath($path), $expiration, $options);
    }

    /**
     * Check if a file exists in tenant storage.
     */
    protected function tenantExists(string $path): bool
    {
        return Storage::disk($this->tenantDisk())->exists($this->tenantPath($path));
    }

    /**
     * Get the size of a file in tenant storage.
     */
    protected function tenantSize(string $path): int
    {
        return Storage::disk($this->tenantDisk())->size($this->tenantPath($path));
    }

    /**
     * Get all files in a tenant directory.
     */
    protected function tenantFiles(string $directory = '', bool $recursive = false): array
    {
        $disk = Storage::disk($this->tenantDisk());
        $tenantPath = $this->tenantPath($directory);

        if ($recursive) {
            return $disk->allFiles($tenantPath);
        }

        return $disk->files($tenantPath);
    }

    /**
     * Get all directories in a tenant directory.
     */
    protected function tenantDirectories(string $directory = '', bool $recursive = false): array
    {
        $disk = Storage::disk($this->tenantDisk());
        $tenantPath = $this->tenantPath($directory);

        if ($recursive) {
            return $disk->allDirectories($tenantPath);
        }

        return $disk->directories($tenantPath);
    }

    /**
     * Delete a file or directory from tenant storage.
     */
    protected function tenantDelete(string|array $paths): bool
    {
        $disk = Storage::disk($this->tenantDisk());

        if (is_array($paths)) {
            $tenantPaths = array_map([$this, 'tenantPath'], $paths);

            return $disk->delete($tenantPaths);
        }

        return $disk->delete($this->tenantPath($paths));
    }

    /**
     * Copy a file within tenant storage.
     */
    protected function tenantCopy(string $from, string $to): bool
    {
        return Storage::disk($this->tenantDisk())->copy($this->tenantPath($from), $this->tenantPath($to));
    }

    /**
     * Move a file within tenant storage.
     */
    protected function tenantMove(string $from, string $to): bool
    {
        return Storage::disk($this->tenantDisk())->move($this->tenantPath($from), $this->tenantPath($to));
    }

    /**
     * Clean up all files for the current tenant.
     * Use with extreme caution - this will delete ALL tenant files.
     */
    protected function cleanupTenantStorage(): bool
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            return false;
        }

        $disk = Storage::disk($this->tenantDisk());
        $tenantFolder = "tenant-$tenant->public_id";

        if (!$disk->exists($tenantFolder)) {
            return true; // Nothing to clean up
        }

        return $disk->deleteDirectory($tenantFolder);
    }
}
