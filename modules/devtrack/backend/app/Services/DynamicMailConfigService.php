<?php

namespace App\Services;

use App\Enums\EmailDriver;
use App\Models\EmailProvider;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class DynamicMailConfigService
{
    public function __construct(
        private readonly ExchangeConfigurationResolver $exchangeResolver,
    ) {}

    public function defaultProvider(): ?EmailProvider
    {
        return EmailProvider::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('priority')
            ->first()
            ?? EmailProvider::query()
                ->where('is_active', true)
                ->orderBy('priority')
                ->first();
    }

    public function resolveProvider(?int $providerId = null): EmailProvider
    {
        if ($providerId !== null) {
            $provider = EmailProvider::query()
                ->where('is_active', true)
                ->find($providerId);

            if ($provider === null) {
                throw new RuntimeException('Email provider not found or inactive.');
            }

            return $provider;
        }

        $provider = $this->defaultProvider();

        if ($provider === null) {
            throw new RuntimeException('No active email provider configured.');
        }

        return $provider;
    }

    /**
     * @return array{address: string|null, name: string}
     */
    public function resolveFromIdentity(EmailProvider $provider): array
    {
        return [
            'address' => $this->exchangeResolver->resolveFromAddress($provider),
            'name' => $this->exchangeResolver->resolveFromName($provider),
        ];
    }

    public function applyProvider(EmailProvider $provider): string
    {
        $mailerName = 'provider_'.$provider->id;
        $from = $this->resolveFromIdentity($provider);

        $this->registerMailer($provider, $mailerName);

        Config::set('mail.from.address', $from['address']);
        Config::set('mail.from.name', $from['name']);
        Config::set('mail.default', $mailerName);

        if ($provider->driver === EmailDriver::Exchange) {
            Config::set('exchange-email', $this->exchangeResolver->resolve($provider));
        }

        return $mailerName;
    }

    private function registerMailer(EmailProvider $provider, string $mailerName): void
    {
        $mailers = Config::get('mail.mailers', []);

        $mailers[$mailerName] = match ($provider->driver) {
            EmailDriver::Exchange => ['transport' => 'exchange'],
            EmailDriver::Smtp => [
                'transport' => 'smtp',
                'host' => $provider->configValue('host') ?: null,
                'port' => (int) ($provider->configValue('port') ?: 587),
                'encryption' => $provider->configValue('encryption') ?: null,
                'username' => $provider->configValue('username') ?: null,
                'password' => $provider->configValue('password') ?: null,
                'timeout' => null,
                'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
            ],
            EmailDriver::Ses => ['transport' => 'ses'],
            EmailDriver::Log => [
                'transport' => 'log',
                'channel' => $provider->configValue('channel'),
            ],
        };

        Config::set('mail.mailers', $mailers);
    }

    public function purgeExchangeClient(): void
    {
        app()->forgetInstance(ExchangeGraphMailClient::class);
    }
}
