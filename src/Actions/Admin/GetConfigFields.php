<?php

declare(strict_types=1);

namespace Quvel\Tenant\Actions\Admin;

use Quvel\Tenant\Data\ConfigFieldDefinitions;

class GetConfigFields
{
    /**
     * Get all available configuration fields.
     */
    public function execute(): array
    {
        $fields = ConfigFieldDefinitions::all();
        $result = [];

        foreach ($fields as $name => $properties) {
            $result[] = array_merge(['name' => $name], $properties);
        }

        return $result;
    }
}
