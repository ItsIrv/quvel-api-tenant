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

## Tenant Scoping & Database Isolation

The package provides automatic tenant scoping with support for multiple isolation strategies.

### Model Scoping

**Option 1: Using the TenantScoped Trait**

```php
use Illuminate\Database\Eloquent\Model;
use Quvel\Tenant\Concerns\TenantScoped;

class Post extends Model
{
    use TenantScoped;
}

// Queries are automatically scoped to current tenant
$posts = Post::all(); // Only current tenant's posts
$posts = Post::forAllTenants()->get(); // Bypass scoping (admin access)
```

**Option 2: Using scoped_models Configuration**

```php
// config/tenant.php
return [
    'scoped_models' => [
        \App\Models\User::class,
        \App\Models\Post::class,
        \Spatie\Permission\Models\Role::class, // Works with third-party packages
    ],
];
```

With `scoped_models`, models automatically get tenant scoping without requiring code changes.

### Database Isolation Strategies

The package supports multiple database isolation patterns:

**1. Shared Database (tenant_id column)**
```php
$tenant = Tenant::create([
    'name' => 'Acme Corp',
    'identifier' => 'acme.example.com',
    // No database config = shared database with tenant_id scoping
]);
```

**2. Dedicated Database (same server, different database)**
```php
$tenant = Tenant::create([
    'name' => 'Acme Corp',
    'identifier' => 'acme.example.com',
    'config' => [
        'database.connections.mysql.database' => 'acme_database',
    ],
]);
```

**3. Isolated Database (separate server/credentials)**
```php
$tenant = Tenant::create([
    'name' => 'Acme Corp',
    'identifier' => 'acme.example.com',
    'config' => [
        'database.connections.mysql.host' => 'acme-db.example.com',
        'database.connections.mysql.database' => 'acme_production',
        'database.connections.mysql.username' => 'acme_user',
        'database.connections.mysql.password' => 'secure_password',
    ],
]);
```

**Automatic Detection**: The system automatically detects isolation strategy and adapts behavior:
- Shared database: Uses `WHERE tenant_id = ?` filtering
- Isolated database: Uses database-level isolation, with configurable tenant_id behavior

### Skip Tenant ID in Isolated Databases

Configure whether to skip tenant_id scoping for tenants using isolated databases:

```php
// config/tenant.php
'scoping' => [
    'skip_tenant_id_in_isolated_databases' => false,
],
```

**Configuration Options:**

- `false` (default): Always use tenant_id scoping for consistency across all tenant architectures
- `true`: Skip tenant_id scoping in isolated databases for performance or customer appeal (database-level isolation only)

### Tenant Creation with Configuration Builder

```php
use Quvel\Tenant\Actions\CreateTenant;
use Quvel\Tenant\Builders\TenantConfigurationBuilder;

$config = TenantConfigurationBuilder::create()
    ->withCoreConfig(
        appName: 'Acme Corp',
        appUrl: 'https://acme.example.com'
    )
    ->withIsolatedDatabase(
        host: 'acme-db.example.com',
        database: 'acme_production',
        username: 'acme_user',
        password: 'secure_password'
    );

$tenant = app(CreateTenant::class)->execute(
    name: 'Acme Corp',
    identifier: 'acme.example.com',
    configBuilder: $config
);
```

### Security Features

- **Cross-tenant protection**: Prevents models from being accessed/modified across tenant boundaries
- **Automatic tenant assignment**: Models get `tenant_id` assigned automatically during creation
- **Bypass protection**: Administrative operations can bypass tenant scoping with `without_tenant()`
- **Event-driven audit trail**: Tenant operations trigger events for monitoring and compliance

### Helper Functions

```php
// Get current tenant
$tenant = tenant();
$tenantId = tenant_id();

// Execute code in specific tenant context
$result = with_tenant($tenant, function () {
    return User::count(); // Counts users for specific tenant
});

// Execute code bypassing tenant scoping
$result = without_tenant(function () {
    return User::all(); // Returns all users across all tenants
});
```

## Service Isolation

Enable automatic tenant isolation for Laravel services. Each service adds `tenant_id` columns and scopes operations per tenant.

```php
// config/tenant.php
'queue' => ['auto_tenant_id' => true],           // Jobs, failed jobs, batches
'sessions' => ['auto_tenant_id' => true],        // Database sessions
'cache' => ['auto_tenant_id' => true],           // Database cache & locks
'password_reset_tokens' => ['auto_tenant_id' => true], // Password resets
'broadcasting' => ['auto_tenant_id' => true],    // Broadcast channels
'mail' => ['auto_tenant_mail' => true],          // Mail configurations
'filesystems' => ['auto_tenant_scoping' => true], // File storage
'preserve_context' => true,                      // Queue context preservation
```

**Add tenant_id columns:**
```php
app(\Quvel\Tenant\Managers\TenantTableManager::class)->processTables();
```

**What gets isolated:**
- **Queues**: Jobs maintain tenant context automatically (database driver)
- **Sessions**: Complete session isolation (database, file, redis, memcached drivers)
- **Cache**: Cache entries and locks are tenant-scoped (database driver) or prefixed (redis, memcached)
- **Password Resets**: Tokens isolated per tenant
- **Broadcasting**: Channel names automatically prefixed (log, pusher, reverb drivers)
- **Mail**: Tenant-specific from addresses and configurations
- **Filesystems**: File paths automatically scoped to tenant folders
- **Context**: Tenant preserved across async operations

**Supported Drivers:**
- **Cache**: database, redis, memcached, file, array
- **Sessions**: database, file, redis, memcached
- **Broadcasting**: log, pusher, reverb
- **Mail**: smtp, mailgun, postmark, ses, sendmail
- **Filesystems**: local, s3, ftp, sftp

No code changes required - everything works automatically once enabled.

## Events

The package dispatches events for monitoring and extending tenant behavior:

- **`Quvel\Tenant\Events\TenantResolved`** - Fired when a tenant is successfully resolved from a request
- **`Quvel\Tenant\Events\TenantNotFound`** - Fired when no tenant could be resolved from a request
- **`Quvel\Tenant\Events\TenantContextSet`** - Fired when tenant context has been set (from TenantContext)
- **`Quvel\Tenant\Events\TenantMiddlewareCompleted`** - Fired when tenant middleware processing is complete
- **`Quvel\Tenant\Events\TenantMismatchDetected`** - Fired when a cross-tenant operation is blocked for security
- **`Quvel\Tenant\Events\TenantScopeApplied`** - Fired when tenant scoping is applied to a query
- **`Quvel\Tenant\Events\TenantScopeNoTenantFound`** - Fired when no tenant is found for scoping

## License

MIT