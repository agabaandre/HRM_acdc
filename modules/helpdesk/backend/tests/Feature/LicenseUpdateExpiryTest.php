<?php

namespace Tests\Feature;

use App\Models\HelpdeskLicense;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LicenseUpdateExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function licenseManager(): User
    {
        $user = User::factory()->create();
        HelpdeskProfile::query()->create([
            'user_id' => $user->id,
            'staff_id' => 910001,
            'role' => HelpdeskProfile::ROLE_ADMIN,
            'synced_at' => now(),
        ]);

        return $user->fresh(['helpdeskProfile']);
    }

    public function test_user_entered_expiry_date_is_kept_and_duration_is_derived(): void
    {
        Sanctum::actingAs($this->licenseManager());

        $license = HelpdeskLicense::query()->create([
            'name' => 'Office 365',
            'vendor' => 'Microsoft',
            'purchase_date' => '2024-01-15',
            'duration_months' => 12,
            'expiry_date' => '2025-01-15',
            'status' => 'active',
        ]);

        $this->putJson('/api/v1/tools/licenses/'.$license->id, [
            'purchase_date' => '2025-06-01',
            'expiry_date' => '2025-12-31',
        ])->assertOk();

        $fresh = $license->fresh();
        $this->assertSame('2025-06-01', $fresh->purchase_date?->format('Y-m-d'));
        $this->assertSame('2025-12-31', $fresh->expiry_date?->format('Y-m-d'));
        $this->assertSame(7, $fresh->duration_months);
    }

    public function test_changing_purchase_alone_does_not_overwrite_expiry(): void
    {
        Sanctum::actingAs($this->licenseManager());

        $license = HelpdeskLicense::query()->create([
            'name' => 'Zoom',
            'purchase_date' => '2025-01-01',
            'duration_months' => 12,
            'expiry_date' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->putJson('/api/v1/tools/licenses/'.$license->id, [
            'purchase_date' => '2025-03-01',
        ])->assertOk();

        $fresh = $license->fresh();
        $this->assertSame('2025-03-01', $fresh->purchase_date?->format('Y-m-d'));
        $this->assertSame('2026-01-01', $fresh->expiry_date?->format('Y-m-d'));
    }

    public function test_store_accepts_user_end_date(): void
    {
        Sanctum::actingAs($this->licenseManager());

        $this->postJson('/api/v1/tools/licenses', [
            'name' => 'Adobe',
            'purchase_date' => '2025-01-01',
            'expiry_date' => '2025-07-01',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Adobe');

        $row = HelpdeskLicense::query()->where('name', 'Adobe')->first();
        $this->assertNotNull($row);
        $this->assertSame('2025-07-01', $row->expiry_date?->format('Y-m-d'));
        $this->assertSame(6, $row->duration_months);
    }
}
