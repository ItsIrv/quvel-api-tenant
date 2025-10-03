<?php

declare(strict_types=1);

namespace Quvel\Tenant\Enums;

/**
 * Configuration visibility levels for tenant config.
 *
 * Controls how tenant configuration is exposed across different layers:
 * - Laravel backend
 * - SSR server (Express/Node)
 * - Frontend/Browser
 */
enum ConfigVisibility: string
{
    /**
     * Public configuration.
     *
     * Flows: Laravel → SSR → Browser
     * Use for: App names, theme settings, public API endpoints
     */
    case PUBLIC = 'PUBLIC';

    /**
     * Protected configuration.
     *
     * Flows: Laravel → SSR (stops here)
     * Use for: API keys, internal service URLs, SSR-only settings
     */
    case PROTECTED = 'PROTECTED';

    /**
     * Private configuration.
     *
     * Flows: Laravel only
     * Use for: Database credentials, sensitive keys, internal settings
     * Note: This is the default for all config keys not explicitly set
     */
    case PRIVATE = 'PRIVATE';
}