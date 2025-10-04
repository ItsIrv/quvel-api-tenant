<?php

declare(strict_types=1);

namespace Quvel\Tenant\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Quvel\Tenant\Database\Factories\TenantFactory;
use Quvel\Tenant\Enums\ConfigVisibility;

/**
 * Tenant model - simplified and clean.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $identifier
 * @property int|null $parent_id
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property array $config
 * @property ?Tenant $parent
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('tenant.table_name', 'tenants'));
    }

    protected $fillable = [
        'public_id',
        'name',
        'identifier',
        'parent_id',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    /**
     * Parent tenant relationship.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Child tenants relationship.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Find an active tenant by identifier.
     */
    public static function findByIdentifier(string $identifier): ?static
    {
        return static::where('identifier', $identifier)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get a config value by key with parent inheritance.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->config, $key);

        if ($value === null && $this->parent_id) {
            $parent = $this->parent;

            if ($parent) {
                return $parent->getConfig($key, $default);
            }
        }

        return $value ?? $default;
    }

    /**
     * Get merged configuration with parent inheritance.
     */
    public function getMergedConfig(): array
    {
        $config = $this->config ?? [];

        if ($this->parent_id) {
            $parent = $this->parent;

            if ($parent) {
                $parentConfig = $parent->getMergedConfig();
                $config = array_merge($parentConfig, $config);
            }
        }

        return $config;
    }

    /**
     * Set a config value by key.
     */
    public function setConfig(string $key, mixed $value): self
    {
        $config = $this->config ?? [];

        data_set($config, $key, $value);

        $this->config = $config;

        return $this;
    }

    /**
     * Check if config key exists.
     */
    public function hasConfig(string $key): bool
    {
        return data_get($this->config, $key) !== null;
    }

    /**
     * Remove a config key.
     */
    public function forgetConfig(string $key): self
    {
        $config = $this->config ?? [];

        data_forget($config, $key);

        $this->config = $config;

        return $this;
    }

    /**
     * Merge config values.
     */
    public function mergeConfig(array $values): self
    {
        $this->config = array_merge($this->config ?? [], $values);

        return $this;
    }

    /**
     * Set the visibility level for a config key.
     */
    public function setConfigVisibility(string $key, ConfigVisibility|string $visibility): self
    {
        $config = $this->config ?? [];

        $visibilityValue = $visibility instanceof ConfigVisibility ? $visibility->value : $visibility;

        data_set($config, '__visibility.' . $key, $visibilityValue);

        $this->config = $config;

        return $this;
    }

    /**
     * Get the visibility level for a config key.
     */
    public function getConfigVisibility(string $key): ConfigVisibility
    {
        $visibility = data_get($this->config, '__visibility.' . $key);

        return ConfigVisibility::tryFrom($visibility) ?? ConfigVisibility::PRIVATE;
    }

    /**
     * Get resolved config with full inheritance (excludes __visibility).
     */
    public function getResolvedConfig(): array
    {
        $config = $this->getMergedConfig();
        unset($config['__visibility']);

        return $config;
    }

    /**
     * Get config filtered by visibility level.
     */
    public function getConfigByVisibility(ConfigVisibility $minVisibility): array
    {
        $config = $this->getResolvedConfig();
        $visibility = data_get($this->config, '__visibility', []);
        $filtered = [];

        foreach ($config as $key => $value) {
            $keyVisibility = ConfigVisibility::tryFrom($visibility[$key] ?? null) ?? ConfigVisibility::PRIVATE;

            // Include if visibility level is >= minimum required
            if ($this->isVisibilityAllowed($keyVisibility, $minVisibility)) {
                data_set($filtered, $key, $value);
            }
        }

        return $filtered;
    }

    /**
     * Get public configuration (PUBLIC visibility only).
     */
    public function getPublicConfig(): array
    {
        return $this->getConfigByVisibility(ConfigVisibility::PUBLIC);
    }

    /**
     * Get protected configuration (PUBLIC + PROTECTED visibility).
     */
    public function getProtectedConfig(): array
    {
        return $this->getConfigByVisibility(ConfigVisibility::PROTECTED);
    }

    /**
     * Check if a visibility level meets the minimum requirement.
     */
    protected function isVisibilityAllowed(ConfigVisibility $actual, ConfigVisibility $required): bool
    {
        $levels = [
            ConfigVisibility::PRIVATE->value => 0,
            ConfigVisibility::PROTECTED->value => 1,
            ConfigVisibility::PUBLIC->value => 2,
        ];

        return $levels[$actual->value] >= $levels[$required->value];
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}