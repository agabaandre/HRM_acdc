<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHelpdeskAgentController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $agents = User::query()
            ->whereHas('helpdeskProfile', fn ($q) => $q->where('role', HelpdeskProfile::ROLE_AGENT))
            ->with(['helpdeskProfile', 'helpdeskAgentCategories:id,name,slug'])
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
            'categories' => $u->helpdeskAgentCategories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]),
        ]);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:helpdesk_categories,id'],
            'can_manage_kb' => ['sometimes', 'boolean'],
            'can_reassign_tickets' => ['sometimes', 'boolean'],
        ]);

        $profile = $user->helpdeskProfile;
        if (! $profile) {
            abort(422, 'User has no Helpdesk profile (must sign in via Staff SSO at least once).');
        }

        if ($profile->role !== HelpdeskProfile::ROLE_AGENT) {
            $profile->role = HelpdeskProfile::ROLE_AGENT;
        }
        if (array_key_exists('can_manage_kb', $validated)) {
            $profile->can_manage_kb = (bool) $validated['can_manage_kb'];
        }
        if (array_key_exists('can_reassign_tickets', $validated)) {
            $profile->can_reassign_tickets = (bool) $validated['can_reassign_tickets'];
        }
        $profile->save();

        $user->helpdeskAgentCategories()->sync($validated['category_ids']);
        $user->load(['helpdeskProfile', 'helpdeskAgentCategories:id,name']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'staff_id' => $user->helpdeskProfile?->staff_id,
                'can_manage_kb' => (bool) ($user->helpdeskProfile?->can_manage_kb),
                'can_reassign_tickets' => (bool) ($user->helpdeskProfile?->can_reassign_tickets),
                'categories' => $user->helpdeskAgentCategories->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ]),
            ],
        ]);
    }
}
