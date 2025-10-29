<?php

declare(strict_types=1);

namespace Quvel\Tenant\Resolution\Resolvers;

use Illuminate\Http\Request;

/**
 * Resolves tenant identifiers from request domain or subdomain.
 *
 * Supports two modes via configuration:
 * - 'domain' (default): Uses full host (acme.example.com → "acme.example.com")
 * - 'subdomain': Extracts subdomain only (acme.example.com → "acme")
 *
 * @example
 * // Domain mode (default)
 * 'resolver' => ['driver' => 'domain']
 *
 * // Subdomain mode
 * 'resolver' => [
 *     'driver' => 'domain',
 *     'config' => ['mode' => 'subdomain']
 * ]
 */
class DomainResolver extends AbstractResolver
{
    /**
     * Extract tenant identifier from request.
     */
    public function getIdentifier(Request $request): ?string
    {
        $mode = $this->config['mode'] ?? 'domain';

        return match ($mode) {
            'subdomain' => $this->extractSubdomain($request),
            default => $request->getHost(),
        };
    }

    /**
     * Extract subdomain from the request host.
     *
     * Returns the first segment of the domain.
     * Example: acme.example.com → "acme"
     */
    private function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Return the first segment if there are at least 3 parts (subdomain.domain.tld)
        return count($parts) > 2 ? $parts[0] : null;
    }
}
