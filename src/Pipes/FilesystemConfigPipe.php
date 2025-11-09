<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;

/**
 * Handles filesystem configuration for tenants.
 */
class FilesystemConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'filesystem_default' => 'filesystems.default',
            'filesystem_cloud' => 'filesystems.cloud',
            'aws_s3_bucket' => 'filesystems.disks.s3.bucket',
            'aws_s3_key' => 'filesystems.disks.s3.key',
            'aws_s3_secret' => 'filesystems.disks.s3.secret',
            'aws_s3_region' => 'filesystems.disks.s3.region',
            'aws_s3_url' => 'filesystems.disks.s3.url',
        ]);

        $localRoot = $this->tenant->hasConfig('filesystem_local_root')
            ? $this->tenant->getConfig('filesystem_local_root')
            : $this->getLocalRootPath();

        $this->config->set('filesystems.disks.local.root', $localRoot);

        if ($this->tenant->hasConfig('filesystem_public_root')) {
            $this->setIfExists('filesystem_public_root', 'filesystems.disks.public.root');
        }

        if (!$this->tenant->hasConfig('filesystem_public_root')) {
            $this->config->set('filesystems.disks.public.root', $this->getPublicRootPath());
            $this->config->set('filesystems.disks.public.url', $this->getPublicUrl());
        }

        if ($this->tenant->hasConfig('aws_s3_bucket')) {
            $s3PathPrefix = $this->tenant->hasConfig('aws_s3_path_prefix')
                ? $this->tenant->getConfig('aws_s3_path_prefix')
                : $this->getS3PathPrefix();

            $this->config->set('filesystems.disks.s3.path_prefix', $s3PathPrefix);
        }

        if (!$this->tenant->hasConfig('disable_temp_isolation')) {
            $this->config->set('filesystems.disks.temp', [
                'driver' => 'local',
                'root' => $this->getTempRootPath(),
                'visibility' => 'private',
            ]);
        }
    }

    /**
     * Configure local root path generator.
     */
    public static function withLocalRootPath(Closure $callback): string
    {
        static::registerConfigurator('local_root_path', $callback);

        return static::class;
    }

    /**
     * Configure public root path generator.
     */
    public static function withPublicRootPath(Closure $callback): string
    {
        static::registerConfigurator('public_root_path', $callback);

        return static::class;
    }

    /**
     * Configure public URL generator.
     */
    public static function withPublicUrl(Closure $callback): string
    {
        static::registerConfigurator('public_url', $callback);

        return static::class;
    }

    /**
     * Configure S3 path prefix generator.
     */
    public static function withS3PathPrefix(Closure $callback): string
    {
        static::registerConfigurator('s3_path_prefix', $callback);

        return static::class;
    }

    /**
     * Configure temp root path generator.
     */
    public static function withTempRootPath(Closure $callback): string
    {
        static::registerConfigurator('temp_root_path', $callback);

        return static::class;
    }

    /**
     * Get local root path using configurator or default.
     */
    protected function getLocalRootPath(): string
    {
        return $this->applyConfigurator('local_root_path', storage_path('app/tenants/' . $this->tenant->public_id));
    }

    /**
     * Get public root path using configurator or default.
     */
    protected function getPublicRootPath(): string
    {
        return $this->applyConfigurator(
            'public_root_path',
            storage_path('app/public/tenants/' . $this->tenant->public_id)
        );
    }

    /**
     * Get public URL using configurator or default.
     */
    protected function getPublicUrl(): string
    {
        return $this->applyConfigurator(
            'public_url',
            config('app.url') . '/storage/tenants/' . $this->tenant->public_id
        );
    }

    /**
     * Get S3 path prefix using configurator or default.
     */
    protected function getS3PathPrefix(): string
    {
        return $this->applyConfigurator('s3_path_prefix', 'tenants/' . $this->tenant->public_id);
    }

    /**
     * Get temp root path using configurator or default.
     */
    protected function getTempRootPath(): string
    {
        return $this->applyConfigurator('temp_root_path', storage_path('app/temp/tenants/' . $this->tenant->public_id));
    }
}
