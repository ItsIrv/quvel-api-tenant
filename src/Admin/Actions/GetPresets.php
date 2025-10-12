<?php

declare(strict_types=1);

namespace Quvel\Tenant\Admin\Actions;

use Quvel\Tenant\Data\ConfigFieldDefinitions;
use Quvel\Tenant\Data\PresetDefinitions;

class GetPresets
{
    /**
     * Get all available presets with their field definitions.
     */
    public function __invoke(): array
    {
        $presets = PresetDefinitions::all();

        return array_map(function ($preset) {
            return [
                'name' => $preset['name'],
                'description' => $preset['description'],
                'features' => $preset['features'],
                'fields' => $this->getFieldsWithDefinitions($preset['fields']),
            ];
        }, $presets);
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
