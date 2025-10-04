<?php

declare(strict_types=1);

namespace Quvel\Tenant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Quvel\Tenant\Models\Tenant;

/**
 * Handles visibility filtering and metadata generation.
 *
 * @property Tenant $resource
 */
class TenantConfigResource extends JsonResource
{
    protected string $visibilityLevel = 'protected';

    /**
     * Set the visibility level for config filtering.
     */
    public function setVisibilityLevel(string $level): self
    {
        $this->visibilityLevel = $level;

        return $this;
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->public_id,
            'name' => $this->resource->name,
            'identifier' => $this->resource->identifier,
            'parent_id' => $this->resource->parent?->public_id,
            'config' => $this->getFilteredConfig(),
        ];
    }

    /**
     * Get filtered config based on visibility level.
     */
    protected function getFilteredConfig(): array
    {
        return match ($this->visibilityLevel) {
            'public' => $this->resource->getPublicConfig(),
            'protected' => $this->resource->getProtectedConfig(),
            default => [],
        };
    }
}