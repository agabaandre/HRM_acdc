<?php

namespace App\Services;

use App\Enums\UserApprovalStatus;
use App\Enums\UserRegistrationSource;
use App\Models\BrandingSetting;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class MigrationExportService
{
    /** Outer envelope version (encrypted package). */
    public const SCHEMA_VERSION = 2;

    /** Inner plaintext payload version. */
    public const PAYLOAD_VERSION = 1;

    public function __construct(
        private readonly MigrationPackageCipher $cipher,
    ) {}

    public function filename(): string
    {
        return 'email-server-migration-'.now()->format('Ymd-His').'.json';
    }

    /**
     * Build plaintext migration payload (never written to disk unencrypted).
     *
     * @return array<string, mixed>
     */
    public function buildPayload(): array
    {
        return [
            'meta' => [
                'schema_version' => self::PAYLOAD_VERSION,
                'exported_at' => now()->toIso8601String(),
                'app_name' => (string) config('app.name', 'Email Server'),
                'source_app_url' => (string) config('app.url', ''),
            ],
            'email_providers' => $this->exportProviders(),
            'users' => $this->exportUsers(),
            'external_integrations' => $this->exportClients(),
            'user_client_links' => $this->exportLinks(),
            'branding' => $this->exportBranding(),
        ];
    }

    /**
     * @return array{
     *     encryption_key: string,
     *     filename: string,
     *     package: array<string, mixed>
     * }
     */
    public function buildEncryptedExport(): array
    {
        $payload = $this->buildPayload();
        $plaintext = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $key = $this->cipher->generateKey();
        $sealed = $this->cipher->encrypt($plaintext, $key['key_raw']);

        return [
            'encryption_key' => $key['key_encoded'],
            'filename' => $this->filename(),
            'package' => [
                'meta' => [
                    'schema_version' => self::SCHEMA_VERSION,
                    'encrypted' => true,
                    'cipher' => MigrationPackageCipher::CIPHER,
                    'kdf' => 'none',
                    'key_bytes' => MigrationPackageCipher::KEY_BYTES,
                    'exported_at' => data_get($payload, 'meta.exported_at'),
                    'app_name' => data_get($payload, 'meta.app_name'),
                    'source_app_url' => data_get($payload, 'meta.source_app_url'),
                ],
                'nonce' => $sealed['nonce'],
                'tag' => $sealed['tag'],
                'ciphertext' => $sealed['ciphertext'],
            ],
        ];
    }

    /**
     * @deprecated Use buildPayload() / buildEncryptedExport()
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return $this->buildPayload();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportProviders(): array
    {
        return EmailProvider::query()
            ->orderBy('id')
            ->get()
            ->map(function (EmailProvider $provider) {
                return [
                    'name' => $provider->name,
                    'slug' => $provider->slug,
                    'driver' => $provider->driver instanceof \App\Enums\EmailDriver
                        ? $provider->driver->value
                        : (string) $provider->getRawOriginal('driver'),
                    'config' => $provider->safeConfig(),
                    'from_address' => $provider->from_address,
                    'from_name' => $provider->from_name,
                    'is_default' => (bool) $provider->is_default,
                    'is_active' => (bool) $provider->is_active,
                    'priority' => (int) $provider->priority,
                    'description' => $provider->description,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportUsers(): array
    {
        $users = User::query()->orderBy('id')->get();
        $byId = $users->keyBy('id');

        return $users->map(function (User $user) use ($byId) {
            $approvedBy = $user->approved_by ? $byId->get($user->approved_by) : null;
            $createdBy = $user->created_by ? $byId->get($user->created_by) : null;

            return [
                'name' => $user->name,
                'email' => $user->email,
                'password_hash' => (string) $user->getRawOriginal('password'),
                'phone' => $user->phone,
                'organisation' => $user->organisation,
                'registration_source' => $user->registration_source instanceof UserRegistrationSource
                    ? $user->registration_source->value
                    : (string) ($user->registration_source ?? UserRegistrationSource::System->value),
                'is_admin' => (bool) $user->is_admin,
                'is_active' => (bool) $user->is_active,
                'approval_status' => $user->approval_status instanceof UserApprovalStatus
                    ? $user->approval_status->value
                    : (string) ($user->approval_status ?? UserApprovalStatus::Approved->value),
                'approved_at' => $user->approved_at?->toIso8601String(),
                'approved_by_email' => $approvedBy?->email,
                'created_by_email' => $createdBy?->email,
                'rejected_at' => $user->rejected_at?->toIso8601String(),
                'rejection_reason' => $user->rejection_reason,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'two_factor_email_enabled' => (bool) $user->two_factor_email_enabled,
                'two_factor_totp_enabled' => (bool) $user->two_factor_totp_enabled,
                'totp_required' => (bool) $user->totp_required,
                'two_factor_totp_secret' => $user->two_factor_totp_secret,
                'two_factor_totp_recovery_codes' => $user->two_factor_totp_recovery_codes,
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportClients(): array
    {
        return ExternalIntegration::query()
            ->with('emailProvider:id,slug')
            ->orderBy('id')
            ->get()
            ->map(function (ExternalIntegration $client) {
                return [
                    'name' => $client->name,
                    'slug' => $client->slug,
                    'api_key_hash' => $client->api_key_hash,
                    'api_key_prefix' => $client->api_key_prefix,
                    'email_provider_slug' => $client->emailProvider?->slug,
                    'allowed_ips' => $client->allowed_ips ?? [],
                    'settings' => $client->settings ?? [],
                    'is_active' => (bool) $client->is_active,
                    'description' => $client->description,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{user_email: string, client_slug: string}>
     */
    private function exportLinks(): array
    {
        $links = [];
        $users = User::query()->with('externalIntegrations:id,slug')->get();
        foreach ($users as $user) {
            foreach ($user->externalIntegrations as $client) {
                $links[] = [
                    'user_email' => $user->email,
                    'client_slug' => $client->slug,
                ];
            }
        }

        return $links;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportBranding(): array
    {
        $branding = BrandingSetting::current();

        return [
            'app_name' => $branding->app_name,
            'tagline' => $branding->tagline,
            'admin_logo_inverse' => (bool) $branding->admin_logo_inverse,
            'admin_logo_size_percent' => (int) ($branding->admin_logo_size_percent ?: 100),
            'primary_color' => $branding->primary_color,
            'secondary_color' => $branding->secondary_color,
            'support_email' => $branding->support_email,
            'logo' => $this->exportAsset($branding->logo_path),
            'logo_dark' => $this->exportAsset($branding->logo_dark_path),
            'favicon' => $this->exportAsset($branding->favicon_path),
        ];
    }

    /**
     * @return array{filename: string, mime: string, base64: string}|null
     */
    private function exportAsset(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        if ($normalized === '' || str_contains($normalized, '..')) {
            return null;
        }

        if (! Storage::disk('public')->exists($normalized)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($normalized);
        $mime = Storage::disk('public')->mimeType($normalized) ?: 'application/octet-stream';

        return [
            'filename' => basename($normalized),
            'mime' => $mime,
            'base64' => base64_encode($bytes),
        ];
    }
}
