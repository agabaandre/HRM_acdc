<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StaffSsoRequest;
use App\Http\Resources\Api\V1\MeResource;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\User;
use App\Services\StaffPortalJwtService;
use App\Support\StaffSapNoFromPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StaffSsoController extends Controller
{
    public function __invoke(StaffSsoRequest $request, StaffPortalJwtService $jwt): JsonResponse
    {
        $secret = (string) config('helpdesk.jwt_secret', '');
        if ($secret === '') {
            abort(503, 'JWT_SECRET is not configured on the Helpdesk API (must match the Staff portal).');
        }

        $raw = trim((string) $request->validated('token'));

        try {
            $payload = $jwt->decodeVerified($raw, $secret);
        } catch (InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }

        if (! $this->payloadHasAllowedPermission($payload)) {
            abort(403, 'Your Staff portal profile does not include permission to open the Helpdesk.');
        }

        $staffId = $this->resolveStaffIdFromPayload($payload);
        if ($staffId <= 0) {
            abort(422, 'Staff session token is missing staff_id.');
        }

        $email = $this->resolveEmailFromPayload($payload);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            abort(422, 'Staff session token is missing a valid email (expected email, work_email, or mail from Staff session).');
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $name = trim(((string) ($payload['fname'] ?? '')).' '.((string) ($payload['lname'] ?? '')));
        }
        if ($name === '') {
            $name = 'Staff '.$staffId;
        }

        $role = $this->resolveHelpdeskRole($payload);

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(Str::random(40)),
            ]
        );

        $attrs = ['name' => $name];
        if (($photo = $this->normalizePhotoFromPayload($payload)) !== null) {
            $attrs['photo'] = $photo;
        }

        $user->forceFill($attrs)->save();

        $profileAttrs = [
            'staff_id' => $staffId,
            'role' => $role,
            'directorate_id' => isset($payload['directorate_id']) ? (int) $payload['directorate_id'] : null,
            'division_id' => isset($payload['division_id']) ? (int) $payload['division_id'] : null,
            'synced_at' => now(),
        ];
        if ($sapAttrs = StaffSapNoFromPayload::attributeIfPresent($payload)) {
            $profileAttrs = array_merge($profileAttrs, $sapAttrs);
        }

        $user->helpdeskProfile()->updateOrCreate(
            ['user_id' => $user->id],
            $profileAttrs
        );

        $user->tokens()->where('name', 'helpdesk-staff-sso')->delete();
        $plain = $user->createToken('helpdesk-staff-sso', ['helpdesk:*'])->plainTextToken;

        return response()->json([
            'token' => $plain,
            'token_type' => 'Bearer',
            'user' => new MeResource($user->fresh(['helpdeskProfile'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadHasAllowedPermission(array $payload): bool
    {
        $codes = collect(explode(',', (string) config('helpdesk.sso_permission_codes', '85,92,93')))
            ->map(fn (string $v) => trim($v))
            ->filter()
            ->values()
            ->all();

        if ($codes === []) {
            return false;
        }

        $perms = $this->normalizePermissionList($payload['permissions'] ?? null);

        foreach ($codes as $code) {
            if (in_array($code, $perms, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Staff session JWT is built from (array) $user — email is usually `work_email`, not `email`.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolveEmailFromPayload(array $payload): string
    {
        foreach (['email', 'work_email', 'private_email', 'mail', 'userPrincipalName'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $candidate = strtolower(trim((string) $payload[$key]));
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveStaffIdFromPayload(array $payload): int
    {
        foreach (['staff_id', 'auth_staff_id'] as $key) {
            if (isset($payload[$key]) && (int) $payload[$key] > 0) {
                return (int) $payload[$key];
            }
        }

        return 0;
    }

    /**
     * @return list<string>
     */
    private function normalizePermissionList(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            if (is_array($value) && isset($value['permission_id'])) {
                $out[] = (string) $value['permission_id'];

                continue;
            }
            if (is_int($key) && (is_string($value) || is_int($value))) {
                $out[] = (string) $value;
            }
        }

        if ($out === [] && $raw !== []) {
            return array_map('strval', array_values($raw));
        }

        return array_map('strval', $out);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveHelpdeskRole(array $payload): string
    {
        $claim = $payload['helpdesk_role'] ?? $payload['helpdeskRole'] ?? null;
        if (is_string($claim) && in_array($claim, [
            HelpdeskProfile::ROLE_USER,
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true)) {
            return $claim;
        }

        $adminRoleIds = collect(explode(',', (string) config('helpdesk.sso_staff_role_ids_admin', '10')))
            ->map(fn (string $v) => (int) trim($v))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $staffRole = (int) ($payload['role'] ?? 0);
        if ($staffRole > 0 && in_array($staffRole, $adminRoleIds, true)) {
            return HelpdeskProfile::ROLE_ADMIN;
        }

        $agentDivCsv = HelpdeskSetting::getValue(HelpdeskSetting::KEY_DEFAULT_AGENT_DIVISION_IDS);
        if ($agentDivCsv === null || trim($agentDivCsv) === '') {
            $agentDivCsv = (string) config('helpdesk.default_agent_division_ids', '21');
        }
        $agentDivIds = collect(explode(',', $agentDivCsv))
            ->map(fn (string $v) => (int) trim($v))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        $divisionId = (int) ($payload['division_id'] ?? 0);
        if ($divisionId > 0 && in_array($divisionId, $agentDivIds, true)) {
            return HelpdeskProfile::ROLE_AGENT;
        }

        return HelpdeskProfile::ROLE_USER;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizePhotoFromPayload(array $payload): ?string
    {
        $raw = $payload['photo'] ?? $payload['portrait'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $base = basename(str_replace('\\', '/', trim((string) $raw)));
        if ($base === '' || $base === '.' || $base === '..'
            || ! preg_match('/^[a-zA-Z0-9_.-]+$/', $base)) {
            return null;
        }

        return $base;
    }
}
