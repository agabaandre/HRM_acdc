<?php

namespace Modules\Auth\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SsoJwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\PortalUser;
use Modules\Auth\Services\PortalImpersonationService;
use Modules\Auth\Services\PortalLoginService;
use Modules\Settings\Services\PortalModulesService;

/**
 * Sanctum auth for the Staff Portal Vue SPA (same token pattern as Helpdesk).
 */
class PortalSpaAuthController extends Controller
{
    public function __construct(
        protected PortalLoginService $portalLogin,
        protected PortalImpersonationService $impersonation,
        protected PortalModulesService $portalModules,
    ) {}

    public function login(Request $request): JsonResponse
    {
        if (! (bool) config('auth.allow_alternative_login', false)) {
            return response()->json(['message' => 'Email and password sign-in is disabled.'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = PortalUser::query()
            ->whereHas('staff', fn ($q) => $q->where('work_email', $validated['email']))
            ->where('status', 1)
            ->first();

        if (! $user || ! $user->password || ! password_verify($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('Invalid email or password.')],
            ]);
        }

        if (! $user->allow_email_login) {
            throw ValidationException::withMessages([
                'email' => [__('Email and password sign-in is not enabled for your account. Use Microsoft SSO or contact an administrator.')],
            ]);
        }

        $this->portalLogin->login($user, (bool) $request->boolean('remember'), 'User logged in via Staff Portal SPA');

        return response()->json($this->tokenPayload($user));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json(['data' => $this->userPayload($user)]);
    }

    /**
     * Issue a Sanctum token when a valid web session already exists (e.g. after Microsoft OAuth).
     */
    public function bootstrapFromSession(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof PortalUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($this->tokenPayload($user));
    }

    /**
     * @return array{token: string, user: array<string, mixed>, sso_token: string}
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

        return [
            'token' => $token->plainTextToken,
            'user' => $this->userPayload($user),
            'sso_token' => SsoJwt::encode($session, (int) config('staff-portal.sso.token_ttl', 7200)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(PortalUser $user): array
    {
        $session = $user->toSessionArray();

        if ($this->impersonation->isImpersonating()) {
            $session['is_impersonated'] = true;
        }

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
            'impersonation' => $this->impersonation->status(),
            'enabled_modules' => $this->portalModules->enabledMap(),
        ];
    }

    /**
     * Public login page flags (no auth required).
     */
    public function loginOptions(): JsonResponse
    {
        return response()->json([
            'data' => [
                'allow_alternative_login' => (bool) config('auth.allow_alternative_login', false),
                'microsoft_enabled' => \Modules\Auth\Services\MicrosoftAuthService::isConfigured(),
                'apm_base_url' => rtrim((string) config('staff-portal.apm_base_url', ''), '/'),
            ],
        ]);
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
