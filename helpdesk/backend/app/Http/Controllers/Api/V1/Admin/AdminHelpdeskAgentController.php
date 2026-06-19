<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskProfile;
use App\Models\User;
use App\Services\AgentCategoryRoutingService;
use App\Services\HelpdeskPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHelpdeskAgentController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function __construct(
        private readonly HelpdeskPermissionService $permissions,
        private readonly AgentCategoryRoutingService $routing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $agents = User::query()
            ->actsAsHelpdeskAgent()
            ->with(['helpdeskProfile', 'helpdeskAgentCategories:id,name,slug', 'helpdeskSupportGroups:id,name,slug'])
            ->orderBy('name')
            ->get();

        $data = $agents->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'staff_id' => $u->helpdeskProfile?->staff_id,
            'duty_station' => $u->helpdeskProfile?->duty_station,
            'work_mode' => $u->helpdeskProfile?->work_mode,
            'work_mode_updated_at' => $u->helpdeskProfile?->work_mode_updated_at?->toIso8601String(),
            'can_manage_kb' => (bool) ($u->helpdeskProfile?->can_manage_kb),
            'can_reassign_tickets' => (bool) ($u->helpdeskProfile?->can_reassign_tickets),
            'grant_helpdesk_admin' => (bool) ($u->helpdeskProfile?->grant_helpdesk_admin),
            'grant_supervisor_access' => (bool) ($u->helpdeskProfile?->grant_supervisor_access),
            'role' => $u->helpdeskProfile?->role,
            'is_designated_agent' => (bool) ($u->helpdeskProfile?->is_designated_agent),
            'categories' => $u->helpdeskAgentCategories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]),
            'support_groups' => $u->helpdeskSupportGroups->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
            ]),
            'inherited_categories' => $this->routing->groupInheritedCategoriesForUser($u->id),
            'effective_categories' => $this->routing->inheritedCategoriesForUser($u->id),
        ]);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:helpdesk_categories,id'],
            'support_group_ids' => ['sometimes', 'array'],
            'support_group_ids.*' => ['integer', 'exists:helpdesk_support_groups,id'],
            'can_manage_kb' => ['sometimes', 'boolean'],
            'can_reassign_tickets' => ['sometimes', 'boolean'],
            'grant_helpdesk_admin' => ['sometimes', 'boolean'],
            'grant_supervisor_access' => ['sometimes', 'boolean'],
        ]);

        $profile = $user->helpdeskProfile;
        if (! $profile) {
            abort(422, 'User has no Helpdesk profile (must sign in via Staff SSO at least once).');
        }

        $profile->is_designated_agent = true;
        if (array_key_exists('can_manage_kb', $validated)) {
            $profile->can_manage_kb = (bool) $validated['can_manage_kb'];
        }
        if (array_key_exists('can_reassign_tickets', $validated)) {
            $profile->can_reassign_tickets = (bool) $validated['can_reassign_tickets'];
        }
        if (array_key_exists('grant_helpdesk_admin', $validated)) {
            $profile->grant_helpdesk_admin = (bool) $validated['grant_helpdesk_admin'];
        }
        if (array_key_exists('grant_supervisor_access', $validated)) {
            $profile->grant_supervisor_access = (bool) $validated['grant_supervisor_access'];
        }
        $this->permissions->syncEffectiveRole($profile);
        $profile->save();

        $user->helpdeskAgentCategories()->sync($validated['category_ids']);
        if (array_key_exists('support_group_ids', $validated)) {
            $user->helpdeskSupportGroups()->sync($validated['support_group_ids']);
        }
        $user->load(['helpdeskProfile', 'helpdeskAgentCategories:id,name', 'helpdeskSupportGroups:id,name,slug']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->helpdeskProfile?->staff_id,
                'can_manage_kb' => (bool) ($user->helpdeskProfile?->can_manage_kb),
                'can_reassign_tickets' => (bool) ($user->helpdeskProfile?->can_reassign_tickets),
                'grant_helpdesk_admin' => (bool) ($user->helpdeskProfile?->grant_helpdesk_admin),
                'grant_supervisor_access' => (bool) ($user->helpdeskProfile?->grant_supervisor_access),
                'role' => $user->helpdeskProfile?->role,
                'is_designated_agent' => (bool) ($user->helpdeskProfile?->is_designated_agent),
                'categories' => $user->helpdeskAgentCategories->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
                'support_groups' => $user->helpdeskSupportGroups->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'slug' => $g->slug,
                ]),
                'inherited_categories' => $this->routing->inheritedCategoriesForUser($user->id),
                'effective_categories' => $this->routing->inheritedCategoriesForUser($user->id),
            ],
        ]);
    }
}
