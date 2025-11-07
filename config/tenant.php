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
    | Auto-Resolve Tenant Model
    |--------------------------------------------------------------------------
    |
    | When enabled, type-hinting the tenant model in controller/service
    | constructors will automatically resolve to TenantContext::current().
    |
    | Example with auto_resolve_model = true:
    |   public function show(Tenant $tenant) { ... }
    |   // $tenant is automatically the current tenant from context
    |
    | IMPORTANT: This feature is disabled by default to avoid confusion.
    | Enable only if you understand the implications:
    | - The tenant model will resolve to current context, not a new instance
    | - If no tenant is set, an exception will be thrown
    | - Route model binding for Tenant will be affected
    |
    */
    'auto_resolve_model' => env('TENANT_AUTO_RESOLVE_MODEL', false),

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
    | resolvers: Array of resolver strategies to try in order (first match wins)
    | drivers: Custom resolver driver class mappings (optional)
    | config: Unified caching configuration for identifier resolution
    |
    | Built-in drivers: domain
    | Custom drivers can be added via the 'drivers' array or by extending ResolverManager
    |
    | Examples:
    | Single resolver: [['domain' => []]]
    | Subdomain only: [['domain' => ['mode' => 'subdomain']]]
    | Fallback chain: [['domain' => ['mode' => 'subdomain']], ['domain' => []]]
    |
    */
    'resolver' => [
        'resolvers' => [
            ['domain' => []],
        ],
        'drivers' => [
            // Custom driver mappings (optional)
            // 'custom' => App\Tenant\Resolvers\CustomResolver::class,
        ],
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
    | middleware: Applied to route group. Affects /public and /protected endpoints.
    |             Remove 'tenant.is-internal' to allow tenant domains to access
    |             their own config. The /cache endpoint has hardcoded security
    |             and cannot be accessed from non-internal tenants regardless
    |             of this setting.
    |
    | Examples of tenant-specific overrides:
    | - tenant.api.allow_public_config: true/false
    | - tenant.api.allow_protected_config: true/false
    |
    */
    'api' => [
        'enabled' => env('TENANT_API_ENABLED', true),
        'prefix' => env('TENANT_API_PREFIX', 'tenant-info'),
        'name' => env('TENANT_API_NAME', 'tenant.'),
        'middleware' => ['tenant.is-internal'],
        'allow_public_config' => env('TENANT_ALLOW_PUBLIC_CONFIG', false),
        'allow_protected_config' => env('TENANT_ALLOW_PROTECTED_CONFIG', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Interface Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the optional admin interface for tenant management.
    |
    | When true, admin routes are registered for creating and
    | managing tenants. This should be disabled in production for security.
    |
    */
    'admin' => [
        'enabled' => env('TENANT_ADMIN_ENABLE', false),
        'prefix' => env('TENANT_ADMIN_PREFIX', 'tenant-admin'),
        'name' => env('TENANT_ADMIN_NAME', 'tenant.admin.'),
        'middleware' => [
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'tenant.is-internal',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration Pipes
    |--------------------------------------------------------------------------
    |
    | Configuration pipes that apply tenant config to Laravel's runtime config.
    | Pipes are executed in array order - reorder to change an execution sequence.
    |
    */
    'pipes' => [
        \Quvel\Tenant\Pipes\CoreConfigPipe::class,
        \Quvel\Tenant\Pipes\ServicesConfigPipe::class,
        \Quvel\Tenant\Pipes\LoggingConfigPipe::class,
        \Quvel\Tenant\Pipes\MailConfigPipe::class,
        \Quvel\Tenant\Pipes\RedisConfigPipe::class,
        \Quvel\Tenant\Pipes\BroadcastingConfigPipe::class,
        \Quvel\Tenant\Pipes\DatabaseConfigPipe::class,
        \Quvel\Tenant\Pipes\QueueConfigPipe::class,
        \Quvel\Tenant\Pipes\CacheConfigPipe::class,
        \Quvel\Tenant\Pipes\FilesystemConfigPipe::class,
        \Quvel\Tenant\Pipes\SessionConfigPipe::class,
        \Quvel\Tenant\Pipes\QuvelCoreConfigPipe::class,

        // Add your custom pipes here
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection Pooling
    |--------------------------------------------------------------------------
    |
    | Controls whether tenant database connections should be pooled by host.
    |
    | When false (default): Each tenant gets a unique connection named 'tenant_{id}'.
    |                       - Safest approach with complete isolation
    |                       - May hit database connection limits at scale (MySQL: 151, PostgreSQL: 100)
    |
    | When true: Tenants sharing the same host:port pool the same connection.
    |            - Reduces total connections from O(tenants) to O(unique_hosts)
    |            - Example: 1000 tenants on 5 hosts = 5 connections instead of 1000
    |            - Relies on PDO switching databases per query
    |            - Small risk of state pollution (transactions, temp tables, session vars)
    |
    | Note: Callback configurators via DatabaseConfigPipe::withTenantConnectionName()
    |       take precedence over this config.
    |
    */
    'database' => [
        'pool_connections_by_host' => env('TENANT_POOL_CONNECTIONS_BY_HOST', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Configuration Defaults
    |--------------------------------------------------------------------------
    |
    | Default behavior for tenant table configurations. These settings apply
    | when using auto-detection or simple 'true' configurations.
    |
    */
    'table_defaults' => [
        // Whether to cascade delete when tenant is deleted
        'cascade_delete' => true,

        // Automatically add tenant_id to all detected indexes
        'add_tenant_to_all_indexes' => true,

        // Automatically add tenant_id to all detected unique constraints
        'add_tenant_to_all_uniques' => true,

        // Custom constraint name generator (class name or null for default)
        // Must implement: public function unique(string $table, array $columns): string
        //                 public function index(string $table, array $columns): string
        'constraint_name_generator' => null,

        // Column after which tenant_id should be added (used when auto-detecting)
        'default_after_column' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Tables Configuration
    |--------------------------------------------------------------------------
    |
    | Define which tables should have a tenant_id column added.
    |
    | Supported formats:
    | - true: Use default settings with auto-detection
    | - array: Manual configuration (see examples below)
    | - class-string: Reference to a class extending BaseTenantTable
    |
    | Auto-detection example:
    |   'posts' => true  // Automatically detects indexes, uniques, FKs
    |
    | Manual configuration example:
    |   'users' => [
    |       'after' => 'id',
    |       'cascade_delete' => true,
    |       'drop_uniques' => [['email']],
    |       'tenant_unique_constraints' => [['email']],
    |   ]
    |
    | Class-based configuration example:
    |   'orders' => \App\Database\Tables\OrdersTable::class
    |
    | Auto-detection with overrides:
    |   'posts' => [
    |       'auto_detect_schema' => true,
    |       'after' => 'custom_id',  // Override detected value
    |   ]
    |
    */
    'tables' => [
        /*
        |----------------------------------------------------------------------
        | Core Tables (Required)
        |----------------------------------------------------------------------
        */

        // Users table - Use table class for manual control
        'users' => \Quvel\Tenant\Database\Tables\UsersTable::class,

        // Sanctum personal access tokens - Use table class for manual control
        'personal_access_tokens' => \Quvel\Tenant\Database\Tables\PersonalAccessTokensTable::class,

        /*
        |----------------------------------------------------------------------
        | Optional Application Tables
        |----------------------------------------------------------------------
        |
        | Uncomment and enable if these tables exist in your application.
        | You can use auto-detection (true) or reference the table class.
        |
        */

        // User devices (if exists in your app)
        // Auto-detect approach (clean):
        // 'user_devices' => true,
        // Or use table class for manual control:
        // 'user_devices' => \Quvel\Tenant\Database\Tables\UserDevicesTable::class,

        // Platform settings (if exists in your app)
        // 'platform_settings' => true,
        // Or: \Quvel\Tenant\Database\Tables\PlatformSettingsTable::class,

        // Failed jobs uuid handling (usually auto-configured via queue.auto_tenant_id)
        // 'failed_jobs' => \Quvel\Tenant\Database\Tables\FailedJobsTable::class,

        /*
        |----------------------------------------------------------------------
        | Laravel Telescope Tables
        |----------------------------------------------------------------------
        |
        | Only enable when TENANT_TELESCOPE_SCOPED=true.
        | For global admin debugging, keep these disabled (default).
        |
        */

        // Telescope entries (when tenant-scoping Telescope)
        // 'telescope_entries' => \Quvel\Tenant\Database\Tables\TelescopeEntriesTable::class,
        // 'telescope_entries_tags' => \Quvel\Tenant\Database\Tables\TelescopeEntriesTagsTable::class,
        // 'telescope_monitoring' => \Quvel\Tenant\Database\Tables\TelescopeMonitoringTable::class,

        /*
        |----------------------------------------------------------------------
        | Your Custom Tables
        |----------------------------------------------------------------------
        |
        | Add your application-specific tables here.
        |
        | Examples:
        |
        | Simple auto-detection:
        |   'posts' => true,
        |   'comments' => true,
        |
        | Class-based configuration:
        |   'orders' => \App\Database\Tables\OrdersTable::class,
        |
        | Manual array configuration:
        |   'products' => [
        |       'after' => 'id',
        |       'cascade_delete' => true,
        |       'drop_uniques' => [['sku']],
        |       'tenant_unique_constraints' => [['sku']],
        |   ],
        |
        */
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
        // Add your application models here
        // \App\Models\Post::class,
        // \App\Models\Order::class,

        // Laravel's built-in models
        // \Illuminate\Notifications\DatabaseNotification::class,

        // Sanctum tokens (for API authentication per tenant)
        // \Laravel\Sanctum\PersonalAccessToken::class,

        // Spatie permissions (enable if roles should be tenant-scoped)
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
        | Controls whether tenant_id column scoping should be skipped for tenants
        | using isolated databases. This enables hybrid multi-tenancy strategies.
        |
        | HOW IT WORKS:
        |
        | By default, all tenants share the main database defined in your .env file
        | (DB_HOST, DB_DATABASE, etc.) and are isolated using tenant_id columns.
        |
        | For per-tenant database isolation, configure tenant-specific database
        | settings in the tenant's config (database.connections.mysql.host or
        | database.connections.mysql.database). The DatabaseConfigPipe reads these
        | configs during tenant resolution and overrides Laravel's database config
        | for the current request.
        |
        |
        | When this setting is true and a tenant uses an isolated database:
        | - Model scopes skip tenant_id filtering (database is already isolated)
        | - Cache/Queue/Session drivers skip tenant_id columns
        | - Migrations don't require tenant_id columns for isolated databases
        |
        | Benefits of isolated databases:
        | - Complete data isolation at database level
        | - Cleaner schema without tenant_id columns
        | - Faster queries (no tenant_id index overhead)
        | - Easier data exports/backups (no filtering needed)
        | - Simplified data presentation (no tenant_id in dumps)
        |
        | When false, tenant_id scoping is always applied for consistency,
        | even if tenants use isolated databases.
        |
        */
        'skip_when_isolated' => env('TENANT_SKIP_WHEN_ISOLATED', false),
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
        'auto_tenant_id' => env('TENANT_QUEUE_AUTO_TENANT_ID', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-aware session drivers.
    |
    | When auto_tenant_id is enabled, this automatically:
    | 1. Database: Adds tenant_id column and scopes queries by tenant
    | 2. File: Creates tenant-specific subdirectories for session files
    | 3. Redis: Prefixes session keys with tenant ID
    |
    | Supported & Tested Drivers: database, file, redis
    | Unsupported: memcached (implementation exists but untested)
    |
    | Requirements:
    | - Session driver must be configured (config/session.php)
    | - For database: sessions table must exist with tenant_id column
    |
    */
    'sessions' => [
        'auto_tenant_id' => env('TENANT_SESSIONS_AUTO_TENANT_ID', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-aware cache behavior for Laravel's database cache driver.
    |
    | When enabled, this automatically:
    | 1. Adds tenant_id columns to cache and cache_locks tables
    | 2. Isolates cache entries per tenant (tenants can't access other tenant cache)
    |
    | Requirements:
    | - A database cache driver must be configured (config/cache.php)
    | - Cache tables must exist (run migrations after enabling this)
    |
    | Note: Only affects the 'database' cache driver. Other drivers (redis, file)
    | rely on different isolation mechanisms (like prefixes).
    |
    */
    'cache' => [
        'auto_tenant_id' => env('TENANT_CACHE_AUTO_TENANT_ID', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Tokens Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-aware password reset tokens behavior.
    |
    | When enabled, this automatically:
    | 1. Adds the tenant_id column to the password_reset_tokens table
    | 2. Isolates password reset tokens per tenant
    |
    | Requirements:
    | - Password reset tokens table must exist (run migrations after enabling this)
    |
    | Note: This ensures users can only reset passwords within their own tenant.
    |
    */
    'password_reset_tokens' => [
        'auto_tenant_id' => env('TENANT_PASSWORD_RESET_AUTO_TENANT_ID', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-aware broadcasting behavior.
    |
    | When auto_tenant_id is enabled, ALL broadcasts are automatically prefixed
    | with tenant identifiers at the driver level (Pusher, Reverb) ensuring
    | complete tenant isolation without code changes.
    |
    | For manual control, use the TenantAware trait in your broadcast events
    | or use the tenant_channel() helper function for selective prefixing.
    |
    */
    'broadcasting' => [
        'auto_tenant_id' => env('TENANT_BROADCASTING_AUTO_PREFIX', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-aware mail behavior.
    |
    | When auto_tenant_mail is enabled, ALL mail sent through the Mail facade
    | automatically uses tenant-specific from addresses, reply-to addresses,
    | and return paths without requiring code changes.
    |
    | For manual control, use the TenantAware trait in your mail classes
    | or use the tenant_mail() helper functions for selective configuration.
    |
    */
    'mail' => [
        'auto_tenant_mail' => env('TENANT_MAIL_AUTO_TENANT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant-aware filesystem behavior.
    |
    | When auto_tenant_scoping is enabled, ALL filesystem operations through
    | the Storage facade automatically prefix paths with tenant folders
    | ensuring complete file isolation between tenants.
    |
    | For manual control, use the HasTenantStorage trait in your classes
    | or use the tenant_storage_*() helper functions for selective scoping.
    |
    */
    'filesystems' => [
        'auto_tenant_scoping' => env('TENANT_FILESYSTEM_AUTO_SCOPING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel Telescope Configuration
    |--------------------------------------------------------------------------
    |
    | Configure tenant isolation for Laravel Telescope debugging data.
    |
    | When false (default): Telescope data is GLOBAL across all tenants.
    |                       - All tenant activity visible in single Telescope dashboard
    |                       - Useful for platform-wide admin debugging
    |                       - No tenant_id scoping applied to EntryModel
    |                       - Recommended for most applications
    |
    | When true: Telescope data is TENANT-SCOPED.
    |            - Each tenant only sees their own Telescope entries
    |            - EntryModel automatically added to scoped_models
    |            - Requires tenant_id columns on telescope tables:
    |              * telescope_entries
    |              * telescope_entries_tags
    |              * telescope_monitoring
    |            - Must publish and modify telescope migrations to add tenant_id
    |
    | Note: This only affects the EntryModel scoping. Telescope must be installed
    |       separately via: composer require laravel/telescope
    |
    */
    'telescope' => [
        'tenant_scoped' => env('TENANT_TELESCOPE_SCOPED', false),
    ],
];
