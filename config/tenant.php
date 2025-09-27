<?php

use Quvel\Tenant\Resolvers\DomainResolver;

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
    | Tenant Resolver
    |--------------------------------------------------------------------------
    |
    | The resolver class to use for identifying tenants from requests.
    | Must implement \Quvel\Tenant\Contracts\TenantResolver interface.
    |
    */
    'resolver' => env('TENANT_RESOLVER', DomainResolver::class),

    /*
    |--------------------------------------------------------------------------
    | Resolver Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration passed to the resolver's constructor.
    |
    */
    'resolver_config' => [
        'cache_ttl' => env('TENANT_CACHE_TTL', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Config Handlers
    |--------------------------------------------------------------------------
    |
    | Register your tenant config handler classes here. These classes
    | handle tenant-specific configuration and customization.
    |
    */
    'config_handlers' => [
        // \App\Tenant\DatabaseConfigHandler::class,
        // \App\Tenant\MailConfigHandler::class,
        // \App\Tenant\CacheConfigHandler::class,
    ],
];