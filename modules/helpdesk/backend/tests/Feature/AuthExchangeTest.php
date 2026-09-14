<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthExchangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_returns_token_when_signature_valid(): void
    {
        $ts = time();
        $email = 'staff@example.org';
        $staffId = 555;
        $sig = hash_hmac('sha256', $staffId.'|'.$ts.'|'.strtolower($email), 'test-bridge-secret-for-phpunit');

        $response = $this->postJson('/api/v1/auth/exchange', [
            'staff_id' => $staffId,
            'email' => $email,
            'name' => 'Staff Member',
            'role' => 'user',
            'photo' => 'uploads/staff/snap.png',
            'ts' => $ts,
            'sig' => $sig,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'avatar_url', 'profile']]);

        $this->assertDatabaseHas('users', ['email' => $email, 'photo' => 'snap.png']);
        $this->assertDatabaseHas('helpdesk_profiles', ['staff_id' => $staffId, 'role' => 'user']);
        $this->assertNull(
            HelpdeskProfile::query()->where('staff_id', $staffId)->value('sap_no')
        );

        $url = $response->json('user.avatar_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('/api/v1/avatar/', $url);
        $this->assertStringContainsString('exp=', $url);
        $this->assertStringContainsString('sig=', $url);
    }

    public function test_exchange_rejects_bad_signature(): void
    {
        $ts = time();
        $email = 'bad@example.org';

        $response = $this->postJson('/api/v1/auth/exchange', [
            'staff_id' => 1,
            'email' => $email,
            'name' => 'X',
            'ts' => $ts,
            'sig' => str_repeat('0', 64),
        ]);

        $response->assertForbidden();
    }

    public function test_exchange_persists_sap_no_when_provided(): void
    {
        config(['helpdesk.bridge_secret' => 'test-bridge-secret-for-phpunit']);

        $ts = time();
        $email = 'sap-bridge@example.org';
        $staffId = 556;
        $sig = hash_hmac('sha256', $staffId.'|'.$ts.'|'.strtolower($email), 'test-bridge-secret-for-phpunit');

        $this->postJson('/api/v1/auth/exchange', [
            'staff_id' => $staffId,
            'email' => $email,
            'name' => 'SAP Bridge',
            'role' => 'user',
            'sap_no' => 'ACDC-7788',
            'ts' => $ts,
            'sig' => $sig,
        ])->assertOk()
            ->assertJsonPath('user.profile.sap_no', 'ACDC-7788');

        $this->assertDatabaseHas('helpdesk_profiles', [
            'staff_id' => $staffId,
            'sap_no' => 'ACDC-7788',
        ]);
    }
}
