<?php

declare(strict_types=1);

namespace Quvel\Tenant\Pipes;

/**
 * Handles logging configuration for tenants.
 */
class LoggingConfigPipe extends BasePipe
{
    public function apply(): void
    {
        $this->setMany([
            'log_channel' => 'logging.default',
            'log_deprecations_channel' => 'logging.deprecations.channel',
            'log_daily_days' => 'logging.channels.daily.days',
            'log_slack_webhook_url' => 'logging.channels.slack.url',
            'log_slack_channel' => 'logging.channels.slack.channel',
            'log_slack_username' => 'logging.channels.slack.username',
            'log_stack_channels' => 'logging.channels.stack.channels',
        ]);

        $this->configureTenantLogPaths();

        if ($this->tenant->hasConfig('log_level')) {
            $this->configureLogLevels();
        }

        if ($this->tenant->hasConfig('log_custom_driver')) {
            $this->configureCustomLogChannel();
        }
    }

    /**
     * Configure tenant-isolated log file paths.
     */
    protected function configureTenantLogPaths(): void
    {
        if ($this->tenant->hasConfig('log_single_path')) {
            $this->setIfExists('log_single_path', 'logging.channels.single.path');
        } else {
            $this->config->set('logging.channels.single.path', storage_path('logs/tenants/' . $this->tenant->public_id . '/laravel.log'));
        }

        if ($this->tenant->hasConfig('log_daily_path')) {
            $this->setIfExists('log_daily_path', 'logging.channels.daily.path');
        } else {
            $this->config->set('logging.channels.daily.path', storage_path('logs/tenants/' . $this->tenant->public_id . '/laravel.log'));
        }
    }

    /**
     * Configure log levels across all channels.
     */
    protected function configureLogLevels(): void
    {
        $level = $this->tenant->getConfig('log_level');
        $this->config->set('logging.channels.single.level', $level);
        $this->config->set('logging.channels.daily.level', $level);
        $this->config->set('logging.channels.slack.level', $level);
        $this->config->set('logging.channels.stderr.level', $level);
    }

    /**
     * Configure custom tenant log channel.
     */
    protected function configureCustomLogChannel(): void
    {
        $this->config->set('logging.channels.tenant', [
            'driver' => $this->tenant->getConfig('log_custom_driver'),
            'path'   => $this->tenant->getConfig('log_custom_path') ?? storage_path('logs/tenants/' . $this->tenant->public_id . '/custom.log'),
            'level'  => $this->tenant->getConfig('log_custom_level') ?? 'info',
            'days'   => $this->tenant->getConfig('log_custom_days') ?? 14,
        ]);
    }
}