<?php

namespace App\Services;

use App\Models\ApmApiUser;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApmImpersonationService
{
    public function canImpersonate(): bool
    {
        return (int) user_session('role') === 10;
    }

    public function isImpersonating(): bool
    {
        return session()->has('original_user')
            && (bool) data_get(session('user'), 'is_impersonated', false);
    }

    /**
     * @throws HttpException
     */
    public function impersonate(ApmApiUser $target): void
    {
        if (! $this->canImpersonate()) {
            throw new HttpException(403, 'You are not authorized to impersonate users.');
        }

        if (! $target->status) {
            throw new HttpException(422, 'Cannot impersonate an inactive user.');
        }

        $current = session('user', []);
        if (! is_array($current) || empty($current)) {
            throw new HttpException(403, 'No active session to impersonate from.');
        }

        $currentUserId = (int) ($current['user_id'] ?? 0);
        $currentStaffId = (int) ($current['staff_id'] ?? $current['auth_staff_id'] ?? 0);

        if ($currentUserId === (int) $target->user_id
            || ($currentStaffId > 0 && $currentStaffId === (int) $target->auth_staff_id)) {
            throw new HttpException(422, 'You cannot impersonate yourself.');
        }

        if (session()->has('original_user')) {
            throw new HttpException(422, 'Already impersonating a user. Revert to admin first.');
        }

        $impersonated = $this->buildWebSessionForUser($target);
        $impersonated['is_impersonated'] = true;
        $impersonated['is_admin'] = false;

        session([
            'original_user' => $current,
            'impersonation_start' => time(),
            'user' => $impersonated,
            'permissions' => $impersonated['permissions'] ?? [],
            'base_url' => $impersonated['base_url'] ?? config('app.url'),
            'last_activity' => now(),
        ]);
        session()->save();

        Log::info('APM impersonation started', [
            'admin_user_id' => $currentUserId,
            'admin_staff_id' => $currentStaffId,
            'admin_name' => $current['name'] ?? null,
            'target_user_id' => $target->user_id,
            'target_staff_id' => $target->auth_staff_id,
            'target_name' => $impersonated['name'] ?? null,
        ]);
    }

    public function revert(): bool
    {
        $original = session('original_user');
        if (! is_array($original) || $original === []) {
            return false;
        }

        session([
            'user' => $original,
            'permissions' => $original['permissions'] ?? [],
            'base_url' => $original['base_url'] ?? config('app.url'),
            'last_activity' => now(),
        ]);
        session()->forget(['original_user', 'impersonation_start']);
        session()->save();

        Log::info('APM impersonation reverted', [
            'admin_user_id' => $original['user_id'] ?? null,
            'admin_name' => $original['name'] ?? null,
        ]);

        return true;
    }

    /**
     * Build a web session payload similar to CodeIgniter auth/impersonate.
     *
     * @return array<string, mixed>
     */
    public function buildWebSessionForUser(ApmApiUser $apiUser): array
    {
        $apiUser->loadMissing('staff');
        $staff = $apiUser->staff;

        $role = (int) ($apiUser->role ?? 0);
        $permissions = $this->loadPermissions($role, (int) $apiUser->user_id);

        $name = trim((string) ($apiUser->name ?? ''));
        if ($name === '' && $staff) {
            $name = trim(collect([$staff->title, $staff->fname, $staff->lname, $staff->oname])->filter()->implode(' '));
        }

        $divisionId = $staff?->division_id;
        if ($divisionId === null && $this->staffAppConfigured()) {
            $divisionId = $this->loadDivisionIdFromStaffApp((int) $apiUser->auth_staff_id);
        }

        $photo = $staff?->photo ?: $apiUser->photo;
        $baseUrl = rtrim((string) config('app.ci_base_url', env('BASE_URL', config('app.url'))), '/') . '/';

        return [
            'user_id' => (int) $apiUser->user_id,
            'staff_id' => (int) $apiUser->auth_staff_id,
            'auth_staff_id' => (int) $apiUser->auth_staff_id,
            'role' => $role,
            'name' => $name !== '' ? $name : ($apiUser->email ?? 'User'),
            'email' => $apiUser->email ?? $staff?->work_email,
            'division_id' => $divisionId,
            'permissions' => $permissions,
            'photo' => $photo,
            'status' => (bool) $apiUser->status,
            'base_url' => $baseUrl,
        ];
    }

    /**
     * @return list<int>
     */
    private function loadPermissions(int $role, int $userId): array
    {
        if (! $this->staffAppConfigured()) {
            return [];
        }

        try {
            $groupPerms = DB::connection('staff_app')
                ->table('group_permissions')
                ->where('group_id', $role)
                ->pluck('permission_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $userPerms = DB::connection('staff_app')
                ->table('user_permissions')
                ->where('user_id', $userId)
                ->pluck('permission_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return array_values(array_unique(array_merge($groupPerms, $userPerms)));
        } catch (\Throwable $e) {
            Log::warning('APM impersonation: could not load permissions from staff DB', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function loadDivisionIdFromStaffApp(int $staffId): ?int
    {
        if ($staffId <= 0 || ! $this->staffAppConfigured()) {
            return null;
        }

        try {
            $fromStaff = DB::connection('staff_app')
                ->table('staff')
                ->where('staff_id', $staffId)
                ->value('division_id');

            if ($fromStaff !== null) {
                return (int) $fromStaff;
            }

            $contractDivision = DB::connection('staff_app')
                ->table('staff_contracts')
                ->where('staff_id', $staffId)
                ->orderByDesc('staff_contract_id')
                ->value('division_id');

            return $contractDivision !== null ? (int) $contractDivision : null;
        } catch (\Throwable $e) {
            return Staff::query()->where('staff_id', $staffId)->value('division_id');
        }
    }

    private function staffAppConfigured(): bool
    {
        $db = config('database.connections.staff_app.database');

        return is_string($db) && $db !== '';
    }
}
