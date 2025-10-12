# Quvel Tenant

Multi-tenant support for Laravel applications with flexible tenant resolution, database isolation, and service scoping.

## Configuration

### Tenant Model

```php
'model' => \Quvel\Tenant\Models\Tenant::class,
```

Configures which model class to use for tenant operations. This allows you to extend the base tenant model with custom functionality while keeping the package code model-agnostic.

**How it works:**
- Used by `tenant_model()` helper function to create new tenant instances
- Used by `TenantFactory` for testing and seeding
- Used by resolvers when querying tenants from the database
- Enables model customization without modifying package code

**Usage example:**

```php
// Custom tenant model
class CustomTenant extends \Quvel\Tenant\Models\Tenant
{
    public function customMethod() {
        // Your custom logic
    }
}

// In config/tenant.php
'model' => \App\Models\CustomTenant::class,
```

The `tenant_model()` helper function returns a new instance of the configured model:
```php
$tenant = tenant_model(); // Returns new instance of configured model class
$tenant->name = 'New Tenant';
$tenant->save();
```

### Middleware Configuration

```php
'middleware' => [
    'auto_register' => env('TENANT_AUTO_MIDDLEWARE', true),
    'internal_request' => 'tenant.is-internal',
],
```

**auto_register**: Controls automatic tenant middleware registration on ALL HTTP requests.

- `true` (default): `TenantMiddleware` is automatically prepended to the global middleware stack, running on every request
- `false`: Manual middleware registration required - add `'tenant'` middleware to specific routes/groups

**How it works:**
```php
// When auto_register = true
// Middleware runs automatically on all requests

// When auto_register = false, manually add middleware:
Route::middleware(['tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

**internal_request**: Middleware alias for protecting internal/admin endpoints.

- Default value: `'tenant.is-internal'`
- Maps to `RequireInternalTenant::class` middleware
- Used by admin routes to ensure only internal tenants can access admin functionality
- Applied automatically to tenant admin UI routes when enabled

**Usage in the package:**
```php
// Admin routes automatically protected with internal_request middleware
Route::middleware(config('tenant.middleware.internal_request'))
    ->group(__DIR__.'/../routes/tenant-admin.php');
```

### Tenant Resolver Configuration

```php
'resolver' => [
    'class' => env('TENANT_RESOLVER', \Quvel\Tenant\Resolvers\DomainResolver::class),
    'config' => [
        'cache_enabled' => env('TENANT_RESOLVER_ENABLE_CACHE', false),
        'cache_ttl' => env('TENANT_RESOLVER_CACHE_TTL', 300),
    ],
],
```

**class**: The resolver class that determines how tenants are identified from HTTP requests.

- Default: `DomainResolver` - resolves tenants by domain name (`tenant.example.com`)
- Must implement `TenantResolver` interface
- Receives config array in constructor

**How it works:**
```php
// DomainResolver extracts host from request
$identifier = $request->getHost(); // e.g., "tenant.example.com"
$tenant = TenantModel::findByIdentifier($identifier);
```

**config.cache_enabled**: Controls whether resolved tenants are cached.

- `false` (default): No caching - resolver called on every request
- `true`: Enables caching using resolver's `getCacheKey()` method

**config.cache_ttl**: Cache time-to-live in seconds.

- `300` (default): Cache resolved tenants for 5 minutes
- `0`: Cache forever (until manually cleared)
- Only used when `cache_enabled = true`

**Caching behavior:**
```php
// When cache_enabled = true
Cache::remember("tenant.{$cacheKey}", $cacheTtl, function() {
    return $resolver->resolve($request);
});
```

**Available Resolvers:**
- `DomainResolver`: Resolve by full domain (`tenant.example.com`)

**Custom Resolver Example:**
```php
class SubdomainResolver implements TenantResolver
{
    public function resolve(Request $request)
    {
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        return TenantModel::findByIdentifier($subdomain);
    }

