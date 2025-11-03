<?php

declare(strict_types=1);

namespace Quvel\Tenant\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem;
use Quvel\Tenant\Models\Tenant;

/**
 * Tenant-aware filesystem adapter.
 *
 * This class wraps any filesystem to provide automatic tenant scoping
 * for file operations. It modifies file paths to include tenant context
 * while preserving the original filesystem's functionality.
 */
class TenantFilesystemAdapter implements Filesystem
{
    protected Filesystem $filesystem;
    protected Tenant $tenant;

    public function __construct(Filesystem $filesystem, Tenant $tenant)
    {
        $this->filesystem = $filesystem;
        $this->tenant = $tenant;
    }

    /**
     * Get tenant-scoped file path.
     */
    protected function getTenantPath(string $path): string
    {
        if ($this->isDiskAlreadyTenantScoped()) {
            return $path;
        }

        $tenantFolder = "tenant-{$this->tenant->public_id}";

        return $path ? "$tenantFolder/$path" : $tenantFolder;
    }

    /**
     * Check if the disk root contains the tenant's public_id
     */
    protected function isDiskAlreadyTenantScoped(): bool
    {
        $diskRoot = $this->filesystem->path('');

        return str_contains($diskRoot, "tenants/{$this->tenant->public_id}") ||
               str_contains($diskRoot, "tenants\\{$this->tenant->public_id}"); // Windows path support
    }

    /**
     * Get tenant-scoped file paths for array operations.
     */
    protected function getTenantPaths(array $paths): array
    {
        return array_map([$this, 'getTenantPath'], $paths);
    }

    /**
     * Determine if a file exists.
     */
    public function exists($path)
    {
        return $this->filesystem->exists($this->getTenantPath($path));
    }

    /**
     * Get the contents of a file.
     */
    public function get($path)
    {
        return $this->filesystem->get($this->getTenantPath($path));
    }

    /**
     * Get a resource to read the file.
     */
    public function readStream($path)
    {
        return $this->filesystem->readStream($this->getTenantPath($path));
    }

    /**
     * Write the contents of a file.
     */
    public function put($path, $contents, $options = [])
    {
        return $this->filesystem->put($this->getTenantPath($path), $contents, $options);
    }

    /**
     * Write a new file using a stream.
     */
    public function writeStream($path, $resource, array $options = [])
    {
        return $this->filesystem->writeStream($this->getTenantPath($path), $resource, $options);
    }

    /**
     * Get the visibility for the given path.
     */
    public function getVisibility($path)
    {
        return $this->filesystem->getVisibility($this->getTenantPath($path));
    }

    /**
     * Set the visibility for the given path.
     */
    public function setVisibility($path, $visibility)
    {
        return $this->filesystem->setVisibility($this->getTenantPath($path), $visibility);
    }

    /**
     * Prepend to a file.
     */
    public function prepend($path, $data)
    {
        return $this->filesystem->prepend($this->getTenantPath($path), $data);
    }

    /**
     * Append to a file.
     */
    public function append($path, $data)
    {
        return $this->filesystem->append($this->getTenantPath($path), $data);
    }

    /**
     * Delete the file at a given path.
     */
    public function delete($paths)
    {
        if (is_array($paths)) {
            return $this->filesystem->delete($this->getTenantPaths($paths));
        }

        return $this->filesystem->delete($this->getTenantPath($paths));
    }

    /**
     * Copy a file to a new location.
     */
    public function copy($from, $to)
    {
        return $this->filesystem->copy(
            $this->getTenantPath($from),
            $this->getTenantPath($to)
        );
    }

    /**
     * Move a file to a new location.
     */
    public function move($from, $to)
    {
        return $this->filesystem->move(
            $this->getTenantPath($from),
            $this->getTenantPath($to)
        );
    }

    /**
     * Get the file size of a given file.
     */
    public function size($path)
    {
        return $this->filesystem->size($this->getTenantPath($path));
    }

    /**
     * Get the file's last modification time.
     */
    public function lastModified($path)
    {
        return $this->filesystem->lastModified($this->getTenantPath($path));
    }

    /**
     * Get an array of all files in a directory.
     */
    public function files($directory = null, $recursive = false)
    {
        $tenantPath = $directory ? $this->getTenantPath($directory) : $this->getTenantPath('');

        $files = $this->filesystem->files($tenantPath, $recursive);

        $tenantPrefix = "tenant-{$this->tenant->public_id}/";
        $prefixLength = strlen($tenantPrefix);

        return array_map(static function ($file) use ($tenantPrefix, $prefixLength) {
            if (str_starts_with($file, $tenantPrefix)) {
                return substr($file, $prefixLength);
            }

            return $file;
        }, $files);
    }

    /**
     * Get all the files from the given directory (recursive).
     */
    public function allFiles($directory = null)
    {
        return $this->files($directory, true);
    }

    /**
     * Get all the directories within a given directory.
     */
    public function directories($directory = null, $recursive = false)
    {
        $tenantPath = $directory ? $this->getTenantPath($directory) : $this->getTenantPath('');

        $directories = $this->filesystem->directories($tenantPath, $recursive);

        $tenantPrefix = "tenant-{$this->tenant->public_id}/";
        $prefixLength = strlen($tenantPrefix);

        return array_map(static function ($dir) use ($tenantPrefix, $prefixLength) {
            if (str_starts_with($dir, $tenantPrefix)) {
                return substr($dir, $prefixLength);
            }

            return $dir;
        }, $directories);
    }

    /**
     * Get all (recursive) of the directories within a given directory.
     */
    public function allDirectories($directory = null)
    {
        return $this->directories($directory, true);
    }

    /**
     * Create a directory.
     */
    public function makeDirectory($path)
    {
        return $this->filesystem->makeDirectory($this->getTenantPath($path));
    }

    /**
     * Recursively delete a directory.
     */
    public function deleteDirectory($directory)
    {
        return $this->filesystem->deleteDirectory($this->getTenantPath($directory));
    }

    /**
     * Get the full path for the file at the given "short" path.
     */
    public function path($path)
    {
        return $this->filesystem->path($this->getTenantPath($path));
    }

    /**
     * Store the uploaded file on the disk.
     */
    public function putFile($path, $file = null, $options = [])
    {
        return $this->filesystem->putFile($this->getTenantPath($path), $file, $options);
    }

    /**
     * Store the uploaded file on the disk with a given name.
     */
    public function putFileAs($path, $file, $name = null, $options = [])
    {
        return $this->filesystem->putFileAs($this->getTenantPath($path), $file, $name, $options);
    }

    /**
     * Pass through any other method calls to the underlying filesystem.
     */
    public function __call($method, $parameters)
    {
        return $this->filesystem->{$method}(...$parameters);
    }
}
