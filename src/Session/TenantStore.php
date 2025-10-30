<?php

declare(strict_types=1);

namespace Quvel\Tenant\Session;

use Illuminate\Session\Store;

class TenantStore extends Store
{
    /**
     * Get the session name, dynamically reading from config.
     */
    public function getName(): string
    {
        return config('session.cookie', $this->name);
    }
}