    public function getCacheKey(Request $request): ?string
    {
        return explode('.', $request->getHost())[0];
    }
}
```

### Tenant Not Found Handling

```php
'not_found' => [
    'strategy' => env('TENANT_NOT_FOUND_STRATEGY', 'abort'),
    'config' => [
        // For 'redirect' strategy
        'redirect_url' => env('TENANT_NOT_FOUND_REDIRECT', '/'),

        // For 'custom' strategy
        'handler' => null, // Invokable class name
    ],
],
```

**strategy**: Defines what happens when no tenant is found for a request.

- `'abort'` (default): Throws `NotFoundHttpException` (returns 404 Not Found)
- `'redirect'`: Redirects to a specified URL
- `'custom'`: Calls a custom handler class or callable

**How it works in TenantMiddleware:**
```php
protected function handleTenantNotFound(Request $request): never
{
    $strategy = config('tenant.not_found.strategy');
    $config = config('tenant.not_found.config', []);

    match ($strategy) {
        'redirect' => redirect($config['redirect_url'] ?? '/')->send(),
        'custom' => $this->callCustomHandler($config['handler'] ?? null, $request),
        default => throw new NotFoundHttpException('Tenant not found'),
    };
}
```

**config.redirect_url**: URL for redirect strategy.

- Default: `'/'` - redirects to homepage
- Environment: `TENANT_NOT_FOUND_REDIRECT`
- Only used when `strategy = 'redirect'`

**config.handler**: Custom handler for 'custom' strategy.

- Must be an invokable class name or callable
- Receives the `Request` object
- Example: `\App\Handlers\TenantNotFoundHandler::class`

**Custom Handler Example:**
```php
class TenantNotFoundHandler
{
    public function __invoke(Request $request)
    {
        // Custom logic - log, redirect, show special page, etc.
        return response()->view('tenant-not-found', [], 404);
    }
}

// In config
'not_found' => [
    'strategy' => 'custom',
    'config' => [
        'handler' => \App\Handlers\TenantNotFoundHandler::class,
    ],
],
```

### Tenant Config API

```php
'api' => [
    'allow_public_config' => env('TENANT_ALLOW_PUBLIC_CONFIG', false),
    'allow_protected_config' => env('TENANT_ALLOW_PROTECTED_CONFIG', false),
],
```

Controls access to tenant configuration API endpoints. These endpoints expose tenant config for frontend applications and SSR.

**allow_public_config**: Enables the public config API endpoint.

- `false` (default): Public config API disabled - throws 404
- `true`: Enables `/tenant-info/public` endpoint
- Returns only config marked with `ConfigVisibility::PUBLIC`
- No authentication required

**allow_protected_config**: Enables the protected config API endpoint.

- `false` (default): Protected config API disabled - throws 404
- `true`: Enables `/tenant-info/protected` endpoint
- Returns config marked with `ConfigVisibility::PUBLIC` and `ConfigVisibility::PROTECTED`
- Protected by `internal_request` middleware

**Available API Endpoints:**

```php
// Public config (when allow_public_config = true)
GET /tenant-info/public
// Returns: { data: { config: { /* public config only */ } } }

// Protected config (when allow_protected_config = true)
GET /tenant-info/protected
// Returns: { data: { config: { /* public + protected config */ } } }

// Cache endpoint (always requires internal middleware)
GET /tenant-info/cache
// Returns: Collection of all tenant configs for SSR
```

**How it works:**
```php
// In TenantPublicConfig action
public function __invoke($tenant): TenantConfigResource
{
    $allowPublicConfig = config('tenant.api.allow_public_config', false);

    if ($allowPublicConfig !== true) {
        throw new NotFoundHttpException('API not enabled for this tenant');
    }

    return new TenantConfigResource($tenant)->setVisibilityLevel('public');
}
```

**Config Visibility Levels:**
- `ConfigVisibility::PRIVATE`: Never exposed via API
- `ConfigVisibility::PROTECTED`: Exposed in protected endpoint only
- `ConfigVisibility::PUBLIC`: Exposed in both public and protected endpoints

**Use Cases:**
- `allow_public_config`: Frontend needs app name, theme colors, public settings
- `allow_protected_config`: SSR needs database config, mail settings, API keys

### Configuration Pipes

```php
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
    \Quvel\Tenant\Pipes\QuvelCoreConfigPipe::class,

    // Add your custom pipes here
],
```

Configuration pipes apply tenant config to Laravel's runtime configuration. Pipes are executed in array order during tenant resolution.

**How it works:**
```php
// In TenantMiddleware, after tenant is resolved:
$this->configPipeline->apply($tenant, config());

