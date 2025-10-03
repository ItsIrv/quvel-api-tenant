<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the table where tenant data will be stored.
    |
    */
    'table_name' => env('TENANT_TABLE_NAME', 'tenants'),

    /*
    |--------------------------------------------------------------------------
    | Tenant Model
    |--------------------------------------------------------------------------
    |
    | The model class to use for tenants. You can extend the base model
    | to add custom functionality.
    |
    */
    'model' => \Quvel\Tenant\Models\Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Tables Configuration
    |--------------------------------------------------------------------------
    |
    | Define which tables should have tenant_id column added.
    | You can use:
    | - true: Use default settings
    | - array: Custom configuration
    | - string: Class that implements table configuration
    |
    */
    'tables' => [
        // Users table with proper tenant isolation
        'users' => [
            'after' => 'id',
            'cascade_delete' => true,
            'drop_uniques' => [['email']],
            'tenant_unique_constraints' => [['email']]
        ],
        // 'posts' => true, // Simple registration with defaults
        // 'orders' => \App\Tenant\Tables\OrdersTableConfig::class,
    ],
];