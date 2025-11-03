<?php

declare(strict_types=1);

namespace Quvel\Tenant\Session;

use Illuminate\Session\SessionManager;

class TenantSessionManager extends SessionManager
{
    /**
     * Build the session instance.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Quvel\Tenant\Session\TenantStore
     */
    protected function buildSession($handler)
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
     * @param  \SessionHandlerInterface  $handler
     * @return \Quvel\Tenant\Session\TenantEncryptedStore
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
