<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Symfony\Component\HttpFoundation\Cookie;

class TenantAwareCsrfToken extends VerifyCsrfToken
{
    /**
     * Create a new XSRF-TOKEN cookie with a tenant-specific name.
     */
    protected function newCookie($request, $config)
    {
        $cookieName = config('session.xsrf_cookie', 'XSRF-TOKEN');

        return new Cookie(
            $cookieName,
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            $config['path'],
            $config['domain'],
            $config['secure'],
            false,
            false,
            $config['same_site'] ?? null,
            $config['partitioned'] ?? false
        );
    }
}
