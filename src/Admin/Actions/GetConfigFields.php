<?php

declare(strict_types=1);

namespace Quvel\Tenant\Admin\Actions;

use Quvel\Tenant\Data\ConfigFieldDefinitions;

class GetConfigFields
{
    /**
     * Get all available configuration fields.
     */
    public function __invoke(): array
    {
        $fields = ConfigFieldDefinitions::all();
        $result = [];

        foreach ($fields as $name => $properties) {
            $result[] = array_merge(['name' => $name], $properties);
        }

        return $result;
    }
}
