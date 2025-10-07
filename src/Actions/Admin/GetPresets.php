<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions\Admin;

use Quvel\Tenant\Data\ConfigFieldDefinitions;
use Quvel\Tenant\Data\PresetDefinitions;

class GetPresets
{
    /**
     * Get all available presets with their field definitions.
     */
    public function execute(): array
    {
        $presets = PresetDefinitions::all();
        $result = [];

        foreach ($presets as $key => $preset) {
            $result[$key] = [
                'name' => $preset['name'],
                'description' => $preset['description'],
                'features' => $preset['features'],
                'fields' => $this->getFieldsWithDefinitions($preset['fields']),
            ];
        }

        return $result;
    }

    /**
     * Get field definitions for the given field names.
     */
    private function getFieldsWithDefinitions(array $fieldNames): array
    {
        $definitions = ConfigFieldDefinitions::only($fieldNames);
        $result = [];

        foreach ($fieldNames as $name) {
            if (isset($definitions[$name])) {
                $result[] = array_merge(['name' => $name], $definitions[$name]);
            }
        }

        return $result;
    }
}
