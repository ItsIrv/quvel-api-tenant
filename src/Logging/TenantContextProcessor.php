<?php

declare(strict_types=1);

namespace Quvel\Tenant\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Quvel\Tenant\Context\TenantContext;

class TenantContextProcessor implements ProcessorInterface
{
    /**
     * Create a new tenant context processor.
     */
    public function __construct(
        /**
         * The tenant context instance.
         */
        protected TenantContext $tenantContext
    ) {
    }

    /**
     * Add tenant information to the log record.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $tenant = $this->tenantContext->current();

        if (!$tenant) {
            return $record;
        }

        // Add tenant_id to extra context if not already present
        $extra = $record->extra;

        if (!isset($extra['tenant_id'])) {
            $extra['tenant_id'] = $tenant->id;
            $extra['tenant_public_id'] = $tenant->public_id;
            $extra['tenant_identifier'] = $tenant->identifier;
        }

        return $record->with(extra: $extra);
    }
}
