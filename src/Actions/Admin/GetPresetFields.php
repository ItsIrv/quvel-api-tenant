<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions\Admin;

use Quvel\Tenant\Data\ConfigFieldDefinitions;
use Quvel\Tenant\Data\PresetDefinitions;

class GetPresetFields
{
    /**
     * Get field definitions for a specific preset.
     */
    public function __invoke(string $preset): ?array
    {
        $presetData = PresetDefinitions::get($preset);

        if (!$presetData) {
            return null;
        }

        $definitions = ConfigFieldDefinitions::only($presetData['fields']);
        $result = [];

        foreach ($presetData['fields'] as $name) {
            if (isset($definitions[$name])) {
                $result[] = array_merge(['name' => $name], $definitions[$name]);
            }
        }

        return $result;
    }
}
