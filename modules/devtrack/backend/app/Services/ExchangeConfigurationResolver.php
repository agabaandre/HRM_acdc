<?php

namespace App\Services;

use App\Models\BrandingSetting;
use App\Models\EmailProvider;
use App\Support\ConfigValue;

class ExchangeConfigurationResolver
{
    public const DEFAULT_FROM_NAME = 'Africa CDC Mailer';

    /**
     * Resolve Exchange settings from the email provider (DB) only.
     * Secrets are never read from .env — configure them in the admin UI.
     *
     * @return array<string, mixed>
     */
    public function resolve(?EmailProvider $provider = null): array
    {
        // When no provider is passed, use runtime config set by applyProvider().
        $runtime = $provider === null ? config('exchange-email', []) : [];

        return [
            'tenant_id' => ConfigValue::firstNonEmpty(
                $provider?->configValue('tenant_id'),
                $runtime['tenant_id'] ?? null,
            ),
            'client_id' => ConfigValue::firstNonEmpty(
                $provider?->configValue('client_id'),
                $runtime['client_id'] ?? null,
            ),
            'client_secret' => ConfigValue::firstNonEmpty(
                $provider?->configValue('client_secret'),
                $runtime['client_secret'] ?? null,
            ),
            'redirect_uri' => ConfigValue::firstNonEmpty(
                $provider?->configValue('redirect_uri'),
                $runtime['redirect_uri'] ?? null,
            ),
            'scope' => ConfigValue::firstNonEmpty(
                $provider?->configValue('scope'),
                $runtime['scope'] ?? null,
                'https://graph.microsoft.com/.default',
            ),
            'auth_method' => ConfigValue::firstNonEmpty(
                $provider?->configValue('auth_method'),
                $runtime['auth_method'] ?? null,
                'client_credentials',
            ),
            'from_email' => $this->resolveFromAddress($provider),
            'from_name' => $this->resolveFromName($provider),
        ];
    }

    public function resolveFromAddress(?EmailProvider $provider = null): ?string
    {
        $runtime = $provider === null ? config('exchange-email', []) : [];

        return ConfigValue::firstNonEmpty(
            $provider?->from_address,
            $runtime['from_email'] ?? null,
            BrandingSetting::query()->value('support_email'),
        );
    }

    public function resolveFromName(?EmailProvider $provider = null): string
    {
        $runtime = $provider === null ? config('exchange-email', []) : [];
        $brandingName = BrandingSetting::query()->value('app_name');

        return (string) (ConfigValue::firstNonEmpty(
            $provider?->from_name,
            $runtime['from_name'] ?? null,
            $brandingName,
        ) ?? self::DEFAULT_FROM_NAME);
    }

    public function isConfigured(?EmailProvider $provider = null): bool
    {
        $config = $this->resolve($provider);

        return ! empty($config['tenant_id'])
            && ! empty($config['client_id'])
            && ! empty($config['client_secret']);
    }
}