// ConfigurationPipeManager loads pipes from config and executes them:
foreach ($this->pipes as $pipe) {
    $pipe->handle($tenant, $config);
}
```

**Available Pipes:**

- **CoreConfigPipe**: App settings (`app.name`, `app.url`, `app.timezone`, `frontend.url`, CORS)
- **BroadcastingConfigPipe**: Pusher, Reverb, broadcasting drivers and credentials
- **CacheConfigPipe**: Cache drivers, Redis configuration, prefixes
- **DatabaseConfigPipe**: Database connections, credentials, isolated databases
- **FilesystemConfigPipe**: Storage disks, S3 configuration, CDN settings
- **LoggingConfigPipe**: Log channels, Sentry configuration, log levels
- **MailConfigPipe**: SMTP settings, mail drivers, from addresses
- **QueueConfigPipe**: Queue drivers (database, Redis, SQS), worker configuration
- **RedisConfigPipe**: Redis connections with tenant prefixing
- **SessionConfigPipe**: Session drivers and tenant isolation
- **ServicesConfigPipe**: Third-party APIs (Stripe, PayPal, payment gateways)
- **QuvelCoreConfigPipe**: Enable tenant scoping for the Quvel Core package

**Pipe Implementation:**
```php
class CoreConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'app.name',      // Direct 1:1 mapping
            'app.url',
            'frontend.url',
        ]);
    }
}
```

**Custom Pipe Example:**
```php
class CustomConfigPipe extends BasePipe
{
    public function apply(): void
    {
        // Set config if tenant has the value
        $this->setIfExists('custom.api_key', 'services.custom.key');

        // Set multiple configs at once
        $this->setMany([
            'custom.endpoint',           // Direct mapping
            'tenant_theme' => 'app.theme', // Custom mapping
        ]);
    }
}
```

**Execution Context:**
- Executed on every request after tenant resolution
- Receives current tenant and Laravel config repository
- Can modify any Laravel configuration at runtime
- Order matters - later pipes can override earlier ones

### Tenant Tables Configuration

```php
'tables' => [
    // Users table with proper tenant isolation
    'users' => [
        'after' => 'id',
        'cascade_delete' => true,
        'drop_uniques' => [['email']],
        'tenant_unique_constraints' => [['email']]
    ],
    'posts' => true, // Simple registration with defaults
    // 'orders' => \App\Tenant\Tables\OrdersTableConfig::class,
],
```

Defines which application tables should have `tenant_id` columns added. These tables get automatically modified by `TenantTableManager` to support tenant isolation.

**How it works:**
```php
// Process configured tables to add tenant_id columns
$manager = app(\Quvel\Tenant\Managers\TenantTableManager::class);
$results = $manager->processTables();

// Result: ['users' => 'processed', 'posts' => 'skipped_exists']
```

**Configuration Options:**

**Simple Registration:**
```php
'table_name' => true, // Uses default settings
```

**Advanced Configuration:**
```php
'users' => [
    'after' => 'id',                           // Insert tenant_id after this column
    'cascade_delete' => true,                  // Cascade delete when tenant is deleted
    'drop_uniques' => [['email']],             // Drop these unique constraints
    'tenant_unique_constraints' => [['email']] // Create tenant-scoped unique constraints
],
```

**Custom Configuration Class:**
```php
'orders' => \App\Tenant\Tables\OrdersTableConfig::class,

