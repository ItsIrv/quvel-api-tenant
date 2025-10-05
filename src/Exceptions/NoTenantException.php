<?php

declare(strict_types=1);

namespace Quvel\Tenant\Exceptions;

use RuntimeException;

/**
 * Exception thrown when no tenant is available in context and operation is not bypassed.
 * This indicates a security issue where tenant-scoped operations are attempted without proper context.
 */
class NoTenantException extends RuntimeException
{
    public function __construct(string $message = 'No tenant in context and operation is not bypassed')
    {
        parent::__construct($message);
    }
}