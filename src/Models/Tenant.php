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
 * @property bool $is_internal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property array $config
 * @property ?Tenant $parent
 */
class Tenant extends Model
{
    use HasFactory;
    use SoftDeletes;

    public function getTable()
    {
        return config('tenant.table_name', 'tenants');
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
        'is_internal' => 'boolean',
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
            ->with('parent')
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
     * Check if a config key exists (with parent inheritance).
     */
    public function hasConfig(string $key): bool
    {
        $value = data_get($this->config, $key);

        if ($value !== null) {
            return true;
        }

        if ($this->parent_id) {
            $parent = $this->parent;

            if ($parent) {
                return $parent->hasConfig($key);
            }
        }

        return false;
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
     * Loops through the visibility tree and pulls matching config values (more efficient).
     */
    public function getConfigByVisibility(ConfigVisibility $minVisibility): array
    {
        $config = $this->getResolvedConfig();
        $visibility = data_get($this->config, '__visibility', []);

        return $this->filterByVisibilityKeys($visibility, $config, $minVisibility);
    }

    /**
     * Recursively filter by looping through the visibility tree.
     * More efficient since we only check explicitly visible keys.
     */
    protected function filterByVisibilityKeys(array $visibility, array $config, ConfigVisibility $minVisibility): array
    {
        $filtered = [];

        foreach ($visibility as $key => $visValue) {
            if (is_array($visValue)) {
                $childConfig = $config[$key] ?? [];

                if (is_array($childConfig)) {
                    $filteredChild = $this->filterByVisibilityKeys($visValue, $childConfig, $minVisibility);

                    if (!empty($filteredChild)) {
                        $filtered[$key] = $filteredChild;
                    }
                }
            } else {
                $vis = ConfigVisibility::tryFrom($visValue) ?? ConfigVisibility::PRIVATE;

                if ($this->isVisibilityAllowed($vis, $minVisibility)) {
                    $filtered[$key] = $config[$key] ?? null;
                }
            }
        }

        return $filtered;
    }

    /**
     * Check if a value is a leaf (actual config value) vs a branch (nested structure).
     */
    protected function isLeafValue(mixed $value): bool
    {
        if (!is_array($value)) {
            return true;
        }

        if (empty($value)) {
            return true;
        }

        return array_is_list($value);
    }

    /**
     * Get public configuration (PUBLIC visibility only).
     */
    public function getPublicConfig(): array
    {
        return $this->getConfigByVisibility(ConfigVisibility::PUBLIC);
    }

    /**
     * Get protected configuration (PUBLIC and PROTECTED visibility).
     */
    public function getProtectedConfig(): array
    {
        return $this->getConfigByVisibility(ConfigVisibility::PROTECTED);
    }

    public function isInternal(): bool
    {
        return $this->is_internal;
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
