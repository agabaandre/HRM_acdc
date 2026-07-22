<?php

namespace Tests\Feature;

use App\Jobs\LicenseExpiryAlertJob;
use App\Mail\LicenseExpiryAlertMail;
use App\Models\HelpdeskLicense;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LicenseExpiryAlertJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_skips_when_alerts_disabled(): void
    {
        Mail::fake();
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED, '0');

        HelpdeskLicense::query()->create([
            'name' => 'Office',
            'expiry_date' => now()->addDays(5)->toDateString(),
            'warning_days_before' => 30,
            'status' => 'active',
        ]);

        (new LicenseExpiryAlertJob)->handle();

        Mail::assertNothingSent();
    }

    public function test_job_emails_admins_for_license_in_warning_window(): void
    {
        Mail::fake();
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED, '1');
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS, '7');

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        HelpdeskProfile::query()->create([
            'user_id' => $admin->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 1,
            'grant_helpdesk_admin' => true,
        ]);

        $license = HelpdeskLicense::query()->create([
            'name' => 'Adobe',
            'vendor' => 'Adobe',
            'expiry_date' => now()->addDays(10)->toDateString(),
            'warning_days_before' => 30,
            'status' => 'active',
        ]);

        (new LicenseExpiryAlertJob)->handle();

        Mail::assertSent(LicenseExpiryAlertMail::class, 1);
        $this->assertNotNull($license->fresh()->expiry_alert_last_sent_at);
    }

    public function test_job_respects_interval_and_skips_recent_alert(): void
    {
        Mail::fake();
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_ENABLED, '1');
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_LICENSE_EXPIRY_ALERT_INTERVAL_DAYS, '7');

        $admin = User::factory()->create(['email' => 'admin2@example.com']);
        HelpdeskProfile::query()->create([
            'user_id' => $admin->id,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'staff_id' => 2,
        ]);

        HelpdeskLicense::query()->create([
            'name' => 'Zoom',
            'expiry_date' => now()->addDays(3)->toDateString(),
            'warning_days_before' => 14,
            'status' => 'active',
            'expiry_alert_last_sent_at' => now()->subDays(2),
        ]);

        (new LicenseExpiryAlertJob)->handle();

        Mail::assertNothingSent();
    }
}
