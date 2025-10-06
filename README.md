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
    \Quvel\Tenant\Pipes\CoreServicesScopingPipe::class,

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
- **CoreServicesScopingPipe**: Enable tenant scoping for various Laravel services

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

**Model Configuration Options:**
Models can be added to this config without any code changes - the package handles all tenant behavior automatically through global scopes and events.
