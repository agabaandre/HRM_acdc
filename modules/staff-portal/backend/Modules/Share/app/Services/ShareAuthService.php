<?php

namespace Modules\Share\Services;

use App\Support\SsoJwt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\Models\PortalUser;

/**
 * Portal credential checks and Share JWT issuance for APM / Helpdesk / Finance clients.
 */
class ShareAuthService
{
    /**
     * Resolve an active portal user by work_email (preferred) or user.email when present.
     */
    public function findActiveUserByUsername(string $username): ?PortalUser
    {
        $username = trim($username);
        if ($username === '' || ! Schema::hasTable('user') || ! Schema::hasTable('staff')) {
            return null;
        }

        $user = PortalUser::query()
            ->where('status', 1)
            ->whereHas('staff', fn ($q) => $q->where('work_email', $username))
            ->first();

        if (! $user && Schema::hasColumn('user', 'email')) {
            $user = PortalUser::query()->where('status', 1)->where('email', $username)->first();
        }

        if (! $user) {
            $row = DB::table('user as u')
                ->join('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
                ->where('u.status', 1)
                ->where('s.work_email', $username)
                ->select('u.user_id')
                ->first();
            if ($row) {
                $user = PortalUser::query()->find($row->user_id);
            }
        }

        return $user instanceof PortalUser ? $user : null;
    }

    public function credentialsValid(string $username, string $password): ?PortalUser
    {
        $user = $this->findActiveUserByUsername($username);
        if (! $user || ! is_string($user->password) || $user->password === '') {
            return null;
        }

        return password_verify($password, $user->password) ? $user : null;
    }

    /**
     * @return array{success: true, access_token: string, token: string, token_type: string, expires_in: int, aud: string, user: array<string, mixed>}
     */
    public function issueTokenResponse(PortalUser $user): array
    {
        $ttl = max(60, (int) config('share.jwt_ttl', 3600));
        $user->loadMissing('staff');
        $staff = $user->staff;
        $staffId = (int) ($user->auth_staff_id ?? 0);
        $name = trim(($staff?->fname ?? '').' '.($staff?->lname ?? ''));
        if ($name === '') {
            $name = (string) ($user->name ?? '');
        }
        $email = (string) ($staff?->work_email ?? '');

        // Lean Share JWT — avoid full portal session hydration (permissions / KV settings).
        $session = [
            'user_id' => (int) $user->user_id,
            'staff_id' => $staffId,
            'name' => $name,
            'email' => $email,
            'role' => (int) ($user->role ?? 0),
            'role_id' => (int) ($user->role ?? 0),
            'aud' => (string) config('share.jwt_audience', 'share-api'),
            'sub' => (string) $user->user_id,
            'base_url' => rtrim((string) config('staff-portal.base_url', config('app.url')), '/').'/',
        ];
        $token = SsoJwt::encode($session, $ttl);

        return [
            'success' => true,
            'access_token' => $token,
            // KnowledgeHub-style alias for clients that expect `token`.
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'aud' => $session['aud'],
            'user' => [
                'user_id' => (int) $user->user_id,
                'staff_id' => $staffId,
                'name' => $name,
                'email' => $email,
                'role' => (int) ($user->role ?? 0),
            ],
        ];
    }
}
