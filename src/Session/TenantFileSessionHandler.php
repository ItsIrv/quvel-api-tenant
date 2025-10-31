<?php

declare(strict_types=1);

namespace Quvel\Tenant\Session;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Session\FileSessionHandler;
use RuntimeException;
use SessionHandlerInterface;
use Quvel\Tenant\Contracts\TenantContext;

/**
 * Tenant-aware file session handler that isolates sessions by tenant.
 */
class TenantFileSessionHandler implements SessionHandlerInterface
{
    protected FileSessionHandler $handler;

    /**
     * Create a new tenant file session handler instance.
     */
    public function __construct(
        protected string $basePath,
        protected int $minutes,
        protected TenantContext $tenantContext
    ) {
        $this->handler = new FileSessionHandler(
            app(Filesystem::class),
            $this->getTenantPath(),
            $minutes
        );
    }

    /**
     * Get the tenant-specific session file path.
     */
    protected function getTenantPath(): string
    {
        if (!config('tenant.sessions.auto_tenant_id', false)) {
            return $this->basePath;
        }

        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return $this->basePath;
        }

        $tenantPath = $this->basePath . DIRECTORY_SEPARATOR . 'tenant_' . $tenant->id;

        if (!mkdir($tenantPath, 0755, true) && !is_dir($tenantPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tenantPath));
        }

        return $tenantPath;
    }

    /**
     * Update handler with current tenant path.
     */
    protected function updateHandlerPath(): void
    {
        $this->handler = new FileSessionHandler(
            app(Filesystem::class),
            $this->getTenantPath(),
            $this->minutes
        );
    }

    /**
     * {@inheritdoc}
     */
    public function open($path, $name): bool
    {
        $this->updateHandlerPath();
        return $this->handler->open($path, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): bool
    {
        return $this->handler->close();
    }

    /**
     * {@inheritdoc}
     */
    public function read($id): string
    {
        $this->updateHandlerPath();
        return $this->handler->read($id);
    }

    /**
     * {@inheritdoc}
     */
    public function write($id, $data): bool
    {
        $this->updateHandlerPath();
        return $this->handler->write($id, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id): bool
    {
        $this->updateHandlerPath();
        return $this->handler->destroy($id);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($max_lifetime): int
    {
        $cleaned = 0;

        // Clean current tenant's sessions
        $this->updateHandlerPath();
        $cleaned += $this->handler->gc($max_lifetime);

        // If auto_tenant_id is enabled, clean up all tenant directories
        if (config('tenant.sessions.auto_tenant_id', false)) {
            $files = app(Filesystem::class);

            if ($files->isDirectory($this->basePath)) {
                foreach ($files->directories($this->basePath) as $tenantDir) {
                    if (str_starts_with(basename($tenantDir), 'tenant_')) {
                        $tenantHandler = new FileSessionHandler(
                            $files,
                            $tenantDir,
                            $this->minutes
                        );
                        $cleaned += $tenantHandler->gc($max_lifetime);
                    }
                }
            }
        }

        return $cleaned;
    }
}