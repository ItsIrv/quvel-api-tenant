<?php

declare(strict_types=1);

namespace Quvel\Tenant\Auth;

use Illuminate\Auth\Passwords\CacheTokenRepository;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;

class TenantPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * Create a token repository instance based on the given configuration.
     */
    protected function createTokenRepository(
        array $config
    ): TenantDatabaseTokenRepository|CacheTokenRepository|TokenRepositoryInterface {
        $key = $this->app['config']['app.key'];

        if (str_starts_with((string) $key, 'base64:')) {
            $key = base64_decode(substr((string) $key, 7));
        }

        if (isset($config['driver']) && $config['driver'] === 'cache') {
            /** @psalm-suppress ArgumentTypeCoercion */
            return new CacheTokenRepository(
                $this->app['cache']->store($config['store'] ?? null),
                $this->app['hash'],
                $key,
                ($config['expire'] ?? 60) * 60,
                $config['throttle'] ?? 0,
            );
        }

        // Use tenant-aware database token repository
        return new TenantDatabaseTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
