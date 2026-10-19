<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserApprovalStatus;
use App\Enums\UserRegistrationSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\ApprovalNotifier;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'approval_status' => ['sometimes', 'nullable', Rule::in(UserApprovalStatus::values())],
            'registration_source' => ['sometimes', 'nullable', Rule::in(UserRegistrationSource::values())],
        ]);

        $users = User::query()
            ->with(['externalIntegrations:id,name,slug'])
            ->when(
                $request->filled('approval_status'),
                fn ($q) => $q->where('approval_status', $request->query('approval_status'))
            )
            ->when(
                $request->filled('registration_source'),
                fn ($q) => $q->where('registration_source', $request->query('registration_source'))
            )
            ->orderByRaw("CASE approval_status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->transform($user));

        return response()->json([
            'data' => $users,
            'meta' => [
                'pending_count' => User::query()->where('approval_status', UserApprovalStatus::Pending)->count(),
                'approved_count' => User::query()->where('approval_status', UserApprovalStatus::Approved)->count(),
                'rejected_count' => User::query()->where('approval_status', UserApprovalStatus::Rejected)->count(),
                'public_count' => User::query()->where('registration_source', UserRegistrationSource::Public)->count(),
                'system_count' => User::query()->where('registration_source', UserRegistrationSource::System)->count(),
                'total_count' => User::query()->count(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, AuditLogService $audit): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'organisation' => $data['organisation'] ?? null,
            'registration_source' => UserRegistrationSource::System,
            'is_admin' => $data['is_admin'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'approval_status' => UserApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $request->user()?->id,
            'created_by' => $request->user()?->id,
            // Admin-created accounts must enroll an authenticator on first sign-in.
            'totp_required' => true,
        ]);

        $this->syncIntegrations($user, $data);

        $audit->logRecordChange('created', 'users', $user->id, null, [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_active' => $user->is_active,
            'approval_status' => UserApprovalStatus::Approved->value,
            'registration_source' => UserRegistrationSource::System->value,
        ]);

        return response()->json([
            'data' => $this->transform($user->fresh()->load(['externalIntegrations:id,name,slug'])),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['externalIntegrations:id,name,slug']);

        return response()->json(['data' => $this->transform($user)]);
    }

    public function update(UpdateUserRequest $request, User $user, AuditLogService $audit): JsonResponse
    {
        $data = $request->validated();
        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_active' => $user->is_active,
        ];

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $integrationIdsProvided = array_key_exists('external_integration_ids', $data);
        $integrationIds = $data['external_integration_ids'] ?? null;
        unset($data['external_integration_ids']);

        $user->update($data);

        if ($integrationIdsProvided || array_key_exists('is_admin', $data)) {
            $this->syncIntegrations($user, [
                'is_admin' => $user->is_admin,
                'external_integration_ids' => $integrationIdsProvided
                    ? ($integrationIds ?? [])
                    : $user->externalIntegrations()->pluck('external_integrations.id')->all(),
            ]);
        }

        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            $user->tokens()->delete();
        }

        $user->refresh();
        $audit->logRecordChange('updated', 'users', $user->id, $before, [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_active' => $user->is_active,
            'password_changed' => array_key_exists('password', $data),
        ]);

        return response()->json([
            'data' => $this->transform($user->fresh()->load(['externalIntegrations:id,name,slug'])),
        ]);
    }

    public function destroy(Request $request, User $user, AuditLogService $audit): JsonResponse
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        if ($user->is_admin) {
            return response()->json(['message' => 'Admin accounts cannot be deleted from this screen. Disable them instead.'], 422);
        }

        $status = $user->approval_status instanceof UserApprovalStatus
            ? $user->approval_status
            : UserApprovalStatus::tryFrom((string) $user->approval_status);

        if ($status !== UserApprovalStatus::Rejected) {
            return response()->json([
                'message' => 'Only rejected accounts can be deleted. Disable active accounts or reject pending ones first.',
            ], 422);
        }

        $snapshot = [
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => $user->is_admin,
            'is_active' => $user->is_active,
            'approval_status' => UserApprovalStatus::Rejected->value,
        ];
        $id = $user->id;

        $user->tokens()->delete();
        $user->delete();

        $audit->logRecordChange('deleted', 'users', $id, $snapshot, null);

        return response()->json(['message' => 'Rejected account deleted.']);
    }

    public function approve(Request $request, User $user, AuditLogService $audit, ApprovalNotifier $notifier): JsonResponse
    {
        if ($user->is_admin) {
            return response()->json(['message' => 'Admin accounts do not require approval.'], 422);
        }

        $before = [
            'approval_status' => $user->approval_status instanceof UserApprovalStatus
                ? $user->approval_status->value
                : (string) $user->approval_status,
            'is_active' => $user->is_active,
        ];

        $user->update([
            'approval_status' => UserApprovalStatus::Approved,
            'is_active' => true,
            'totp_required' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()?->id,
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);

        $audit->log('User account approved', [
            'event_type' => 'user_approved',
            'user' => $request->user(),
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'users',
            'target_id' => $user->id,
            'old_values' => $before,
            'new_values' => [
                'approval_status' => UserApprovalStatus::Approved->value,
                'is_active' => true,
            ],
        ]);

        $notifier->notifyAccountApproved($user->fresh());

        return response()->json([
            'message' => 'Account approved.',
            'data' => $this->transform($user->fresh()->load(['externalIntegrations:id,name,slug'])),
        ]);
    }

    public function reject(Request $request, User $user, AuditLogService $audit, ApprovalNotifier $notifier): JsonResponse
    {
        if ($user->is_admin) {
            return response()->json(['message' => 'Admin accounts cannot be rejected.'], 422);
        }

        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ])['reason'] ?? null;

        $before = [
            'approval_status' => $user->approval_status instanceof UserApprovalStatus
                ? $user->approval_status->value
                : (string) $user->approval_status,
            'is_active' => $user->is_active,
        ];

        $user->update([
            'approval_status' => UserApprovalStatus::Rejected,
            'is_active' => false,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
        $user->tokens()->delete();

        $audit->log('User account rejected', [
            'event_type' => 'user_rejected',
            'user' => $request->user(),
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'users',
            'target_id' => $user->id,
            'old_values' => $before,
            'new_values' => [
                'approval_status' => UserApprovalStatus::Rejected->value,
                'rejection_reason' => $reason,
            ],
        ]);

        $notifier->notifyAccountRejected($user->fresh(), $reason);

        return response()->json([
            'message' => 'Account rejected.',
            'data' => $this->transform($user->fresh()->load(['externalIntegrations:id,name,slug'])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncIntegrations(User $user, array $data): void
    {
        if (($data['is_admin'] ?? $user->is_admin) === true) {
            $user->externalIntegrations()->sync([]);

            return;
        }

        if (! array_key_exists('external_integration_ids', $data)) {
            return;
        }

        $ids = collect($data['external_integration_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $user->externalIntegrations()->sync($ids);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(User $user): array
    {
        $integrations = $user->relationLoaded('externalIntegrations')
            ? $user->externalIntegrations
            : $user->externalIntegrations()->get(['external_integrations.id', 'name', 'slug']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'organisation' => $user->organisation,
            'registration_source' => $user->registration_source instanceof UserRegistrationSource
                ? $user->registration_source->value
                : (string) ($user->registration_source ?? UserRegistrationSource::System->value),
            'is_admin' => (bool) $user->is_admin,
            'is_active' => (bool) $user->is_active,
            'approval_status' => $user->approval_status instanceof UserApprovalStatus
                ? $user->approval_status->value
                : (string) ($user->approval_status ?? UserApprovalStatus::Approved->value),
            'approved_at' => $user->approved_at?->toIso8601String(),
            'rejected_at' => $user->rejected_at?->toIso8601String(),
            'rejection_reason' => $user->rejection_reason,
            'totp_required' => (bool) $user->totp_required,
            'two_factor_totp_enabled' => (bool) $user->two_factor_totp_enabled,
            'must_setup_totp' => $user->mustSetupTotp(),
            'external_integration_ids' => $integrations->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'external_integrations' => $integrations->map(fn ($i) => [
                'id' => (int) $i->id,
                'name' => $i->name,
                'slug' => $i->slug,
            ])->values()->all(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}