// OrdersTableConfig.php
class OrdersTableConfig
{
    public function getConfig(): TenantTableConfig
    {
        return new TenantTableConfig(
            after: 'id',
            cascadeDelete: true,
            dropUniques: [['order_number']],
            tenantUniqueConstraints: [['order_number']]
        );
    }
}
```

**What gets added to tables:**
- `tenant_id` foreign key column referencing `tenants.id`
- Index on `tenant_id` for query performance
- Optional cascade delete constraint
- Tenant-scoped unique constraints (e.g., `tenant_id + email` unique)

**Automatic Service Table Registration:**
The manager automatically adds system tables when service isolation is enabled:

```php
// When 'queue.auto_tenant_id' = true
'jobs', 'failed_jobs', 'job_batches'

// When 'sessions.auto_tenant_id' = true
'sessions'

// When 'cache.auto_tenant_id' = true
'cache', 'cache_locks'

// When 'password_reset_tokens.auto_tenant_id' = true
'password_reset_tokens'
```

**Processing Results:**
- `'processed'`: Table successfully updated with tenant_id
- `'skipped_exists'`: Table already has tenant_id column
- `'skipped_missing'`: Table doesn't exist in database
- `'error: message'`: Processing failed with error details

**Usage Example:**
```php
// Process all configured tables
$manager = app(\Quvel\Tenant\Managers\TenantTableManager::class);
$results = $manager->processTables();

// Process specific tables only
$results = $manager->processTables(['users', 'posts']);

// Remove tenant support
$results = $manager->removeTenantSupport(['old_table']);
```

### Scoped Models Configuration

```php
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
```

Models that should have tenant scoping automatically applied. The package adds global scopes and model events to enforce tenant isolation **without requiring code changes**.

**How it works:**
```php
// In TenantServiceProvider::bootExternalModelScoping()
foreach ($models as $modelClass) {
    // Add global scope for automatic WHERE tenant_id filtering
    $modelClass::addGlobalScope(new Scopes\TenantScope());

    // Add model events for automatic tenant_id assignment and validation
    $modelClass::creating(function ($model) {
        if (!isset($model->tenant_id) && !tenant_bypassed()) {
            $model->tenant_id = tenant_id();
        }
    });

    $modelClass::updating(function ($model) {
        $this->validateTenantMatch($model); // Prevent cross-tenant updates
    });

    $modelClass::deleting(function ($model) {
        $this->validateTenantMatch($model); // Prevent cross-tenant deletes
    });
}
```

**Automatic Scoping Behavior:**

**Query Filtering:**
```php
// Automatically scoped to current tenant
$posts = Post::all(); // WHERE tenant_id = current_tenant_id

// Available query macros
$posts = Post::forAllTenants()->get();    // Remove tenant filtering
$posts = Post::withoutTenantScope()->get(); // Same as forAllTenants()
$posts = Post::forTenant(123)->get();       // Filter for specific tenant ID
```

**Model Creation:**
```php
// tenant_id automatically assigned
$post = Post::create(['title' => 'New Post']);
// Results in: INSERT ... (title, tenant_id) VALUES ('New Post', current_tenant_id)
```

**Cross-Tenant Protection:**
```php
// Prevents updating/deleting models from different tenants
$otherTenantPost = Post::forAllTenants()->where('tenant_id', 999)->first();
$otherTenantPost->update(['title' => 'Hacked']); // Throws TenantMismatchException
```

**Isolated Database Behavior:**
For tenants using isolated databases, tenant_id scoping is automatically disabled since database-level isolation provides the separation.

**No-Tenant Handling:**
When no tenant context exists:
- `throw_no_tenant_exception = true`: Throws `NoTenantException`
- `throw_no_tenant_exception = false`: Returns empty results (`WHERE 1 = 0`)

**Bypass Mode:**
```php
// Admin operations can bypass tenant scoping
without_tenant(function () {
    return Post::all(); // Returns all posts across all tenants
});

