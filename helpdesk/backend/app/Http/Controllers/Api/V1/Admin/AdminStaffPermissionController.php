<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskProfile;
use App\Models\User;
use App\Services\HelpdeskPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStaffPermissionController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function __construct(
        private readonly HelpdeskPermissionService $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $rows = User::query()
            ->whereHas('helpdeskProfile', fn ($q) => $q->withoutAgentDuties())
            ->with('helpdeskProfile')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (User $u) => $this->serializeRow($u)),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'grant_helpdesk_admin' => ['sometimes', 'boolean'],
            'grant_supervisor_access' => ['sometimes', 'boolean'],
            'can_manage_kb' => ['sometimes', 'boolean'],
            'can_reassign_tickets' => ['sometimes', 'boolean'],
            'can_delete_request_attachments' => ['sometimes', 'boolean'],
            'can_change_ticket_category' => ['sometimes', 'boolean'],
        ]);

        $profile = $user->helpdeskProfile;
        if (! $profile) {
            abort(422, 'User has no Helpdesk profile (must sign in via Staff SSO at least once).');
        }

        if ($profile->actsAsAgent()) {
            abort(422, 'Use Agents & category routing to manage agents.');
        }

        $this->applyPermissionFields($profile, $validated);
        $this->permissions->syncEffectiveRole($profile);
        $profile->save();

        $user->load('helpdeskProfile');

        return response()->json(['data' => $this->serializeRow($user)]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyPermissionFields(HelpdeskProfile $profile, array $validated): void
    {
        foreach ([
            'grant_helpdesk_admin',
            'grant_supervisor_access',
            'can_manage_kb',
            'can_reassign_tickets',
            'can_delete_request_attachments',
            'can_change_ticket_category',
        ] as $key) {
            if (array_key_exists($key, $validated)) {
                $profile->{$key} = (bool) $validated[$key];
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRow(User $user): array
    {
        $p = $user->helpdeskProfile;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'staff_id' => $p?->staff_id,
            'role' => $p?->role,
            'staff_portal_role' => $p?->staff_portal_role,
            'grant_helpdesk_admin' => (bool) ($p?->grant_helpdesk_admin),
            'grant_supervisor_access' => (bool) ($p?->grant_supervisor_access),
            'can_manage_kb' => (bool) ($p?->can_manage_kb),
            'can_reassign_tickets' => (bool) ($p?->can_reassign_tickets),
            'can_delete_request_attachments' => (bool) ($p?->can_delete_request_attachments),
            'can_change_ticket_category' => (bool) ($p?->can_change_ticket_category),
        ];
    }
}
