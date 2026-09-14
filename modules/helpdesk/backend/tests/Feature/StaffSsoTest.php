<?php

namespace Tests\Feature;

use App\Models\HelpdeskProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSsoTest extends TestCase
{
    use RefreshDatabase;

    private function makeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $h.'.'.$p, $secret, true)), '+/', '-_'), '=');

        return $h.'.'.$p.'.'.$sig;
    }

    public function test_staff_sso_returns_token_when_jwt_valid_and_permission_allowed(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'staff_id' => 66001,
            'email' => 'sso-tester@example.org',
            'name' => 'SSO Tester',
            'permissions' => ['93', '84'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $secret);

        $response = $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'profile']]);

        $this->assertDatabaseHas('users', ['email' => 'sso-tester@example.org']);
        $this->assertDatabaseHas('helpdesk_profiles', [
            'staff_id' => 66001,
            'role' => HelpdeskProfile::ROLE_USER,
        ]);
    }

    public function test_staff_sso_maps_staff_portal_admin_role_to_helpdesk_admin(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'staff_id' => 66011,
            'email' => 'portal-admin@example.org',
            'name' => 'Portal Admin',
            'role' => 10,
            'permissions' => ['93'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $secret);

        $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt])
            ->assertOk()
            ->assertJsonPath('user.profile.role', HelpdeskProfile::ROLE_ADMIN);

        $this->assertDatabaseHas('helpdesk_profiles', [
            'staff_id' => 66011,
            'role' => HelpdeskProfile::ROLE_ADMIN,
        ]);
    }

    public function test_staff_sso_rejects_when_permission_missing(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'staff_id' => 66002,
            'email' => 'no-perm@example.org',
            'name' => 'No Perm',
            'permissions' => ['84'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $secret);

        $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt])->assertForbidden();
    }

    public function test_staff_sso_accepts_work_email_and_auth_staff_id(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'auth_staff_id' => 77007,
            'work_email' => 'field.ops@example.org',
            'fname' => 'Field',
            'lname' => 'Ops',
            'permissions' => [85, 93],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $secret);

        $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt])
            ->assertOk()
            ->assertJsonPath('user.email', 'field.ops@example.org');

        $this->assertDatabaseHas('helpdesk_profiles', ['staff_id' => 77007]);
    }

    public function test_staff_sso_rejects_bad_signature(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'staff_id' => 1,
            'email' => 'bad-sig@example.org',
            'name' => 'X',
            'permissions' => ['93'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], 'wrong-secret-not-the-same-as-config________');

        $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt])->assertForbidden();
    }

    public function test_staff_sso_maps_division_21_to_helpdesk_agent(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'staff_id' => 66012,
            'email' => 'div21@example.org',
            'name' => 'Division 21',
            'role' => 3,
            'division_id' => 21,
            'permissions' => ['93'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $secret);

        $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt])
            ->assertOk()
            ->assertJsonPath('user.profile.role', HelpdeskProfile::ROLE_AGENT);

        $this->assertDatabaseHas('helpdesk_profiles', [
            'staff_id' => 66012,
            'role' => HelpdeskProfile::ROLE_AGENT,
        ]);
    }

    public function test_staff_sso_persists_photo_and_returns_avatar_url(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'staff_id' => 77077,
            'email' => 'photo-sso@example.org',
            'name' => 'Photo User',
            'photo' => 'legacy/uploads/staff/face_1.jpg',
            'permissions' => ['93'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $secret);

        $res = $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'photo-sso@example.org', 'photo' => 'face_1.jpg']);

        $url = $res->json('user.avatar_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('/api/v1/avatar/', $url);
        $this->assertStringContainsString('exp=', $url);
        $this->assertStringContainsString('sig=', $url);
    }

    public function test_staff_sso_persists_sapno_from_portal_jwt_payload(): void
    {
        $secret = 'test-jwt-secret-for-phpunit-sso-verification-key-min-32';
        config(['helpdesk.jwt_secret' => $secret]);

        $now = time();
        $jwt = $this->makeJwt([
            'staff_id' => 66099,
            'email' => 'sap-sso@example.org',
            'name' => 'SAP SSO',
            'SAPNO' => '50001234',
            'permissions' => ['93'],
            'iat' => $now,
            'exp' => $now + 3600,
        ], $secret);

        $this->postJson('/api/v1/auth/staff-sso', ['token' => $jwt])
            ->assertOk()
            ->assertJsonPath('user.profile.sap_no', '50001234');

        $this->assertDatabaseHas('helpdesk_profiles', [
            'staff_id' => 66099,
            'sap_no' => '50001234',
        ]);
    }
}
