<?php

declare(strict_types=1);

namespace Quvel\Tenant\Traits;

use Illuminate\Database\Eloquent\Model;
use Quvel\Tenant\Models\Tenant;
use Quvel\Tenant\Scopes\TenantScope;
use Quvel\Tenant\Exceptions\TenantMismatchException;
use Quvel\Tenant\Events\TenantModelCreated;
use Quvel\Tenant\Events\TenantMismatchDetected;

/**
 * Automatically applies tenant scoping to models with security guards.
 * Combines HasTenant relationship with TenantScope filtering and cross-tenant protection.
 *
 * @mixin Model
 *
 * @property int|null $tenant_id The tenant ID this model belongs to
 * @property-read Tenant|null $tenant The tenant relationship
 *
 * This trait automatically:
 * - Adds a global TenantScope to filter queries by tenant_id
 * - Sets tenant_id during model creation (::creating event)
 * - Guards against cross-tenant save/update/delete operations
 * - Prevents changing tenant_id after model creation
 * - Adds 'tenant_id' to $fillable array for mass assignment
 * - Adds 'tenant_id' to $hidden array to hide from API responses
 * - Provides tenant relationship and helper methods via HasTenant trait
 * - Skips tenant_id logic for isolated databases (database-level isolation)
 *
 * Usage:
 * ```php
 * class Post extends Model
 * {
 *     use TenantScoped;
 * }
 *
 * // Queries are automatically scoped to current tenant
 * Post::all(); // Only posts for current tenant
 * Post::forAllTenants()->get(); // Bypass scoping
 * ```
 */
trait TenantScoped
{
    use HasTenant;

    /**
     * Boot the scoped trait.
     *
     * Automatically registers:
     * - TenantScope global scope for query filtering
     * - Creating event listener to auto-assign tenant_id
     * - Security guards for update/delete operations
     */
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(static function ($model) {
            if (!isset($model->tenant_id)) {
                $tenant = $model->getCurrentTenant();

                if ($tenant && $model->tenantUsesIsolatedDatabase($tenant)) {
                    TenantModelCreated::dispatch($model, $tenant);

                    return;
                }

                $model->tenant_id = $model->getCurrentTenantId();

                if ($tenant) {
                    TenantModelCreated::dispatch($model, $tenant);
                }
            }
        });

        static::updating(static function ($model) {
            $model->guardAgainstTenantMismatch();
        });

        static::deleting(static function ($model) {
            $model->guardAgainstTenantMismatch();
        });

        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive(static::class), true)) {
            static::restoring(static function ($model) {
                $model->guardAgainstTenantMismatch();
            });
        }
    }

    /**
     * Initialize the trait after model instantiation.
     *
     * Modifies model configuration:
     * - Adds 'tenant_id' to $fillable for mass assignment protection
     * - Adds 'tenant_id' to $hidden to exclude from API serialization
     */
    public function initializeTenantScoped(): void
    {
        if (config('tenant.scoping.auto_fillable', true) && !in_array('tenant_id', $this->getFillable(), true)) {
            $this->fillable[] = 'tenant_id';
        }

        if (config('tenant.scoping.auto_hidden', true) && !in_array('tenant_id', $this->getHidden(), true)) {
            $this->hidden[] = 'tenant_id';
        }
    }

    /**
     * Override save to add tenant safety guard.
     *
     * @throws TenantMismatchException
     */
    public function save(array $options = []): bool
    {
        $this->ensureTenantIdSet();
        $this->guardAgainstTenantMismatch();

        return parent::save($options);
    }

    /**
     * Override delete to add tenant safety guard.
     *
     * @throws TenantMismatchException
     */
    public function delete(): ?bool
    {
        $this->guardAgainstTenantMismatch();

        return parent::delete();
    }

    /**
     * Override update to add tenant safety guard.
     *
     * @throws TenantMismatchException
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        $this->guardAgainstTenantMismatch();

        return parent::update($attributes, $options);
    }

    /**
     * Override fill to prevent changing tenant_id after creation.
     */
    public function fill(array $attributes): static
    {
        if ($this->exists && array_key_exists(
                'tenant_id',
                $attributes
            ) && $attributes['tenant_id'] !== $this->tenant_id) {
                throw new TenantMismatchException(
                    'Cannot change tenant_id after model creation'
                );
            }

        return parent::fill($attributes);
    }

    /**
     * Ensure tenant_id is set on the model.
     */
    protected function ensureTenantIdSet(): void
    {
        if (!isset($this->tenant_id) && !tenant_bypassed()) {
            $tenant = $this->getCurrentTenant();

            // Skip tenant_id assignment for isolated databases
            if ($tenant && $this->tenantUsesIsolatedDatabase($tenant)) {
                return;
            }

            $this->tenant_id = $this->getCurrentTenantId();
        }
    }

    /**
     * Guard against cross-tenant operations.
     *
     * @throws TenantMismatchException
     */
    protected function guardAgainstTenantMismatch(): void
    {
        if (tenant_bypassed()) {
            return;
        }

        $tenant = $this->getCurrentTenant();

        // Skip tenant_id checks for isolated databases
        if ($tenant && $this->tenantUsesIsolatedDatabase($tenant)) {
            return;
        }

        $currentTenantId = $this->getCurrentTenantId();
        $modelTenantId = $this->tenant_id;

        if ($modelTenantId !== null && $modelTenantId !== $currentTenantId) {
            TenantMismatchDetected::dispatch(
                get_class($this),
                $modelTenantId,
                $currentTenantId,
                'modify'
            );

            throw new TenantMismatchException(
                sprintf(
                    'Cross-tenant operation blocked: %s (tenant_id: %s) cannot be modified from tenant %s context',
                    get_class($this),
                    $modelTenantId,
                    $currentTenantId
                )
            );
        }

        if ($modelTenantId === null && !$this->exists) {
            $this->tenant_id = $currentTenantId;
        }
    }
}