// Or using TenantContext
TenantContext::bypass();
$allPosts = Post::all();
```

**Events Dispatched:**
- `TenantScopeApplied`: When scope is applied to a query
- `TenantScopeNoTenantFound`: When no tenant context exists
- `TenantMismatchDetected`: When cross-tenant operation is blocked

### Scoping Behavior Configuration

```php
'scoping' => [
    // Whether to throw NoTenantException when no tenant is found
    // When false, returns empty results instead of throwing
    'throw_no_tenant_exception' => env('TENANT_THROW_NO_TENANT_EXCEPTION', true),

    // Whether to automatically add tenant_id to model $fillable arrays
    'auto_fillable' => env('TENANT_AUTO_FILLABLE', true),

    // Whether to automatically add tenant_id to model $hidden arrays
    'auto_hidden' => env('TENANT_AUTO_HIDDEN', true),

    // Skip tenant_id scoping for tenants using isolated databases
    // When true, skip tenant_id scoping for tenants using isolated databases.
    // When false, always use tenant_id scoping for consistency.
    'skip_tenant_id_in_isolated_databases' => env('TENANT_SKIP_TENANT_ID_ISOLATED', false),
],
```

Configures how tenant scoping behaves across the application.

**throw_no_tenant_exception**: Controls behavior when no tenant context exists.

- `true` (default): Throws `NoTenantException` when trying to query tenant-scoped models without tenant context
- `false`: Returns empty results by applying `WHERE 1 = 0` condition

**How it works:**
```php
// In TenantScope::apply()
if (!$tenant) {
    if (config('tenant.scoping.throw_no_tenant_exception', true)) {
        throw new NoTenantException('No tenant context found for model...');
    }

    // Return empty results
    $builder->whereRaw('1 = 0');
}
```

**auto_fillable**: Automatically adds `tenant_id` to model `$fillable` arrays.

- `true` (default): Models using `TenantScoped` trait get `tenant_id` added to `$fillable`
- `false`: Manual fillable management required

**auto_hidden**: Automatically adds `tenant_id` to model `$hidden` arrays.

- `true` (default): Models using `TenantScoped` trait get `tenant_id` added to `$hidden` (excludes from JSON serialization)
- `false`: Manual hidden management required

**How it works:**
```php
// In TenantScoped::initializeTenantScoped()
if (config('tenant.scoping.auto_fillable', true) && !in_array('tenant_id', $this->getFillable(), true)) {
    $this->fillable[] = 'tenant_id';
}

if (config('tenant.scoping.auto_hidden', true) && !in_array('tenant_id', $this->getHidden(), true)) {
    $this->hidden[] = 'tenant_id';
}
```

**skip_tenant_id_in_isolated_databases**: Controls tenant_id scoping for isolated database tenants.

- `false` (default): Always use tenant_id scoping for consistency across all isolation strategies
- `true`: Skip tenant_id scoping for tenants using isolated databases since database-level isolation provides separation

**How it works:**
```php
// In TenantScope and model events
if ($this->tenantUsesIsolatedDatabase($tenant)) {
    return; // Skip tenant_id logic - database isolation handles it
}

// Otherwise apply tenant_id scoping
$builder->where('tenant_id', $tenant->id);
```

**Configuration Trade-offs:**

**throw_no_tenant_exception = false:**
- Pros: Prevents exceptions during development/testing
- Cons: Can hide bugs where tenant context is missing

**skip_tenant_id_in_isolated_databases = true:**
- Pros: Better performance, cleaner database schema for isolated tenants
- Cons: Inconsistent behavior between isolation strategies

**auto_fillable/auto_hidden = false:**
- Pros: Full control over model configuration
- Cons: Manual configuration required for every tenant-scoped model

### Context Preservation

```php
'preserve_context' => env('TENANT_PRESERVE_CONTEXT', true),
```

Enables automatic tenant context preservation across async operations using Laravel's Context feature. This ensures tenant context is maintained in queued jobs, HTTP requests, and other async operations.

**How it works:**
```php
// In TenantServiceProvider::registerContextPreservation()
Context::dehydrating(static function ($context): void {
    $tenant = TenantContextFacade::current();

    if ($tenant) {
        $context->addHidden('tenant', $tenant);
    }
});

