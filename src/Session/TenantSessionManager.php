<?php

declare(strict_types=1);

namespace Quvel\Tenant\Session;

use Illuminate\Session\SessionManager;

class TenantSessionManager extends SessionManager
{
    /**
     * Build the session instance.
     *
     * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement
     */
    protected function buildSession($handler): TenantEncryptedStore|TenantStore
    {
        return $this->config->get('session.encrypt')
            ? $this->buildEncryptedSession($handler)
            : new TenantStore(
                $this->config->get('session.cookie'),
                $handler,
                $id = null,
                $this->config->get('session.serialization', 'php')
            );
    }

    /**
     * Build the encrypted session instance.
     *
     * @psalm-suppress MoreSpecificReturnType, LessSpecificReturnStatement
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.Found
     */
    protected function buildEncryptedSession($handler)
    {
        return new TenantEncryptedStore(
            $this->config->get('session.cookie'),
            $handler,
            $this->container['encrypter'],
            $id = null,
            $this->config->get('session.serialization', 'php'),
        );
    }
}
