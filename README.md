# Quvel Tenant

Multi-tenant support for Laravel applications with flexible tenant resolution strategies.

## Installation

### 1. Install the Package

```bash
composer require quvel/tenant
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="Quvel\Tenant\TenantServiceProvider" --tag="config"
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Tenant Resolution

The package supports multiple tenant resolution strategies:

- **Domain**: Resolve tenant by domain name
- **Subdomain**: Resolve tenant by subdomain
- **Path**: Resolve tenant by URL path segment
- **Header**: Resolve tenant by HTTP header

#### Basic Configuration

Update your `config/tenant.php`:

```php
return [
    'resolver' => 'domain', // domain, subdomain, path, or header

    'resolvers' => [
        'domain' => [
            'enabled' => true,
            'cache_ttl' => 3600,
        ],
        'subdomain' => [
            'enabled' => true,
            'cache_ttl' => 3600,
        ],
        'path' => [
            'enabled' => true,
            'position' => 1, // URL segment position
            'cache_ttl' => 3600,
        ],
        'header' => [
            'enabled' => true,
            'header_name' => 'X-Tenant-ID',
            'cache_ttl' => 3600,
        ],
    ],
];
```

## Usage

### Creating Tenants

```php
use Quvel\Tenant\Models\Tenant;

// Create a new tenant
$tenant = Tenant::create([
    'name' => 'Acme Corp',
    'identifier' => 'acme.example.com',
    'config' => [
        // Use Laravel config paths directly - they map 1:1 to Laravel's config system
        'app.name' => 'Acme Corp',
        'app.url' => 'https://acme.example.com',
        'database.connections.mysql.database' => 'acme_db',
        'mail.from.address' => 'noreply@acme.example.com',
        'broadcasting.connections.pusher.app_id' => '123456',
    ],
]);
```

### Configuration System

The tenant configuration system uses **dot notation** that maps directly to Laravel's config paths:

```php
// Setting config values
$tenant->setConfig('app.name', 'Acme Corp');
$tenant->setConfig('mail.from.address', 'hello@acme.com');
$tenant->setConfig('broadcasting.connections.pusher.app_id', '123456');

// Getting config values (with parent inheritance)
$appName = $tenant->getConfig('app.name');
$mailFrom = $tenant->getConfig('mail.from.address', 'default@example.com');

// Checking if config exists
if ($tenant->hasConfig('broadcasting.connections.pusher.app_id')) {
    // Pusher is configured for this tenant
}

// Removing config
$tenant->forgetConfig('old.config.key');
```

**How it works:**
- Config keys use Laravel's dot notation (e.g., `app.name`, `database.connections.mysql.host`)
- Values are stored as nested arrays: `{"app": {"name": "Acme"}}`
- Pipes automatically apply these configs to Laravel's runtime configuration
- Child tenants inherit config from parent tenants

**Available Configuration Pipes:**
- `CoreConfigPipe` - App settings, URLs, locales, CORS
- `BroadcastingConfigPipe` - Pusher, Reverb, Redis broadcasting
- `CacheConfigPipe` - Cache drivers and tenant isolation
- `DatabaseConfigPipe` - Database connections per tenant
- `FilesystemConfigPipe` - Storage disks and S3 configuration
- `LoggingConfigPipe` - Log channels and Sentry integration
- `MailConfigPipe` - SMTP and mail service settings
- `QueueConfigPipe` - Queue drivers (database, Redis, SQS)
- `RedisConfigPipe` - Redis connections with tenant prefixing
- `ServicesConfigPipe` - Third-party APIs (Stripe, PayPal, etc.)
- `SessionConfigPipe` - Session configuration and isolation

### Resolving Current Tenant

```php
use Quvel\Tenant\TenantResolverManager;

$resolver = app(TenantResolverManager::class);
$tenant = $resolver->resolve();

if ($tenant) {
    echo "Current tenant: " . $tenant->name;
}
```

### Middleware

Add the tenant middleware to your routes:

```php
Route::middleware(['tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});
```

## Database Scoping System

The package provides automatic tenant isolation for your Eloquent models using two complementary approaches.

### Automatic Scoping (`scoped_models` Configuration)

Configure external models (from Laravel or packages) to automatically have tenant scoping applied:

```php
// config/tenant.php
'scoped_models' => [
    \App\Models\User::class,
    \Illuminate\Notifications\DatabaseNotification::class,
    \Laravel\Sanctum\PersonalAccessToken::class,
],
```

**Features:**
- Automatically adds `TenantScope` to filter queries by `tenant_id`
- Sets `tenant_id` on model creation
- Prevents updating/deleting models from other tenants
- Works without modifying the model files

---

### Trait-Based Scoping (`TenantScoped`)

Add the trait to your application models for automatic scoping with additional convenience methods:

```php
use Quvel\Tenant\Traits\TenantScoped;

class Post extends Model
{
    use TenantScoped;
}
```

**Features:**
- All features from `scoped_models` configuration
- Adds `tenant()` relationship
- Helper methods: `getCurrentTenant()`, `belongsToCurrentTenant()`
- Auto-adds `tenant_id` to `$fillable` and `$hidden`
- Query scopes: `forCurrentTenant()`, `forTenant($id)`, `forAllTenants()`
- Security guards on `save()`, `update()`, `delete()` operations
- Prevents changing `tenant_id` after model creation

---

### Manual Control (`HasTenant`)

For models that need tenant relationships but not automatic scoping:

```php
use Quvel\Tenant\Traits\HasTenant;

class SystemLog extends Model
{
    use HasTenant;

    public function scopeCurrentTenantLogs($query)
    {
        return $query->forCurrentTenant();
    }
}
```

**Features:**
- Adds `tenant()` relationship
- Provides helper methods
- No automatic scoping or tenant assignment
- Full manual control over scoping behavior

---

### Query Behavior

All scoped models automatically filter queries by the current tenant:

```php
// Automatically scoped queries
User::all();                  // WHERE tenant_id = 1
User::find(123);              // WHERE id = 123 AND tenant_id = 1
User::where('active', true);  // WHERE active = 1 AND tenant_id = 1

// Bypass scoping when needed
User::withoutTenantScope()->get();  // No tenant filtering
User::forAllTenants()->get();       // Alias for withoutTenantScope
User::forTenant(2)->get();          // Query specific tenant

// Helper functions for admin operations
without_tenant(fn() => User::count());  // Execute without scoping
with_tenant($tenant, fn() => User::count());  // Execute with specific tenant
```

### Configuration Options

Control scoping behavior through configuration:

```php
// config/tenant.php
'scoping' => [
    // Throw exception or return empty results when no tenant found
    'throw_no_tenant_exception' => true,

    // Automatically add tenant_id to model $fillable arrays
    'auto_fillable' => true,

    // Automatically add tenant_id to model $hidden arrays
    'auto_hidden' => true,
],
```

## Helper Functions

```php
tenant();           // Get current tenant model
tenant_id();        // Get current tenant ID
tenant_config('key'); // Get tenant config value
tenant_bypassed();  // Check if currently bypassed
without_tenant(fn); // Execute without tenant scoping
with_tenant($t, fn); // Execute with specific tenant
```


## License

MIT