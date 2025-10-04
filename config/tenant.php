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
    | Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic middleware registration.
    |
    | auto_register: When true, tenant middleware runs on ALL HTTP requests.
    |                When false, add 'tenant' middleware to specific routes.
    |
    | For admin panels or tenant-free routes:
    | - Option 1: Set auto_register=false, add 'tenant' only where needed
    | - Option 2: Set auto_register=true, use bypass callback for exceptions
    |
    */
    'middleware' => [
        'auto_register' => env('TENANT_AUTO_MIDDLEWARE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolver Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tenants are resolved from requests.
    |
    */
    'resolver' => [
        'class' => env('TENANT_RESOLVER', \Quvel\Tenant\Resolvers\DomainResolver::class),
        'config' => [
            'cache_ttl' => env('TENANT_CACHE_TTL', 300),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Not Found Handling
    |--------------------------------------------------------------------------
    |
    | Configure what happens when no tenant is resolved from a request.
    |
    | Strategies:
    | - 'abort': Return 404 Not Found response (default)
    | - 'redirect': Redirect to specified URL
    | - 'custom': Call a custom handler invokable class
    |
    */
    'not_found' => [
        'strategy' => env('TENANT_NOT_FOUND_STRATEGY', 'abort'),
        'config' => [
            // For 'redirect' strategy
            'redirect_url' => env('TENANT_NOT_FOUND_REDIRECT', '/'),

            // For 'custom' strategy
            'handler' => null, // Invokable class name
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Pipes
    |--------------------------------------------------------------------------
    |
    | Configuration pipes that apply tenant config to Laravel's runtime config.
    | Pipes are executed in array order - reorder to change execution sequence.
    |
    */
    'pipes' => [
        \Quvel\Tenant\Pipes\CoreConfigPipe::class,
        // Add your custom pipes here
    ],

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