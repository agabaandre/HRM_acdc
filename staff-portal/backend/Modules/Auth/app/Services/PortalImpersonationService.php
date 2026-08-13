<?php

namespace Modules\Auth\Services;

use App\Support\SsoJwt;
use Illuminate\Support\Facades\Auth;
use Modules\Audit\Services\AuditLogService;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Support\PortalPermission;
use Modules\Settings\Services\PortalModulesService;
use RuntimeException;

class PortalImpersonationService
{
    /** Match CI3 / APM temporary impersonation window. */
    public const TTL_SECONDS = 300;

    public function __construct(
        protected AuditLogService $auditLog,
        protected PortalModulesService $portalModules,
    ) {}

    public function canStart(): bool
    {
        return PortalPermission::can(17) && ! $this->isImpersonating();
    }

    public function isImpersonating(): bool
    {
        return session()->has('original_user')
            && (bool) data_get(session('user'), 'is_impersonated', false);
    }

    /**
     * @return array{
     *   active: bool,
     *   user_name: string|null,
     *   user_id: int|null,
     *   started_at: int|null,
     *   expires_at: int|null,
     *   remaining_seconds: int|null,
     *   original_user_name: string|null
     * }
     */
    public function status(): array
    {
        if (! $this->isImpersonating()) {
            return [
                'active' => false,
                'user_name' => null,
                'user_id' => null,
                'started_at' => null,
                'expires_at' => null,
                'remaining_seconds' => null,
                'original_user_name' => null,
            ];
        }

        $started = (int) session('impersonation_start', time());
        $expires = $started + self::TTL_SECONDS;
        $remaining = max(0, $expires - time());
        $original = session('original_user', []);

        return [
            'active' => true,
            'user_name' => (string) (session('user.name') ?? Auth::user()?->name ?? 'User'),
            'user_id' => (int) (session('user.user_id') ?? Auth::id() ?? 0) ?: null,
            'started_at' => $started,
            'expires_at' => $expires,
            'remaining_seconds' => $remaining,
            'original_user_name' => is_array($original)
                ? (string) ($original['name'] ?? 'Admin')
                : 'Admin',
        ];
    }

    /**
     * @return array{token: string, user: array<string, mixed>, sso_token: string, impersonation: array<string, mixed>}
     */
    public function impersonate(int $targetUserId): array
    {
        if (! PortalPermission::can(17)) {
            throw new RuntimeException('You are not authorized to impersonate users.');
        }

        if ($this->isImpersonating()) {
            throw new RuntimeException('Already impersonating a user. Revert to admin first.');
        }

        /** @var PortalUser|null $admin */
        $admin = Auth::user();
        if (! $admin instanceof PortalUser) {
            throw new RuntimeException('No active session to impersonate from.');
        }

        if ((int) $admin->user_id === $targetUserId) {
            throw new RuntimeException('You cannot impersonate yourself.');
        }

        $target = PortalUser::query()->where('user_id', $targetUserId)->first();
        if (! $target) {
            throw new RuntimeException('User not found.');
        }
        if (! (bool) $target->status) {
            throw new RuntimeException('Cannot impersonate an inactive user.');
        }

        $adminSession = $admin->toSessionArray();
        $targetSession = $target->toSessionArray();
        $targetSession['is_impersonated'] = true;
        $targetSession['is_admin'] = false;

        // Drop the admin SPA token so the bearer cannot keep acting as admin.
        $admin->tokens()->where('name', 'staff-portal-spa')->delete();

        // Sanctum bearer auth uses RequestGuard (no login()). Session switch must use web.
        Auth::guard('web')->login($target, false);

        session([
            'original_user' => $adminSession,
            'impersonation_start' => time(),
            'user' => $targetSession,
            'last_activity' => now(),
        ]);
        session()->save();

        $this->auditLog->log(
            sprintf(
                'Admin %s (ID: %d) is now impersonating %s (ID: %d)',
                $adminSession['name'] ?? $admin->name,
                (int) $admin->user_id,
                $targetSession['name'] ?? $target->name,
                (int) $target->user_id,
            ),
            ['event_type' => 'impersonation'],
        );

        return $this->tokenPayload($target);
    }

