<?php

namespace Database\Seeders;

use App\Enums\EmailDriver;
use App\Models\BrandingSetting;
use App\Models\EmailProvider;
use App\Models\ExternalIntegration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        BrandingSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'app_name' => env('BRANDING_APP_NAME', 'Africa CDC DevTrack'),
                'tagline' => env('BRANDING_TAGLINE', 'Software delivery, assignment and reporting'),
                'logo_path' => 'branding/logo.png',
                'logo_dark_path' => 'branding/logo-dark.png',
                'primary_color' => env('BRANDING_PRIMARY_COLOR') ?: '#0d7a3a',
                'secondary_color' => env('BRANDING_SECONDARY_COLOR') ?: '#c9a227',
                'support_email' => env('BRANDING_SUPPORT_EMAIL') ?: 'notifications@africacdc.org',
            ],
        );

        // Provider shells only — secrets are set in the admin UI (encrypted in DB).
        // firstOrCreate so re-seed never overwrites existing credentials.
        $exchange = EmailProvider::query()->firstOrCreate(
            ['slug' => 'default-exchange'],
            [
                'name' => 'Default Exchange',
                'driver' => EmailDriver::Exchange,
                'config' => [
                    'tenant_id' => '',
                    'client_id' => '',
                    'client_secret' => '',
                    'redirect_uri' => '',
                    'scope' => 'https://graph.microsoft.com/.default',
                    'auth_method' => 'client_credentials',
                ],
                'from_address' => 'notifications@africacdc.org',
                'from_name' => 'Africa CDC Mailer',
                'is_default' => true,
                'is_active' => true,
                'priority' => 10,
                'description' => 'Microsoft Graph API — configure tenant/client secrets in the admin UI.',
            ],
        );

        EmailProvider::query()->firstOrCreate(
            ['slug' => 'fallback-smtp'],
            [
                'name' => 'SMTP Fallback',
                'driver' => EmailDriver::Smtp,
                'config' => [
                    'host' => '',
                    'port' => 465,
                    'encryption' => 'ssl',
                    'username' => '',
                    'password' => '',
                ],
                'from_address' => 'notifications@africacdc.org',
                'from_name' => 'Africa CDC Mailer',
                'is_default' => false,
                'is_active' => false,
                'priority' => 100,
                'description' => 'SMTP provider — configure host/credentials in the admin UI (not .env).',
            ],
        );

        EmailProvider::query()->firstOrCreate(
            ['slug' => 'dev-log'],
            [
                'name' => 'Development Log',
                'driver' => EmailDriver::Log,
                'config' => [],
                'from_address' => 'dev@localhost',
                'from_name' => 'Email Server Dev',
                'is_default' => false,
                'is_active' => true,
                'priority' => 999,
                'description' => 'Writes emails to Laravel log — for local testing.',
            ],
        );

        $clientSecret = env('INTEGRATION_CLIENT_SECRET') ?: bin2hex(random_bytes(24));

        ExternalIntegration::query()->updateOrCreate(
            ['slug' => 'staff-portal'],
            [
                'name' => 'Staff Portal',
                'api_key_hash' => ExternalIntegration::hashClientSecret($clientSecret),
                'api_key_prefix' => ExternalIntegration::clientSecretHint($clientSecret),
                'email_provider_id' => $exchange->id,
                'allowed_ips' => [],
                'settings' => ['source' => 'seed'],
                'is_active' => true,
                'description' => 'Example integration for external systems (APM, Helpdesk, etc.).',
            ],
        );

        $adminEmail = (string) env('ADMIN_EMAIL', 'andrewa@africacdc.org');
        $adminPassword = (string) env('ADMIN_PASSWORD', '');

        if ($adminPassword === '') {
            throw new \RuntimeException(
                'ADMIN_PASSWORD must be set (docker/.env / container env) before seeding.',
            );
        }

        // Always set the password from ADMIN_PASSWORD during seed so re-setup
        // matches the secrets file (firstOrCreate left old hashes in place).
        $admin = User::query()->updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
                'is_active' => true,
            ],
        );

        User::query()
            ->where('is_admin', true)
            ->whereNotIn('email', [$adminEmail, 'kibiyed@africacdc.org'])
            ->update(['is_active' => false]);

        if ($this->command) {
            $this->command->info('Admin ready: '.$admin->email.' (password set from ADMIN_PASSWORD)');
            $this->command->warn('Integration credentials — client_id: staff-portal | client_secret: '.$clientSecret);
        }

        $this->call(DevTrackSeeder::class);
    }
}
