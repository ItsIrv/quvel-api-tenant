<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions\Admin;

trait NormalizesVisibility
{
    /**
     * Normalize visibility structure - handles both flat and nested formats.
     * Flat: {"app.url": "PUBLIC"} -> Nested: {"app": {"url": "PUBLIC"}}
     */
    protected function normalizeVisibility(array $visibility): array
    {
        if (is_array($visibility[0] ?? null)) {
            return $visibility;
        }

        $nested = [];

        foreach ($visibility as $key => $value) {
            data_set($nested, $key, $value);
        }

        return $nested;
    }
}