    /**
     * @return array{token: string, user: array<string, mixed>, sso_token: string, impersonation: array<string, mixed>}
     */
    public function revert(): array
    {
        if (! $this->isImpersonating()) {
            throw new RuntimeException('You are not impersonating any user.');
        }

        $original = session('original_user');
        if (! is_array($original) || empty($original['user_id'])) {
            throw new RuntimeException('Original admin session is missing.');
        }

        $adminId = (int) $original['user_id'];
        $admin = PortalUser::query()->where('user_id', $adminId)->first();
        if (! $admin) {
            throw new RuntimeException('Original admin account could not be restored.');
        }

        /** @var PortalUser|null $current */
        $current = Auth::user();
        if ($current instanceof PortalUser) {
            $current->tokens()->where('name', 'staff-portal-spa')->delete();
        }

        Auth::guard('web')->login($admin, false);

        $fresh = $admin->toSessionArray();
        session([
            'user' => array_merge($original, $fresh, ['is_impersonated' => false]),
            'last_activity' => now(),
        ]);
        session()->forget(['original_user', 'impersonation_start']);
        session()->save();

        $this->auditLog->log('Reverted back to personal account', ['event_type' => 'impersonation']);

        return $this->tokenPayload($admin);
    }

    /**
     * Auto-revert when the temporary window has elapsed.
     */
    public function expireIfNeeded(): bool
    {
        if (! $this->isImpersonating()) {
            return false;
        }
        $started = (int) session('impersonation_start', 0);
        if ($started > 0 && (time() - $started) >= self::TTL_SECONDS) {
            $this->revert();

            return true;
        }

        return false;
    }

    /**
     * @return array{token: string, user: array<string, mixed>, sso_token: string, impersonation: array<string, mixed>}
     */
    protected function tokenPayload(PortalUser $user): array
    {
        $user->tokens()->where('name', 'staff-portal-spa')->delete();

        $token = $user->createToken(
            'staff-portal-spa',
            ['*'],
            now()->addHours(8)
        );

        $session = $user->toSessionArray();
        if ($this->isImpersonating()) {
            $session['is_impersonated'] = true;
        }

        return [
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user, $session),
            'sso_token' => SsoJwt::encode($session, (int) config('staff-portal.sso.token_ttl', 7200)),
            'impersonation' => $this->status(),
        ];
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    protected function userPayload(PortalUser $user, array $session): array
    {
        return [
            'id' => (int) $user->user_id,
            'name' => (string) ($session['name'] ?? $user->name),
            'email' => (string) ($session['email'] ?? ''),
            'avatar_url' => $this->resolvePhotoUrl($session['photo'] ?? null),
            'profile' => [
                'staff_id' => (int) ($session['staff_id'] ?? 0),
                'role' => (string) ($session['role'] ?? ''),
                'role_id' => (int) ($session['role_id'] ?? $session['role'] ?? 0),
                'division_id' => $session['division_id'] ?? null,
                'permissions' => $session['permissions'] ?? [],
                'is_hr' => (int) ($session['role_id'] ?? $session['role'] ?? 0) === 20,
                'is_hr_admin' => (int) ($session['role_id'] ?? $session['role'] ?? 0) === 22,
                'is_system_admin' => (int) ($session['role_id'] ?? $session['role'] ?? 0) === 10,
                'allow_email_login' => (bool) $user->allow_email_login,
                'password_login_available' => (bool) $user->allow_email_login
                    && (bool) config('auth.allow_alternative_login', false),
                'is_impersonated' => (bool) ($session['is_impersonated'] ?? false),
            ],
            'impersonation' => $this->status(),
            'enabled_modules' => $this->portalModules->enabledMap(),
        ];
    }

    protected function resolvePhotoUrl(mixed $photo): ?string
    {
        if (! is_string($photo) || trim($photo) === '') {
            return null;
        }

        $photo = trim($photo);
        if (preg_match('#^https?://#i', $photo)) {
            return $photo;
        }

        return \App\Support\StaffPhoto::url($photo);
    }
}
