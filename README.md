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

## License

MIT