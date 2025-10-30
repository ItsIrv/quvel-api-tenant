<?php

declare(strict_types=1);

namespace Quvel\Tenant\Session;

use Illuminate\Session\EncryptedStore;

class TenantEncryptedStore extends EncryptedStore
{
    /**
     * Get the session name, dynamically reading from config.
     */
    public function getName(): string
    {
        return config('session.cookie', $this->name);
    }
}
