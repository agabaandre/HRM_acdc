<?php

namespace Modules\Settings\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Settings\Models\PortalEmailProvider;

class EmailProvidersService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function driverDefinitions(): array
    {
        $smtpFields = [
            ['key' => 'host', 'label' => 'SMTP host', 'type' => 'text', 'required' => true, 'placeholder' => 'smtp.example.org'],
            ['key' => 'port', 'label' => 'Port', 'type' => 'number', 'required' => true, 'default' => '587'],
            ['key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'required' => false, 'default' => 'tls', 'options' => ['tls', 'ssl', 'none']],
            ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => false],
            ['key' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => false, 'secret' => true],
        ];

        return [
            [
                'key' => 'exchange',
                'label' => 'Microsoft Exchange / Graph',
                'category' => 'Microsoft',
                'description' => 'Send via Microsoft Graph using an Entra ID app registration. Empty fields fall back to EXCHANGE_* env.',
                'fields' => [
                    ['key' => 'tenant_id', 'label' => 'Tenant ID', 'type' => 'text', 'required' => false],
                    ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'required' => false],
                    ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'required' => false, 'secret' => true],
                    ['key' => 'scope', 'label' => 'Scope', 'type' => 'text', 'required' => false, 'default' => 'https://graph.microsoft.com/.default'],
                    ['key' => 'auth_method', 'label' => 'Auth method', 'type' => 'select', 'required' => false, 'default' => 'client_credentials', 'options' => ['client_credentials', 'authorization_code']],
                    ['key' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'key' => 'smtp',
                'label' => 'SMTP',
                'category' => 'Generic',
                'description' => 'Any standard SMTP server. Empty fields fall back to MAIL_* env.',
                'fields' => $smtpFields,
            ],
            [
                'key' => 'ses',
                'label' => 'Amazon SES',
                'category' => 'AWS',
                'description' => 'Amazon Simple Email Service API credentials.',
                'fields' => [
                    ['key' => 'access_key_id', 'label' => 'AWS access key ID', 'type' => 'text', 'required' => true],
                    ['key' => 'secret_access_key', 'label' => 'AWS secret access key', 'type' => 'password', 'required' => true, 'secret' => true],
                    ['key' => 'region', 'label' => 'Region', 'type' => 'text', 'required' => true, 'default' => 'us-east-1'],
                    ['key' => 'configuration_set', 'label' => 'Configuration set', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'key' => 'azure',
                'label' => 'Azure Communication Services',
                'category' => 'Microsoft',
                'description' => 'Send email through Azure Communication Services.',
                'fields' => [
                    ['key' => 'connection_string', 'label' => 'Connection string', 'type' => 'password', 'required' => false, 'secret' => true],
                    ['key' => 'endpoint', 'label' => 'Endpoint (optional)', 'type' => 'text', 'required' => false],
                    ['key' => 'access_key', 'label' => 'Access key (optional)', 'type' => 'password', 'required' => false, 'secret' => true],
                ],
            ],
            [
                'key' => 'google',
                'label' => 'Google / Gmail API',
                'category' => 'Google',
                'description' => 'Gmail API for Google Workspace or consumer Gmail (OAuth or service account).',
                'fields' => [
                    ['key' => 'auth_type', 'label' => 'Auth type', 'type' => 'select', 'required' => true, 'default' => 'oauth', 'options' => ['oauth', 'service_account']],
                    ['key' => 'client_id', 'label' => 'OAuth client ID', 'type' => 'text', 'required' => false],
                    ['key' => 'client_secret', 'label' => 'OAuth client secret', 'type' => 'password', 'required' => false, 'secret' => true],
                    ['key' => 'refresh_token', 'label' => 'OAuth refresh token', 'type' => 'password', 'required' => false, 'secret' => true],
                    ['key' => 'service_account_json', 'label' => 'Service account JSON', 'type' => 'textarea', 'required' => false, 'secret' => true],
                    ['key' => 'impersonate_user', 'label' => 'Impersonate user (Workspace)', 'type' => 'text', 'required' => false],
                ],
            ],
            [
                'key' => 'zoho',
                'label' => 'Zoho Mail',
                'category' => 'Zoho',
                'description' => 'Zoho Mail API or Zoho SMTP credentials.',
                'fields' => [
                    ['key' => 'mode', 'label' => 'Mode', 'type' => 'select', 'required' => true, 'default' => 'smtp', 'options' => ['api', 'smtp']],
                    ['key' => 'client_id', 'label' => 'Client ID (API)', 'type' => 'text', 'required' => false],
                    ['key' => 'client_secret', 'label' => 'Client secret (API)', 'type' => 'password', 'required' => false, 'secret' => true],
                    ['key' => 'refresh_token', 'label' => 'Refresh token (API)', 'type' => 'password', 'required' => false, 'secret' => true],
                    ['key' => 'account_id', 'label' => 'Account ID (API)', 'type' => 'text', 'required' => false],
                    ['key' => 'host', 'label' => 'SMTP host', 'type' => 'text', 'required' => false, 'default' => 'smtp.zoho.com'],
                    ['key' => 'port', 'label' => 'SMTP port', 'type' => 'number', 'required' => false, 'default' => '587'],
                    ['key' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'required' => false, 'default' => 'tls', 'options' => ['tls', 'ssl', 'none']],
                    ['key' => 'username', 'label' => 'SMTP username', 'type' => 'text', 'required' => false],
                    ['key' => 'password', 'label' => 'SMTP password / app password', 'type' => 'password', 'required' => false, 'secret' => true],
                ],
            ],
            [
                'key' => 'sendgrid',
                'label' => 'SendGrid',
                'category' => 'Transactional',
                'description' => 'Twilio SendGrid Web API.',
                'fields' => [
                    ['key' => 'api_key', 'label' => 'API key', 'type' => 'password', 'required' => true, 'secret' => true],
                    ['key' => 'base_url', 'label' => 'API base URL', 'type' => 'text', 'required' => false, 'default' => 'https://api.sendgrid.com/v3'],
                ],
            ],
            [
                'key' => 'mailgun',
                'label' => 'Mailgun',
                'category' => 'Transactional',
                'description' => 'Mailgun Messages API.',
                'fields' => [
                    ['key' => 'api_key', 'label' => 'API key', 'type' => 'password', 'required' => true, 'secret' => true],
                    ['key' => 'domain', 'label' => 'Sending domain', 'type' => 'text', 'required' => true],
                    ['key' => 'region', 'label' => 'Region', 'type' => 'select', 'required' => false, 'default' => 'us', 'options' => ['us', 'eu']],
                ],
            ],
            [
                'key' => 'postmark',
                'label' => 'Postmark',
                'category' => 'Transactional',
                'description' => 'Postmark server API token.',
                'fields' => [
                    ['key' => 'server_token', 'label' => 'Server API token', 'type' => 'password', 'required' => true, 'secret' => true],
                    ['key' => 'message_stream', 'label' => 'Message stream', 'type' => 'text', 'required' => false, 'default' => 'outbound'],
                ],
            ],
            [
                'key' => 'mailjet',
                'label' => 'Mailjet',
                'category' => 'Transactional',
                'description' => 'Mailjet Send API v3.1 credentials.',
                'fields' => [
                    ['key' => 'api_key', 'label' => 'API key', 'type' => 'text', 'required' => true],
                    ['key' => 'secret_key', 'label' => 'Secret key', 'type' => 'password', 'required' => true, 'secret' => true],
                ],
            ],
            [
                'key' => 'api',
                'label' => 'Custom HTTP API',
                'category' => 'Generic',
                'description' => 'Generic HTTP mail gateway.',
                'fields' => [
                    ['key' => 'base_url', 'label' => 'API base URL', 'type' => 'text', 'required' => true],
                    ['key' => 'send_path', 'label' => 'Send path', 'type' => 'text', 'required' => false, 'default' => '/mail/send'],
                    ['key' => 'api_key', 'label' => 'API key', 'type' => 'password', 'required' => true, 'secret' => true],
                    ['key' => 'auth_scheme', 'label' => 'Auth scheme', 'type' => 'select', 'required' => false, 'default' => 'bearer', 'options' => ['bearer', 'api_key_header', 'basic']],
                    ['key' => 'auth_header', 'label' => 'API key header name', 'type' => 'text', 'required' => false, 'default' => 'X-Api-Key'],
                ],
            ],
            [
                'key' => 'log',
                'label' => 'Log (dev)',
                'category' => 'Generic',
                'description' => 'Write messages to the application log instead of sending.',
                'fields' => [],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function secretConfigKeys(): array
    {
        $keys = [];
        foreach ($this->driverDefinitions() as $driver) {
            foreach ($driver['fields'] as $field) {
                if (! empty($field['secret'])) {
                    $keys[] = $field['key'];
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return PortalEmailProvider::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (PortalEmailProvider $p) => $this->present($p))
            ->all();
    }

    public function findByUuid(string $uuid): PortalEmailProvider
    {
        return PortalEmailProvider::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function defaultProvider(): ?PortalEmailProvider
    {
        return PortalEmailProvider::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? PortalEmailProvider::query()->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PortalEmailProvider
    {
        $this->assertDriver($data['driver'] ?? '');

        return DB::transaction(function () use ($data) {
            $row = PortalEmailProvider::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => trim((string) $data['name']),
                'slug' => $this->uniqueSlug((string) ($data['slug'] ?? $data['name'])),
                'driver' => (string) $data['driver'],
                'config' => $data['config'] ?? [],
                'from_address' => (string) ($data['from_address'] ?? ''),
                'from_name' => (string) ($data['from_name'] ?? ''),
                'description' => $data['description'] ?? null,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            if ($row->is_default) {
                $this->clearOtherDefaults($row->id);
            }

            return $row->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PortalEmailProvider $provider, array $data): PortalEmailProvider
    {
        return DB::transaction(function () use ($provider, $data) {
            $config = $provider->config ?? [];
            if (isset($data['config']) && is_array($data['config'])) {
                foreach ($data['config'] as $key => $value) {
                    if ($value === '********' || $value === null) {
                        continue;
                    }
                    $config[$key] = $value;
                }
            }

            $provider->update([
                'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $provider->name,
                'from_address' => array_key_exists('from_address', $data) ? (string) $data['from_address'] : $provider->from_address,
                'from_name' => array_key_exists('from_name', $data) ? (string) $data['from_name'] : $provider->from_name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $provider->description,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $provider->is_active,
                'is_default' => array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $provider->is_default,
                'config' => $config,
            ]);

            if ($provider->is_default) {
                $this->clearOtherDefaults($provider->id);
            }

            return $provider->fresh();
        });
    }

    public function delete(PortalEmailProvider $provider): void
    {
        if ($provider->is_default) {
            throw ValidationException::withMessages(['provider' => 'Cannot delete the default email provider. Set another default first.']);
        }
        $provider->delete();
    }

    public function setDefault(PortalEmailProvider $provider): PortalEmailProvider
    {
        return DB::transaction(function () use ($provider) {
            $provider->update(['is_default' => true, 'is_active' => true]);
            $this->clearOtherDefaults($provider->id);

            return $provider->fresh();
        });
    }

    /**
     * Merge provider config with env fallbacks for Exchange / SMTP.
     *
     * @return array{provider: PortalEmailProvider, config: array<string, mixed>, from_address: string, from_name: string}
     */
    public function resolveForSend(?PortalEmailProvider $provider = null): array
    {
        $provider ??= $this->defaultProvider();
        if (! $provider) {
            // Synthetic exchange from env
            $synthetic = new PortalEmailProvider([
                'name' => 'Env Exchange',
                'slug' => 'env-exchange',
                'driver' => 'exchange',
                'config' => [],
                'from_address' => (string) config('mail.from.address'),
                'from_name' => (string) config('mail.from.name'),
                'is_default' => true,
                'is_active' => true,
            ]);

            return [
                'provider' => $synthetic,
                'config' => $this->withEnvFallbacks('exchange', []),
                'from_address' => (string) config('mail.from.address'),
                'from_name' => (string) config('mail.from.name'),
            ];
        }

        $config = $this->withEnvFallbacks($provider->driver, $provider->config ?? []);

        return [
            'provider' => $provider,
            'config' => $config,
            'from_address' => $provider->from_address ?: (string) config('mail.from.address'),
            'from_name' => $provider->from_name ?: (string) config('mail.from.name'),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function withEnvFallbacks(string $driver, array $config): array
    {
        $filled = $config;
        foreach ($filled as $k => $v) {
            if ($v === null || $v === '') {
                unset($filled[$k]);
            }
        }

        if ($driver === 'exchange') {
            $filled += array_filter([
                'tenant_id' => config('exchange-email.tenant_id'),
                'client_id' => config('exchange-email.client_id'),
                'client_secret' => config('exchange-email.client_secret'),
                'scope' => config('exchange-email.scope'),
                'auth_method' => config('exchange-email.auth_method'),
                'redirect_uri' => config('exchange-email.redirect_uri'),
            ], fn ($v) => $v !== null && $v !== '');
        }

        if ($driver === 'smtp') {
            $filled += array_filter([
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'username' => config('mail.mailers.smtp.username'),
                'password' => config('mail.mailers.smtp.password'),
                'encryption' => config('mail.mailers.smtp.scheme') === 'smtps' ? 'ssl' : 'tls',
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $filled;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(PortalEmailProvider $provider): array
    {
        $config = $provider->config ?? [];
        foreach ($this->secretConfigKeys() as $key) {
            if (! empty($config[$key])) {
                $config[$key] = '********';
            }
        }

        return [
            'id' => $provider->id,
            'uuid' => $provider->uuid,
            'name' => $provider->name,
            'slug' => $provider->slug,
            'driver' => $provider->driver,
            'config' => $config,
            'from_address' => $provider->from_address,
            'from_name' => $provider->from_name,
            'description' => $provider->description,
            'is_default' => $provider->is_default,
            'is_active' => $provider->is_active,
            'created_at' => $provider->created_at,
            'updated_at' => $provider->updated_at,
        ];
    }

    private function assertDriver(string $driver): void
    {
        $keys = array_column($this->driverDefinitions(), 'key');
        if (! in_array($driver, $keys, true)) {
            throw ValidationException::withMessages(['driver' => 'Unsupported email driver.']);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'provider';
        $slug = $base;
        $i = 1;
        while (PortalEmailProvider::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function clearOtherDefaults(int $keepId): void
    {
        PortalEmailProvider::query()->where('id', '!=', $keepId)->update(['is_default' => false]);
    }
}
