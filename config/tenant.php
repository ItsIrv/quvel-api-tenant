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
    | Configure automatic middleware registration and dependencies.
    |
    | auto_register: When true, tenant middleware runs on ALL HTTP requests.
    |                When false, add 'tenant' middleware to specific routes.
    |
    | internal_request: Middleware class for protecting internal endpoints.
    |                   Used by SSR config endpoints. Can be swapped out.
    |
    | For admin panels or tenant-free routes:
    | - Option 1: Set auto_register=false, add 'tenant' only where needed
    | - Option 2: Set auto_register=true, use bypass callback for exceptions
    |
    */
    'middleware' => [
        'auto_register' => env('TENANT_AUTO_MIDDLEWARE', true),
        'internal_request' => 'tenant.is-internal',
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
            'cache_enabled' => env('TENANT_RESOLVER_ENABLE_CACHE', false),
            'cache_ttl' => env('TENANT_RESOLVER_CACHE_TTL', 300),
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
    | Tenant Config API
    |--------------------------------------------------------------------------
    |
    | Default settings for tenant config API endpoints.
    | These can be overridden per-tenant using the same config keys.
    |
    | Examples of tenant-specific overrides:
    | - tenant.api.allow_public_config: true/false
    | - tenant.api.allow_protected_config: true/false
    |
    */
    'api' => [
        'allow_public_config' => env('TENANT_ALLOW_PUBLIC_CONFIG', false),
        'allow_protected_config' => env('TENANT_ALLOW_PROTECTED_CONFIG', false),
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
        \Quvel\Tenant\Pipes\BroadcastingConfigPipe::class,
        \Quvel\Tenant\Pipes\CacheConfigPipe::class,
        \Quvel\Tenant\Pipes\DatabaseConfigPipe::class,
        \Quvel\Tenant\Pipes\FilesystemConfigPipe::class,
        \Quvel\Tenant\Pipes\LoggingConfigPipe::class,
        \Quvel\Tenant\Pipes\MailConfigPipe::class,
        \Quvel\Tenant\Pipes\QueueConfigPipe::class,
        \Quvel\Tenant\Pipes\RedisConfigPipe::class,
        \Quvel\Tenant\Pipes\SessionConfigPipe::class,
        \Quvel\Tenant\Pipes\ServicesConfigPipe::class,
        \Quvel\Tenant\Pipes\CoreServicesScopingPipe::class,

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

    /*
    |--------------------------------------------------------------------------
    | Scoped Models Configuration
    |--------------------------------------------------------------------------
    |
    | Models that should have tenant
    | scoping automatically applied. The package will add global scopes
    | and model events to enforce tenant isolation.
    |
    */
    'scoped_models' => [
        // Laravel's built-in models
        // \Illuminate\Notifications\DatabaseNotification::class,

        // Sanctum tokens
        // \Laravel\Sanctum\PersonalAccessToken::class,

        // Spatie permissions (be careful with global roles)
        // \Spatie\Permission\Models\Role::class,
        // \Spatie\Permission\Models\Permission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoping Behavior Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how tenant scoping behaves in different scenarios.
    |
    */
    'scoping' => [
        // Whether to throw NoTenantException when no tenant is found
        // When false, returns empty results instead of throwing
        'throw_no_tenant_exception' => env('TENANT_THROW_NO_TENANT_EXCEPTION', true),

        // Whether to automatically add tenant_id to model $fillable arrays
        'auto_fillable' => env('TENANT_AUTO_FILLABLE', true),

        // Whether to automatically add tenant_id to model $hidden arrays
        'auto_hidden' => env('TENANT_AUTO_HIDDEN', true),

        /*
        |----------------------------------------------------------------------
        | Skip Tenant ID in Isolated Databases
        |----------------------------------------------------------------------
        |
        | When true, skip tenant_id scoping for tenants using isolated databases.
        | When false, always use tenant_id scoping for consistency.
        |
        */
        'skip_tenant_id_in_isolated_databases' => env('TENANT_SKIP_TENANT_ID_ISOLATED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Preserve Tenant Context
    |--------------------------------------------------------------------------
    |
    | When true, automatically preserve tenant context using Laravel's Context.
    |
    */
    'preserve_context' => env('TENANT_PRESERVE_CONTEXT', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-aware queue behavior for Laravel's database queue driver.
    |
    | When enabled, this automatically:
    | 1. Adds tenant_id columns to jobs, failed_jobs, and job_batches tables
    | 2. Overrides the database queue connector to set tenant_id when dispatching jobs
    | 3. Overrides the failed job provider to set tenant_id when jobs fail
    | 4. Preserves tenant context across queue boundaries using Laravel Context
    |
    | Requirements:
    | - Database queue driver must be configured (config/queue.php)
    | - Tables must exist (run migrations after enabling this)
    |
    | Note: Only affects the 'database' queue driver. Other drivers (Redis, SQS)
    | rely on payload-based tenant context preservation via Laravel Context.
    |
    */
    'queue' => [
        // Enable tenant-aware database queue with automatic tenant_id column management
        'auto_tenant_id' => env('TENANT_QUEUE_AUTO_TENANT_ID', false),
    ],
];