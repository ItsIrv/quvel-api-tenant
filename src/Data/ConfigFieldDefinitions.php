<?php

declare(strict_types=1);

namespace Quvel\Tenant\Data;

class ConfigFieldDefinitions
{
    /**
     * Get all available configuration fields.
     */
    public static function all(): array
    {
        return [
            // Required fields
            'app.name' => [
                'label' => 'Application Name',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'My Application',
                'description' => 'Display name for this tenant',
                'group' => 'Application',
            ],
            'app.url' => [
                'label' => 'Backend API URL',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://api.example.com',
                'description' => 'Backend API URL (Laravel)',
                'group' => 'Application',
            ],
            'frontend.url' => [
                'label' => 'Frontend App URL',
                'type' => 'url',
                'required' => true,
                'placeholder' => 'https://app.example.com',
                'description' => 'Frontend app URL (Quasar)',
                'group' => 'Frontend',
            ],

            // Optional app fields
            'app.env' => [
                'label' => 'Application Environment',
                'type' => 'select',
                'required' => false,
                'options' => ['local', 'development', 'staging', 'production'],
                'placeholder' => 'production',
                'description' => 'Application environment',
                'group' => 'Application',
            ],
            'app.debug' => [
                'label' => 'Debug Mode',
                'type' => 'boolean',
                'required' => false,
                'placeholder' => 'false',
                'description' => 'Enable debug mode',
                'group' => 'Application',
            ],
            'app.timezone' => [
                'label' => 'Application Timezone',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'UTC',
                'description' => 'Application timezone',
                'group' => 'Application',
            ],
            'app.locale' => [
                'label' => 'Application Locale',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'en',
                'description' => 'Application locale',
                'group' => 'Application',
            ],
            'app.fallback_locale' => [
                'label' => 'Fallback Locale',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'en',
                'description' => 'Fallback locale',
                'group' => 'Application',
            ],

            // Optional frontend fields
            'frontend.internal_api_url' => [
                'label' => 'Internal API URL',
                'type' => 'url',
                'required' => false,
                'placeholder' => 'http://localhost:8000',
                'description' => 'Internal API URL for SSR to backend communication',
                'group' => 'Frontend',
            ],
            'frontend.capacitor_scheme' => [
                'label' => 'Capacitor Custom Scheme',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'myapp',
                'description' => 'Custom scheme for mobile apps (Capacitor)',
                'group' => 'Frontend',
            ],
            'database.connections.mysql.host' => [
                'label' => 'Database Host',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'localhost',
                'description' => 'Database server hostname or IP',
                'group' => 'Database',
            ],
            'database.connections.mysql.port' => [
                'label' => 'Database Port',
                'type' => 'number',
                'required' => false,
                'placeholder' => '3306',
                'description' => 'Database server port',
                'group' => 'Database',
            ],
            'database.connections.mysql.database' => [
                'label' => 'Database Name',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'tenant_database',
                'description' => 'Name of the tenant database',
                'group' => 'Database',
            ],
            'database.connections.mysql.username' => [
                'label' => 'Database Username',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'tenant_user',
                'description' => 'Database username for tenant',
                'group' => 'Database',
            ],
            'database.connections.mysql.password' => [
                'label' => 'Database Password',
                'type' => 'password',
                'required' => true,
                'placeholder' => '',
                'description' => 'Database password for tenant',
                'group' => 'Database',
            ],

            // Cache Configuration (CacheConfigPipe)
            'cache.default' => [
                'label' => 'Default Cache Driver',
                'type' => 'select',
                'required' => false,
                'options' => ['file', 'redis', 'memcached', 'database'],
                'placeholder' => 'file',
                'description' => 'Default cache driver for this tenant',
                'group' => 'Cache',
            ],
            'cache.prefix' => [
                'label' => 'Cache Prefix',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'tenant_{id}',
                'description' => 'Cache key prefix for tenant isolation',
                'group' => 'Cache',
            ],

            // Redis Configuration (RedisConfigPipe)
            'database.redis.client' => [
                'label' => 'Redis Client',
                'type' => 'select',
                'required' => false,
                'options' => ['phpredis', 'predis'],
                'placeholder' => 'phpredis',
                'description' => 'Redis client library',
                'group' => 'Redis',
            ],
            'database.redis.default.host' => [
                'label' => 'Redis Host',
                'type' => 'text',
                'required' => false,
                'placeholder' => '127.0.0.1',
                'description' => 'Redis server host',
                'group' => 'Redis',
            ],
            'database.redis.default.password' => [
                'label' => 'Redis Password',
                'type' => 'password',
                'required' => false,
                'placeholder' => '',
                'description' => 'Redis server password',
                'group' => 'Redis',
            ],
            'database.redis.default.port' => [
                'label' => 'Redis Port',
                'type' => 'number',
                'required' => false,
                'placeholder' => '6379',
                'description' => 'Redis server port',
                'group' => 'Redis',
            ],
            'database.redis.default.prefix' => [
                'label' => 'Redis Key Prefix',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'tenant_{id}:',
                'description' => 'Redis key prefix for tenant isolation',
                'group' => 'Redis',
            ],

            // Session Configuration (SessionConfigPipe)
            'session.driver' => [
                'label' => 'Session Driver',
                'type' => 'select',
                'required' => false,
                'options' => ['file', 'cookie', 'database', 'redis', 'memcached'],
                'placeholder' => 'file',
                'description' => 'Session storage driver',
                'group' => 'Session',
            ],
            'session.lifetime' => [
                'label' => 'Session Lifetime (minutes)',
                'type' => 'number',
                'required' => false,
                'placeholder' => '120',
                'description' => 'Session lifetime in minutes',
                'group' => 'Session',
            ],
            'session.cookie' => [
                'label' => 'Session Cookie Name',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'tenant_{id}_session',
                'description' => 'Session cookie name for tenant isolation',
                'group' => 'Session',
            ],
            'session.domain' => [
                'label' => 'Session Cookie Domain',
                'type' => 'text',
                'required' => false,
                'placeholder' => '.example.com',
                'description' => 'Domain for session cookie',
                'group' => 'Session',
            ],
            'session.path' => [
                'label' => 'Session Cookie Path',
                'type' => 'text',
                'required' => false,
                'placeholder' => '/',
                'description' => 'Path for session cookie',
                'group' => 'Session',
            ],
            'session.encrypt' => [
                'label' => 'Encrypt Session',
                'type' => 'boolean',
                'required' => false,
                'placeholder' => 'false',
                'description' => 'Encrypt session data',
                'group' => 'Session',
            ],

            // Mail Configuration (MailConfigPipe) - Essential tenant-specific fields only
            'mail.from.address' => [
                'label' => 'From Email Address',
                'type' => 'email',
                'required' => false,
                'placeholder' => 'noreply@example.com',
                'description' => 'Default sender email address for this tenant',
                'group' => 'Mail',
            ],
            'mail.from.name' => [
                'label' => 'From Name',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'My Application',
                'description' => 'Default sender name for this tenant',
                'group' => 'Mail',
            ],
            'mail.reply_to.address' => [
                'label' => 'Reply-To Email Address',
                'type' => 'email',
                'required' => false,
                'placeholder' => 'support@example.com',
                'description' => 'Reply-to email address for this tenant',
                'group' => 'Mail',
            ],
            'mail.reply_to.name' => [
                'label' => 'Reply-To Name',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Support Team',
                'description' => 'Reply-to name for this tenant',
                'group' => 'Mail',
            ],
        ];
    }

    /**
     * Get fields for specific keys.
     */
    public static function only(array $keys): array
    {
        $all = self::all();
        $result = [];

        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }
}