Context::hydrated(function ($context): void {
    if ($context->hasHidden('tenant')) {
        $tenant = $context->getHidden('tenant');

        TenantContextFacade::setCurrent($tenant);

        app(ConfigurationPipeManager::class)->apply(
            $tenant,
            $this->app->make(ConfigRepository::class)
        );
    }
});
```

**When enabled (`true`, default):**
- Tenant context is automatically serialized when Laravel dehydrates context (queued jobs, etc.)
- Tenant context is restored when Laravel hydrates context in the worker/async process
- Configuration pipes are re-applied to restore tenant-specific settings
- Works across queue drivers (database, Redis, SQS, etc.)

**When disabled (`false`):**
- No automatic context preservation
- Async operations lose tenant context
- Manual tenant context management required for jobs

**Use Cases:**

**Queued Jobs:**
```php
// With preserve_context = true
dispatch(new ProcessOrderJob($order));
// Job automatically has tenant context and config

// With preserve_context = false
dispatch(new ProcessOrderJob($order, tenant_id()));
// Must manually pass tenant_id and restore context
```

**Event Listeners:**
```php
// With preserve_context = true
event(new OrderCreated($order));
// Event listeners have tenant context

// With preserve_context = false
// Listeners lose tenant context if queued
```

**HTTP Client Requests:**
```php
// With preserve_context = true
Http::post('https://api.service.com', $data);
// Outbound requests can include tenant context via middleware

// With preserve_context = false
// Manual context management for external requests
```

**Performance Considerations:**
- Minimal overhead - tenant object is serialized/deserialized
- Context data travels with async operations
- Configuration pipes re-run on context restoration

**Requirements:**
- Laravel 11+ Context feature
- Works with all queue drivers that support Laravel Context
- No additional setup required

### Queue Configuration

```php
'queue' => [
    // Enable tenant-aware database queue with automatic tenant_id column management
    'auto_tenant_id' => env('TENANT_QUEUE_AUTO_TENANT_ID', false),
],
```

Enables tenant-aware queue behavior for Laravel's database queue driver. When enabled, this automatically adds `tenant_id` columns to queue tables and scopes queue operations per tenant.

**How it works:**
```php
// In TenantServiceProvider
if (config('tenant.queue.auto_tenant_id', true)) {
    // Override database queue connector
    $manager->addConnector('database', function () {
        return new TenantDatabaseConnector($this->app['db']);
    });

    // Override failed job provider
    return new TenantDatabaseUuidFailedJobProvider($app['db'], ...);

    // Override batch repository
    return new TenantDatabaseBatchRepository(...);
}
```

**What gets modified:**

**Queue Tables (`jobs`):**
```php
// In TenantDatabaseQueue::buildDatabaseRecord()
$record = [
    'queue' => $queue,
    'payload' => $payload,
    'tenant_id' => TenantContext::current()?->id, // Added automatically
];
```

**Failed Jobs Table (`failed_jobs`):**
```php
// TenantDatabaseUuidFailedJobProvider adds tenant_id when jobs fail
$failedJob['tenant_id'] = TenantContext::current()?->id;
```

**Job Batches Table (`job_batches`):**
```php
// TenantDatabaseBatchRepository adds tenant_id to batches
$batch['tenant_id'] = TenantContext::current()?->id;
```

**Automatic Table Registration:**
When enabled, these tables are automatically added to the `tables` config for `TenantTableManager`:

```php
// Automatically added tables when auto_tenant_id = true
'jobs' => [
    'after' => 'id',
    'cascade_delete' => true,
],
'failed_jobs' => [
    'after' => 'id',
    'cascade_delete' => true,
],
'job_batches' => [
    'after' => 'id',
    'cascade_delete' => true,
],
```

**Usage Example:**
```php
// Jobs automatically get tenant_id when dispatched
dispatch(new ProcessOrderJob($order));

