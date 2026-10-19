<?php

namespace App\Services;

use App\Enums\EmailDriver;
use App\Enums\UserApprovalStatus;
use App\Enums\UserRegistrationSource;
use App\Models\BrandingSetting;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class MigrationImportService
{
    public function __construct(
        private readonly MigrationPackageCipher $cipher,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope  Encrypted package (schema 2) or legacy plaintext (schema 1) — schema 1 rejected
     * @return array{
     *     providers: array{created: int, updated: int},
     *     users: array{created: int, updated: int},
     *     clients: array{created: int, updated: int},
     *     links_synced: int,
     *     branding_updated: bool,
     *     warnings: list<string>
     * }
     */
    public function import(array $envelope, string $encryptionKey): array
    {
        $package = $this->decryptEnvelope($envelope, $encryptionKey);

        $version = (int) data_get($package, 'meta.schema_version', 0);
        if ($version !== MigrationExportService::PAYLOAD_VERSION) {
            throw new InvalidArgumentException(
                'Unsupported migration payload schema_version. Expected '.MigrationExportService::PAYLOAD_VERSION.'.'
            );
        }

        $warnings = [];
        $summary = [
            'providers' => ['created' => 0, 'updated' => 0],
            'users' => ['created' => 0, 'updated' => 0],
            'clients' => ['created' => 0, 'updated' => 0],
            'links_synced' => 0,
            'branding_updated' => false,
            'warnings' => [],
        ];

        DB::transaction(function () use ($package, &$summary, &$warnings): void {
            $this->importProviders($package['email_providers'] ?? [], $summary, $warnings);
            $emailToId = $this->importUsers($package['users'] ?? [], $summary, $warnings);
            $this->resolveUserReferences($package['users'] ?? [], $emailToId);
            $slugToId = $this->importClients($package['external_integrations'] ?? [], $summary, $warnings);
            $summary['links_synced'] = $this->importLinks(
                $package['user_client_links'] ?? [],
                $emailToId,
                $slugToId,
                $warnings
            );
            $summary['branding_updated'] = $this->importBranding($package['branding'] ?? null, $warnings);
        });

        $summary['warnings'] = $warnings;

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function decryptEnvelope(array $envelope, string $encryptionKey): array
    {
        $version = (int) data_get($envelope, 'meta.schema_version', 0);
        if ($version !== MigrationExportService::SCHEMA_VERSION) {
            throw new InvalidArgumentException(
                'Unsupported migration package schema_version. Expected '.MigrationExportService::SCHEMA_VERSION.' (encrypted).'
            );
        }

        if (! data_get($envelope, 'meta.encrypted')) {
            throw new InvalidArgumentException('Migration package is not encrypted.');
        }

        $cipher = (string) data_get($envelope, 'meta.cipher', '');
        if ($cipher !== MigrationPackageCipher::CIPHER) {
            throw new InvalidArgumentException('Unsupported migration cipher.');
        }

        $rawKey = $this->cipher->decodeKey($encryptionKey);
        $plaintext = $this->cipher->decrypt(
            (string) ($envelope['ciphertext'] ?? ''),
            (string) ($envelope['nonce'] ?? ''),
            (string) ($envelope['tag'] ?? ''),
            $rawKey
        );

        $package = json_decode($plaintext, true);
        if (! is_array($package)) {
            throw new InvalidArgumentException('Decrypted migration payload is not valid JSON.');
        }

        return $package;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $warnings
     */
    private function importProviders(array $rows, array &$summary, array &$warnings): void
    {
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                $warnings[] = 'Skipped provider with empty slug.';

                continue;
            }

            $driver = (string) ($row['driver'] ?? '');
            if (! in_array($driver, EmailDriver::values(), true)) {
                $warnings[] = "Skipped provider “{$slug}”: unknown driver.";

                continue;
            }

            $provider = EmailProvider::query()->where('slug', $slug)->first();
            $isNew = $provider === null;
            if ($isNew) {
                $provider = new EmailProvider(['slug' => $slug]);
            }

            $provider->fill([
                'name' => (string) ($row['name'] ?? $slug),
                'slug' => $slug,
                'driver' => $driver,
                'from_address' => $row['from_address'] ?? null,
                'from_name' => $row['from_name'] ?? null,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'priority' => (int) ($row['priority'] ?? 100),
                'description' => $row['description'] ?? null,
            ]);

            $config = $row['config'] ?? [];
            if (is_array($config)) {
                $provider->setConfigSafely($config);
            }

            $makeDefault = (bool) ($row['is_default'] ?? false);
            if ($makeDefault) {
                EmailProvider::query()->where('slug', '!=', $slug)->update(['is_default' => false]);
                $provider->is_default = true;
            } elseif ($isNew) {
                $provider->is_default = false;
            }

            $provider->save();
            $summary['providers'][$isNew ? 'created' : 'updated']++;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $warnings
     * @return array<string, int> email => id
     */
    private function importUsers(array $rows, array &$summary, array &$warnings): array
    {
        $emailToId = User::query()->pluck('id', 'email')->map(fn ($id) => (int) $id)->all();

        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $warnings[] = 'Skipped user with invalid email.';

                continue;
            }

            $user = User::query()->where('email', $email)->first();
            $isNew = $user === null;
            if ($isNew) {
                $user = new User(['email' => $email]);
            }

            $status = (string) ($row['approval_status'] ?? UserApprovalStatus::Approved->value);
            if (! in_array($status, UserApprovalStatus::values(), true)) {
                $status = UserApprovalStatus::Approved->value;
            }

            $source = (string) ($row['registration_source'] ?? UserRegistrationSource::System->value);
            if (! in_array($source, UserRegistrationSource::values(), true)) {
                $source = UserRegistrationSource::System->value;
            }

            $user->fill([
                'name' => (string) ($row['name'] ?? $email),
                'email' => $email,
                'phone' => $row['phone'] ?? null,
                'organisation' => $row['organisation'] ?? null,
                'registration_source' => $source,
                'is_admin' => (bool) ($row['is_admin'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'approval_status' => $status,
                'approved_at' => $row['approved_at'] ?? null,
                'rejected_at' => $row['rejected_at'] ?? null,
                'rejection_reason' => $row['rejection_reason'] ?? null,
                'email_verified_at' => $row['email_verified_at'] ?? null,
                'two_factor_email_enabled' => (bool) ($row['two_factor_email_enabled'] ?? false),
                'two_factor_totp_enabled' => (bool) ($row['two_factor_totp_enabled'] ?? false),
                'totp_required' => (bool) ($row['totp_required'] ?? false),
                'two_factor_totp_secret' => $row['two_factor_totp_secret'] ?? null,
                'two_factor_totp_recovery_codes' => $row['two_factor_totp_recovery_codes'] ?? null,
            ]);

            $hash = (string) ($row['password_hash'] ?? '');
            if ($hash !== '') {
                if (Hash::isHashed($hash)) {
                    $user->password = $hash;
                } else {
                    $warnings[] = "User “{$email}”: password_hash was not a valid hash; left unchanged.";
                    if ($isNew) {
                        $user->password = Hash::make(str()->random(32));
                    }
                }
            } elseif ($isNew) {
                $user->password = Hash::make(str()->random(32));
                $warnings[] = "User “{$email}”: missing password_hash; generated a random password.";
            }

            $user->save();
            $emailToId[$email] = (int) $user->id;
            $summary['users'][$isNew ? 'created' : 'updated']++;
        }

        return $emailToId;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $emailToId
     */
    private function resolveUserReferences(array $rows, array $emailToId): void
    {
        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $userId = $emailToId[$email] ?? null;
            if ($userId === null) {
                continue;
            }

            $approvedByEmail = strtolower(trim((string) ($row['approved_by_email'] ?? '')));
            $createdByEmail = strtolower(trim((string) ($row['created_by_email'] ?? '')));

            User::query()->where('id', $userId)->update([
                'approved_by' => $approvedByEmail !== '' ? ($emailToId[$approvedByEmail] ?? null) : null,
                'created_by' => $createdByEmail !== '' ? ($emailToId[$createdByEmail] ?? null) : null,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     * @param  list<string>  $warnings
     * @return array<string, int> slug => id
     */
    private function importClients(array $rows, array &$summary, array &$warnings): array
    {
        $providerIds = EmailProvider::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
        $slugToId = ExternalIntegration::query()->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();

        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                $warnings[] = 'Skipped client with empty slug.';

                continue;
            }

            $hash = (string) ($row['api_key_hash'] ?? '');
            if ($hash === '') {
                $warnings[] = "Skipped client “{$slug}”: missing api_key_hash.";

                continue;
            }

            $providerSlug = trim((string) ($row['email_provider_slug'] ?? ''));
            $providerId = null;
            if ($providerSlug !== '') {
                $providerId = $providerIds[$providerSlug] ?? null;
                if ($providerId === null) {
                    $warnings[] = "Client “{$slug}”: provider slug “{$providerSlug}” not found; left unassigned.";
                }
            }

            $client = ExternalIntegration::query()->where('slug', $slug)->first();
            $isNew = $client === null;
            if ($isNew) {
                $client = new ExternalIntegration(['slug' => $slug]);
            }

            $client->fill([
                'name' => (string) ($row['name'] ?? $slug),
                'slug' => $slug,
                'api_key_hash' => $hash,
                'api_key_prefix' => (string) ($row['api_key_prefix'] ?? ''),
                'email_provider_id' => $providerId,
                'allowed_ips' => is_array($row['allowed_ips'] ?? null) ? $row['allowed_ips'] : [],
                'settings' => is_array($row['settings'] ?? null) ? $row['settings'] : [],
                'is_active' => (bool) ($row['is_active'] ?? false),
                'description' => $row['description'] ?? null,
            ]);
            $client->save();

            $slugToId[$slug] = (int) $client->id;
            $summary['clients'][$isNew ? 'created' : 'updated']++;
        }

        return $slugToId;
    }

    /**
     * @param  list<array{user_email?: string, client_slug?: string}>  $links
     * @param  array<string, int>  $emailToId
     * @param  array<string, int>  $slugToId
     * @param  list<string>  $warnings
     */
    private function importLinks(array $links, array $emailToId, array $slugToId, array &$warnings): int
    {
        $byUser = [];
        foreach ($links as $link) {
            $email = strtolower(trim((string) ($link['user_email'] ?? '')));
            $slug = trim((string) ($link['client_slug'] ?? ''));
            if ($email === '' || $slug === '') {
                continue;
            }
            if (! isset($emailToId[$email])) {
                $warnings[] = "Link skipped: user “{$email}” not found.";

                continue;
            }
            if (! isset($slugToId[$slug])) {
                $warnings[] = "Link skipped: client “{$slug}” not found.";

                continue;
            }
            $byUser[$email][] = $slugToId[$slug];
        }

        $synced = 0;
        foreach ($byUser as $email => $clientIds) {
            $user = User::query()->find($emailToId[$email]);
            if (! $user) {
                continue;
            }
            $user->externalIntegrations()->sync(array_values(array_unique($clientIds)));
            $synced += count(array_unique($clientIds));
        }

        return $synced;
    }

    /**
     * @param  array<string, mixed>|null  $branding
     * @param  list<string>  $warnings
     */
    private function importBranding(?array $branding, array &$warnings): bool
    {
        if ($branding === null) {
            return false;
        }

        $row = BrandingSetting::current();
        $row->fill([
            'app_name' => $branding['app_name'] ?? $row->app_name,
            'tagline' => $branding['tagline'] ?? $row->tagline,
            'admin_logo_inverse' => (bool) ($branding['admin_logo_inverse'] ?? $row->admin_logo_inverse),
            'admin_logo_size_percent' => (int) ($branding['admin_logo_size_percent'] ?? $row->admin_logo_size_percent ?: 100),
            'primary_color' => $branding['primary_color'] ?? $row->primary_color,
            'secondary_color' => $branding['secondary_color'] ?? $row->secondary_color,
            'support_email' => $branding['support_email'] ?? $row->support_email,
        ]);

        foreach (['logo' => 'logo_path', 'logo_dark' => 'logo_dark_path', 'favicon' => 'favicon_path'] as $key => $column) {
            $asset = $branding[$key] ?? null;
            if (! is_array($asset) || empty($asset['base64'])) {
                continue;
            }
            $path = $this->storeAsset($asset, $warnings);
            if ($path !== null) {
                $row->{$column} = $path;
            }
        }

        $row->save();

        return true;
    }

    /**
     * @param  array{filename?: string, mime?: string, base64?: string}  $asset
     * @param  list<string>  $warnings
     */
    private function storeAsset(array $asset, array &$warnings): ?string
    {
        $raw = base64_decode((string) ($asset['base64'] ?? ''), true);
        if ($raw === false || $raw === '') {
            $warnings[] = 'Skipped branding asset with invalid base64.';

            return null;
        }

        $filename = basename((string) ($asset['filename'] ?? 'asset.bin'));
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'asset.bin';
        }

        $path = 'branding/'.uniqid('mig_', true).'_'.$filename;
        Storage::disk('public')->put($path, $raw);

        return $path;
    }
}
