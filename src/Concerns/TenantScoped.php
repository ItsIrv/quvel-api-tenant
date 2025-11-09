<?php

declare(strict_types=1);

namespace Quvel\Tenant\Concerns;

use Illuminate\Database\Eloquent\Model;
use Quvel\Tenant\Exceptions\TenantMismatchException;
use Quvel\Tenant\Scopes\TenantScope;

/**
 * Tenant scoping with automatic isolation detection.
 *
 * @mixin Model
 *
 * @property int|null $tenant_id The tenant ID this model belongs to
 * @property-read mixed $tenant The tenant relationship
 *
 * Usage:
 * ```php
 * class Post extends Model
 * {
 *     use TenantScoped;
 * }
 *
 * // Automatic tenant scoping
 * Post::all(); // Only current tenant's posts
 * Post::forAllTenants()->get(); // Administrative access
 * ```
 */
trait TenantScoped
{
    use HasTenant;
    use HandlesTenantModels;

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

        static::creating(static function ($model): void {
            if (!isset($model->tenant_id)) {
                if (!TenantContext::needsTenantIdScope()) {
                    return;
                }

                $model->tenant_id = $model->getCurrentTenantId();
            }
        });

        static::updating(static function ($model): void {
            $model->guardAgainstTenantMismatch();
        });

        static::deleting(static function ($model): void {
            $model->guardAgainstTenantMismatch();
        });

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restoring(static function ($model): void {
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
        if (
            $this->exists && array_key_exists(
                'tenant_id',
                $attributes
            ) && $attributes['tenant_id'] !== $this->tenant_id
        ) {
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
            if (!TenantContext::needsTenantIdScope()) {
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
        $this->validateTenantMatch($this);

        if ($this->tenant_id === null && !$this->exists) {
            $this->tenant_id = $this->getCurrentTenantId();
        }
    }
}
