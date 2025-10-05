<?php

declare(strict_types=1);

namespace Quvel\Tenant\Exceptions;

use RuntimeException;

/**
 * Exception thrown when attempting to save/update/delete a model with a different tenant_id
 * than the current context. This prevents cross-tenant data manipulation.
 */
class TenantMismatchException extends RuntimeException
{
    public function __construct(string $message = 'Attempted to modify a model belonging to a different tenant')
    {
        parent::__construct($message);
    }
}