// Failed jobs include tenant_id for isolation
// Batches are tenant-scoped automatically

// Process tables to add tenant_id columns
$manager = app(\Quvel\Tenant\Managers\TenantTableManager::class);
$results = $manager->processTables(['jobs', 'failed_jobs', 'job_batches']);
```

**Requirements:**
- Database queue driver must be configured in `config/queue.php`
- Queue tables must exist (run `queue:table` and migrate)
- Only affects the `database` queue driver
- Other drivers (Redis, SQS) rely on context preservation

**Tenant Context in Jobs:**
- Jobs automatically retain tenant context via `preserve_context`
- Queue isolation works alongside context preservation
- Worker processes restore tenant context and apply configuration pipes

**Benefits:**
- Complete job isolation per tenant
- Failed job isolation and debugging
- Batch operation isolation
- Database-level queue security

### Admin Interface Configuration

```php
'admin' => [
    'enable' => env('TENANT_ADMIN_ENABLE', false),
],
```

Controls the optional admin interface for tenant management. When enabled, provides a web UI and API endpoints for creating and managing tenants using preset configurations.

**enable**: Enables/disables the admin interface.

- `false` (default): Admin interface disabled for security
- `true`: Enables admin routes, views, and API endpoints

**How it works:**
```php
// In TenantServiceProvider
if (config('tenant.admin.enable', false)) {
    // Register admin routes with internal middleware protection
    Route::middleware(config('tenant.middleware.internal_request'))
        ->group(__DIR__.'/../routes/tenant-admin.php');

    // Register view namespace for Blade templates
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'tenant');
}
```

**Available Routes (when enabled):**
```php
// UI Routes
GET  /admin/tenants/ui              - Tenant management interface

// API Routes (protected by internal middleware)
GET  /admin/tenants/presets         - Get available presets
GET  /admin/tenants/presets/{preset}/fields - Get form fields for preset
GET  /admin/tenants                 - List all tenants
POST /admin/tenants                 - Create new tenant
```

**Admin Interface Features:**

**Tenant Creation with Presets:**
- **Basic Preset**: Shared database with minimal configuration
- **Isolated Database Preset**: Dedicated database with connection settings
- Dynamic form generation based on preset requirements
- Real-time field validation and error handling

**Tenant Listing:**
- View all existing tenants
- Display tenant names, identifiers, and creation dates
- Sortable and filterable interface

**API Integration:**
```php
// Get available presets
GET /admin/tenants/presets
// Response: {"presets": {"basic": {...}, "isolated_database": {...}}}

// Get form fields for a preset
GET /admin/tenants/presets/basic/fields
// Response: {"preset": "basic", "fields": [...]}

// Create tenant
POST /admin/tenants
{
    "name": "Acme Corp",
    "identifier": "acme.example.com",
    "preset": "basic",
    "config": {
        "app.name": "Acme Application",
        "frontend.url": "https://acme.example.com"
    }
}
```

**Security Considerations:**

**Protected by Internal Middleware:**
- Admin routes require `internal_request` middleware
- Only internal/system tenants can access admin functionality
- Prevents external access to tenant management

**Default Disabled:**
- Admin interface is disabled by default
- Must be explicitly enabled via environment variable
- Recommended to disable in production environments

**Environment Configuration:**
```php
// Enable admin interface (development/staging only)
TENANT_ADMIN_ENABLE=true

// Disable admin interface (production - default)
TENANT_ADMIN_ENABLE=false
```

**File Structure:**
```
/resources/views/admin/ui.blade.php  - Main admin interface
/routes/tenant-admin.php            - Admin route definitions
/Http/Controllers/TenantController.php - Admin API endpoints
```

**Use Cases:**
- Development/staging tenant setup
- Internal admin panels
- Automated tenant provisioning
- Tenant configuration management

**Production Recommendations:**
- Keep `enable = false` in production
- Use programmatic tenant creation instead
- Implement custom admin interfaces with proper authentication
