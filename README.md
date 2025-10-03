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
        'database' => 'acme_db',
        'theme' => 'blue',
    ],
]);
```

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