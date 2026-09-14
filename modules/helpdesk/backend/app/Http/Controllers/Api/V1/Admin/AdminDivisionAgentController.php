<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\User;
use App\Services\StaffPortalReferenceClient;
use App\Support\StaffShareNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Backs the "View / designate division agents" panel on Settings → General.
 *
 * Staff directory data is read live from the Staff Share API (cached) — we
 * never duplicate it locally. The only helpdesk-side state is the nullable
 * `is_designated_agent` flag on `helpdesk_profiles` (added via migration
 * 2026_05_26_140000…), which locks the agent role across future SSO logins.
 */
class AdminDivisionAgentController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function candidates(Request $request, StaffPortalReferenceClient $client): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        if (! $client->isConfigured()) {
            return response()->json([
                'message' => 'Staff API credentials are not configured. Set them on Settings → Integrations.',
            ], 503);
        }

        $divisionIds = $this->resolveAgentDivisionIds();
        if ($divisionIds === []) {
            return response()->json([
                'data' => [
                    'division_ids' => [],
                    'divisions' => [],
                    'candidates' => [],
                ],
                'meta' => [
                    'message' => 'No default agent divisions are configured. Pick at least one above and save before viewing candidates.',
                ],
            ]);
        }

        $ttl = max(30, (int) config('helpdesk.reference_data_cache_ttl', 300));
        $limit = (int) config('helpdesk.staff_api.staff_fetch_limit', 5000);

        $bundle = Cache::remember('helpdesk_reference_bundle_v1', $ttl, function () use ($client) {
            return [
                'divisions' => array_map(fn (array $r) => StaffShareNormalizer::division($r), $client->fetchDivisions()),
                'directorates' => array_map(fn (array $r) => StaffShareNormalizer::directorate($r), $client->fetchDirectorates()),
            ];
        });

        $divisionById = collect($bundle['divisions'] ?? [])->keyBy('id');
        $selectedDivisions = $divisionById->only($divisionIds)->values()->all();

        $staffRows = Cache::remember('helpdesk_reference_staff_v1_'.$limit, $ttl, function () use ($client, $limit) {
            return $client->fetchStaff($limit, 0);
        });

        $candidates = [];
        $staffIdsInScope = [];
        $emailsInScope = [];
        foreach ($staffRows as $raw) {
            $row = StaffShareNormalizer::staff(is_array($raw) ? $raw : (array) $raw);
            $divId = (int) ($row['division_id'] ?? 0);
            if (! in_array($divId, $divisionIds, true)) {
                continue;
            }
            $candidates[] = $row;
            if ((int) ($row['id'] ?? 0) > 0) {
                $staffIdsInScope[] = (int) $row['id'];
            }
            if (! empty($row['work_email'])) {
                $emailsInScope[] = strtolower((string) $row['work_email']);
            }
        }

        // Pull existing helpdesk-side profile state in one query for both
        // staff_id and work_email so we can label rows accurately.
        $profilesByStaffId = HelpdeskProfile::query()
            ->with(['user:id,name,email'])
            ->whereIn('staff_id', array_unique($staffIdsInScope ?: [0]))
            ->get()
            ->keyBy('staff_id');

        $usersByEmail = User::query()
            ->whereIn('email', array_unique($emailsInScope ?: ['']))
            ->with('helpdeskProfile')
            ->get()
            ->keyBy(fn (User $u) => strtolower($u->email));

        $rows = [];
        foreach ($candidates as $row) {
            $staffId = (int) ($row['id'] ?? 0);
            $email = strtolower((string) ($row['work_email'] ?? ''));
            $divId = (int) ($row['division_id'] ?? 0);

            $profile = $profilesByStaffId->get($staffId);
            if (! $profile && $email !== '') {
                $u = $usersByEmail->get($email);
                $profile = $u?->helpdeskProfile;
            }

            $rows[] = [
                'staff_id' => $staffId,
                'name' => (string) ($row['name'] ?? trim(($row['fname'] ?? '').' '.($row['lname'] ?? ''))),
                'work_email' => $row['work_email'] ?? null,
                'duty_station_name' => $row['duty_station_name'] ?? null,
                'division_id' => $divId,
                'division_name' => $divisionById->get($divId)['name'] ?? ('Division '.$divId),
                'has_user' => $profile !== null,
                'current_role' => $profile?->role,
                'is_designated_agent' => $profile ? (bool) $profile->is_designated_agent : false,
                'last_synced_at' => $profile?->synced_at?->toIso8601String(),
            ];
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return response()->json([
            'data' => [
                'division_ids' => $divisionIds,
                'divisions' => $selectedDivisions,
                'candidates' => $rows,
            ],
            'meta' => [
                'count' => count($rows),
                'cache_ttl_seconds' => $ttl,
            ],
        ]);
    }

    public function designate(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'work_email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:191'],
            'division_id' => ['nullable', 'integer'],
            'duty_station' => ['nullable', 'string', 'max:191'],
        ]);

        $email = strtolower(trim($validated['work_email']));

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $validated['name'],
                'password' => Hash::make(Str::random(40)),
            ]
        );

        if ($user->name !== $validated['name']) {
            $user->forceFill(['name' => $validated['name']])->save();
        }

        // Designation pins the agent role for users who would otherwise be
        // 'user'. Never downgrade elevated roles (admin/supervisor/auditor)
        // — those stay as-is while still being "marked".
        $existing = $user->helpdeskProfile;
        $elevated = [
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_AUDITOR,
        ];
        $targetRole = ($existing && in_array($existing->role, $elevated, true))
            ? $existing->role
            : HelpdeskProfile::ROLE_AGENT;

        $profile = $user->helpdeskProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'staff_id' => (int) $validated['staff_id'],
                'role' => $targetRole,
                'is_designated_agent' => true,
                'division_id' => $validated['division_id'] ?? null,
                'duty_station' => $validated['duty_station'] ?? null,
            ]
        );

        return response()->json([
            'data' => [
                'staff_id' => $profile->staff_id,
                'work_email' => $user->email,
                'name' => $user->name,
                'role' => $profile->role,
                'is_designated_agent' => (bool) $profile->is_designated_agent,
                'has_user' => true,
            ],
        ]);
    }

    public function undesignate(Request $request, int $staffId): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $profile = HelpdeskProfile::query()->where('staff_id', $staffId)->first();
        if (! $profile) {
            return response()->json([
                'data' => [
                    'staff_id' => $staffId,
                    'is_designated_agent' => false,
                    'has_user' => false,
                ],
            ]);
        }

        $profile->is_designated_agent = false;
        // Only demote ROLE_AGENT to ROLE_USER. Admins / supervisors / auditors
        // retain their elevated role even after un-designation.
        if ($profile->role === HelpdeskProfile::ROLE_AGENT) {
            $profile->role = HelpdeskProfile::ROLE_USER;
        }
        $profile->save();

        return response()->json([
            'data' => [
                'staff_id' => $profile->staff_id,
                'role' => $profile->role,
                'is_designated_agent' => false,
                'has_user' => true,
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function resolveAgentDivisionIds(): array
    {
        $csv = HelpdeskSetting::getValue(HelpdeskSetting::KEY_DEFAULT_AGENT_DIVISION_IDS);
        if ($csv === null || trim((string) $csv) === '') {
            $csv = (string) config('helpdesk.default_agent_division_ids', '');
        }

        return collect(explode(',', (string) $csv))
            ->map(fn (string $v) => (int) trim($v))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
