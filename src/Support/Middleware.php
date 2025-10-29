<?php

declare(strict_types=1);

namespace Quvel\Tenant\Support;

use Illuminate\Foundation\Configuration\Middleware as MiddlewareConfiguration;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Quvel\Tenant\Http\Middleware\TenantAwareCsrfToken;
use Quvel\Tenant\Http\Middleware\TenantAwareStartSession;

class Middleware
{
    /**
     * Configure tenant-aware middleware replacements.
     *
     * Call this from bootstrap/app.php:
     *
     * @example
     * use Quvel\Tenant\Support\Middleware as TenantMiddleware;
     *
     * ->withMiddleware(function (Middleware $middleware): void {
     *     TenantMiddleware::configure($middleware);
     * })
     */
    public static function configure(MiddlewareConfiguration $middleware): void
    {
        $middleware->web(replace: [
            ValidateCsrfToken::class => TenantAwareCsrfToken::class,
            StartSession::class => TenantAwareStartSession::class,
        ]);
    }
}
