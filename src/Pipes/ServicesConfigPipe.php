<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

use Closure;

/**
 * Handles third-party services configuration for tenants.
 */
class ServicesConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'stripe_key' => 'services.stripe.key',
            'stripe_secret' => 'services.stripe.secret',
            'stripe_webhook_secret' => 'services.stripe.webhook_secret',
            'paypal_client_id' => 'services.paypal.client_id',
            'paypal_secret' => 'services.paypal.secret',
            'twilio_sid' => 'services.twilio.sid',
            'twilio_token' => 'services.twilio.token',
            'twilio_from' => 'services.twilio.from',
            'sendgrid_api_key' => 'services.sendgrid.api_key',
            'mailgun_domain' => 'services.mailgun.domain',
            'mailgun_secret' => 'services.mailgun.secret',
            'postmark_token' => 'services.postmark.token',
            'ses_key' => 'services.ses.key',
            'ses_secret' => 'services.ses.secret',
            'algolia_app_id' => 'services.algolia.app_id',
            'algolia_secret' => 'services.algolia.secret',
            'google_analytics_id' => 'services.google_analytics.tracking_id',
            'google_maps_key' => 'services.google_maps.key',
            'bugsnag_api_key' => 'services.bugsnag.api_key',
            'slack_webhook_url' => 'services.slack.webhook_url',
        ]);

        $this->configureServicesWithDefaults();

        if ($this->tenant->hasConfig('custom_api_endpoints')) {
            $this->configureCustomApiServices();
        }
    }

    /**
     * Configure services that need default values.
     */
    protected function configureServicesWithDefaults(): void
    {
        if ($this->tenant->hasConfig('paypal_client_id')) {
            $mode = $this->tenant->getConfig('paypal_mode') ?? $this->getDefaultPayPalMode();
            $this->config->set('services.paypal.mode', $mode);
        }

        if ($this->tenant->hasConfig('mailgun_domain')) {
            $endpoint = $this->tenant->getConfig('mailgun_endpoint') ?? $this->getDefaultMailgunEndpoint();
            $this->config->set('services.mailgun.endpoint', $endpoint);
        }

        if ($this->tenant->hasConfig('ses_key')) {
            $region = $this->tenant->getConfig('ses_region') ?? $this->getDefaultSesRegion();
            $this->config->set('services.ses.region', $region);
        }
    }

    /**
     * Configure custom API services.
     */
    protected function configureCustomApiServices(): void
    {
        $endpoints = $this->tenant->getConfig('custom_api_endpoints');

        foreach ($endpoints as $service => $endpoint) {
            $this->config->set('services.custom.' . $service . '.endpoint', $endpoint);

            $apiKeyConfig = 'custom_api_keys.' . $service;

            if ($this->tenant->hasConfig($apiKeyConfig)) {
                $apiKey = $this->tenant->getConfig($apiKeyConfig);
                $this->config->set('services.custom.' . $service . '.key', $apiKey);
            }
        }
    }

    /**
     * Configure default PayPal mode.
     */
    public static function withDefaultPayPalMode(Closure $callback): string
    {
        static::registerConfigurator('default_paypal_mode', $callback);

        return static::class;
    }

    /**
     * Configure default Mailgun endpoint.
     */
    public static function withDefaultMailgunEndpoint(Closure $callback): string
    {
        static::registerConfigurator('default_mailgun_endpoint', $callback);

        return static::class;
    }

    /**
     * Configure default SES region.
     */
    public static function withDefaultSesRegion(Closure $callback): string
    {
        static::registerConfigurator('default_ses_region', $callback);

        return static::class;
    }

    /**
     * Get default PayPal mode using configurator or default.
     */
    protected function getDefaultPayPalMode(): string
    {
        return $this->applyConfigurator('default_paypal_mode', 'sandbox');
    }

    /**
     * Get default Mailgun endpoint using configurator or default.
     */
    protected function getDefaultMailgunEndpoint(): string
    {
        return $this->applyConfigurator('default_mailgun_endpoint', 'api.mailgun.net');
    }

    /**
     * Get default SES region using configurator or default.
     */
    protected function getDefaultSesRegion(): string
    {
        return $this->applyConfigurator('default_ses_region', 'us-east-1');
    }
}
