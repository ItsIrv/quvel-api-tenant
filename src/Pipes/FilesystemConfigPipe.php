<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

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

        if ($this->tenant->hasConfig('filesystem_local_root')) {
            $this->setIfExists('filesystem_local_root', 'filesystems.disks.local.root');
        } else {
            $this->config->set('filesystems.disks.local.root', storage_path('app/tenants/' . $this->tenant->public_id));
        }

        if ($this->tenant->hasConfig('filesystem_public_root')) {
            $this->setIfExists('filesystem_public_root', 'filesystems.disks.public.root');
        } else {
            $this->config->set('filesystems.disks.public.root', storage_path('app/public/tenants/' . $this->tenant->public_id));
            $this->config->set('filesystems.disks.public.url', config('app.url') . '/storage/tenants/' . $this->tenant->public_id);
        }

        if ($this->tenant->hasConfig('aws_s3_bucket')) {
            if ($this->tenant->hasConfig('aws_s3_path_prefix')) {
                $this->setIfExists('aws_s3_path_prefix', 'filesystems.disks.s3.path_prefix');
            } else {
                $this->config->set('filesystems.disks.s3.path_prefix', 'tenants/' . $this->tenant->public_id);
            }
        }

        if (!$this->tenant->hasConfig('disable_temp_isolation')) {
            $this->config->set('filesystems.disks.temp', [
                'driver'     => 'local',
                'root'       => storage_path('app/temp/tenants/' . $this->tenant->public_id),
                'visibility' => 'private',
            ]);
        }
    }
}