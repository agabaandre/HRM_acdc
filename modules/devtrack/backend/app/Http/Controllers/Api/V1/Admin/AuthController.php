<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Admin\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Http\Requests\Api\V1\Admin\RegisterAccountRequest;
use App\Http\Requests\Api\V1\Admin\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Admin\StaffSsoLoginRequest;
use App\Enums\UserApprovalStatus;
use App\Enums\UserRegistrationSource;
use App\Models\User;
use App\Services\AdminPasswordResetService;
use App\Services\AdminTwoFactorService;
use App\Services\AuditLogService;
use App\Services\BlockedEmailService;
use App\Services\MicrosoftAuthService;
use App\Services\PendingRegistrationNotifier;
use App\Services\StaffShare\StaffShareAuthException;
use App\Services\StaffShare\StaffShareUserSyncService;
use App\Support\ApiDocsAuthCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends Controller
{
    public function register(
        RegisterAccountRequest $request,
        AuditLogService $audit,
        PendingRegistrationNotifier $notifier,
    ): JsonResponse {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'],
            'organisation' => $data['organisation'],
            'registration_source' => UserRegistrationSource::Public,
            'is_admin' => false,
            'is_active' => false,
            'approval_status' => UserApprovalStatus::Pending,
            'totp_required' => false,
        ]);

        $audit->log('Account registration submitted', [
            'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
            'event_type' => 'user_registered',
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'attempted_email' => $user->email,
            'target_table' => 'users',
            'target_id' => $user->id,
            'new_values' => [
                'email' => $user->email,
                'organisation' => $user->organisation,
                'registration_source' => UserRegistrationSource::Public->value,
                'approval_status' => UserApprovalStatus::Pending->value,
            ],
        ]);

        $notifier->notifyAdmins($user);

        return response()->json([
            'message' => 'Registration received. An administrator must approve your account before you can sign in.',
        ], 201);
    }

    public function login(
        LoginRequest $request,
        AdminTwoFactorService $twoFactor,
        AuditLogService $audit,
        BlockedEmailService $blockedEmails,
    ): JsonResponse {
        $email = (string) $request->validated('email');

        if ($blockedEmails->isBlocked($email)) {
            $audit->log('Login attempt with blocked email', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_blocked_email',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'attempted_email' => $email,
                'new_values' => ['email' => $email, 'reason' => 'email_blocked'],
            ]);

            throw ValidationException::withMessages([
                'email' => ['This email address has been blocked.'],
            ]);
        }

        try {
            $user = User::query()->where('email', $email)->first();
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Database unavailable. Check MySQL credentials and run migrate/seed.',
            ], 503);
        }

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            $audit->log('Failed login attempt', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'attempted_email' => $email,
                'new_values' => ['email' => $email],
            ]);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->approval_status === UserApprovalStatus::Pending) {
            $audit->log('Login attempt on pending account', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_pending',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'user' => $user,
                'attempted_email' => $user->email,
                'target_table' => 'users',
                'target_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Your account is awaiting administrator approval.',
            ], 403);
        }

        if ($user->approval_status === UserApprovalStatus::Rejected) {
            $audit->log('Login attempt on rejected account', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_rejected',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'user' => $user,
                'attempted_email' => $user->email,
                'target_table' => 'users',
                'target_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Your account registration was not approved.',
            ], 403);
        }

        if (! $user->is_active) {
            $audit->log('Login attempt on deactivated account', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_inactive',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'user' => $user,
                'attempted_email' => $user->email,
                'target_table' => 'users',
                'target_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        return $this->finishLogin($request, $user, $twoFactor, $audit, 'auth_login');
    }

    public function ssoLogin(
        StaffSsoLoginRequest $request,
        StaffShareUserSyncService $staffSync,
        AdminTwoFactorService $twoFactor,
        AuditLogService $audit,
        BlockedEmailService $blockedEmails,
    ): JsonResponse {
        if (! config('services.staff_share.enabled')) {
            return response()->json([
                'message' => 'Staff SSO is not enabled on this environment.',
            ], 503);
        }

        $username = Str::lower(trim((string) $request->validated('username')));
        $password = (string) $request->validated('password');

        if ($blockedEmails->isBlocked($username)) {
            $audit->log('Staff SSO attempt with blocked email', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_blocked_email',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'attempted_email' => $username,
            ]);

            throw ValidationException::withMessages([
                'username' => ['This email address has been blocked.'],
            ]);
        }

        try {
            $user = $staffSync->authenticateAndUpsert($username, $password);
        } catch (StaffShareAuthException $e) {
            $audit->log('Failed Staff SSO login', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_sso',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'attempted_email' => $username,
                'new_values' => ['username' => $username, 'reason' => $e->getMessage()],
            ]);

            throw ValidationException::withMessages([
                'username' => ['Invalid Staff SSO username or password.'],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Staff SSO is temporarily unavailable. Try again or use a local account.',
            ], 503);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => ['This account has been deactivated.'],
            ]);
        }

        return $this->finishLogin($request, $user, $twoFactor, $audit, 'auth_login_sso');
    }

    public function microsoftRedirect(Request $request, MicrosoftAuthService $microsoft): JsonResponse
    {
        if (! $microsoft->enabled()) {
            return response()->json([
                'message' => 'Microsoft 365 SSO is not enabled on this environment. Check MICROSOFT_* settings and that MICROSOFT_REDIRECT_URI matches the Azure app registration (e.g. https://tools.africacdc.org/login/redirect).',
                'enabled' => false,
            ], 503);
        }

        $loginHint = $request->query('login_hint');

        try {
            $payload = $microsoft->authorizationUrl(
                is_string($loginHint) ? $loginHint : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'enabled' => false], 503);
        }

        return response()->json([
            'enabled' => true,
            'url' => $payload['url'],
            'state' => $payload['state'],
        ]);
    }

    public function microsoftCallback(
        Request $request,
        MicrosoftAuthService $microsoft,
        AdminTwoFactorService $twoFactor,
        AuditLogService $audit,
        BlockedEmailService $blockedEmails,
    ): JsonResponse {
        if (! $microsoft->enabled()) {
            return response()->json([
                'message' => 'Microsoft 365 SSO is not enabled on this environment.',
            ], 503);
        }

        $data = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        if (! $microsoft->consumeState($data['state'])) {
            $audit->log('Microsoft SSO invalid or expired state', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_microsoft_state',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Microsoft sign-in session expired. Please try again.',
            ], 422);
        }

        try {
            $profile = $microsoft->exchangeCode($data['code']);
        } catch (RuntimeException $e) {
            $audit->log('Microsoft SSO token exchange failed', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_microsoft_token',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'new_values' => ['reason' => $e->getMessage()],
            ]);

            return response()->json([
                'message' => 'Microsoft sign-in failed. '.$e->getMessage(),
            ], 422);
        }

        if ($blockedEmails->isBlocked($profile['email'])) {
            $audit->log('Microsoft SSO attempt with blocked email', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_blocked_email',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'attempted_email' => $profile['email'],
            ]);

            return response()->json([
                'message' => 'This email address has been blocked.',
            ], 403);
        }

        try {
            $user = $microsoft->resolveAuthorizedUser($profile);
        } catch (RuntimeException $e) {
            $audit->log('Microsoft SSO denied — staff/user gate', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_failed_microsoft_unauthorized',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'attempted_email' => $profile['email'],
                'new_values' => ['reason' => $e->getMessage()],
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        }

        if ($user->approval_status === UserApprovalStatus::Pending) {
            return response()->json([
                'message' => 'Your account is awaiting administrator approval.',
            ], 403);
        }

        if ($user->approval_status === UserApprovalStatus::Rejected) {
            return response()->json([
                'message' => 'Your account registration was not approved.',
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account has been deactivated.',
            ], 403);
        }

        return $this->finishLogin($request, $user, $twoFactor, $audit, 'auth_login_microsoft');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->transformUser($request->user()));
    }

    private function finishLogin(
        Request $request,
        User $user,
        AdminTwoFactorService $twoFactor,
        AuditLogService $audit,
        string $eventType,
    ): JsonResponse {
        if ($user->hasTwoFactorEnabled()) {
            return response()->json($twoFactor->beginLoginChallenge($user));
        }

        $token = $user->createToken('admin-panel')->plainTextToken;

        $audit->log('User logged in', [
            'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
            'event_type' => $eventType,
            'user' => $user,
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'users',
            'target_id' => $user->id,
        ]);

        return ApiDocsAuthCookie::attach(response()->json([
            'token' => $token,
            'user' => $this->transformUser($user),
        ]), $token);
    }

    public function logout(Request $request, AuditLogService $audit): JsonResponse
    {
        $user = $request->user();
        $audit->log('User logged out', [
            'event_type' => 'auth_logout',
            'user' => $user,
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'users',
            'target_id' => $user?->id,
        ]);

        $user?->currentAccessToken()?->delete();

        return ApiDocsAuthCookie::clear(response()->json(['message' => 'Logged out.']));
    }

    public function changePassword(ChangePasswordRequest $request, AuditLogService $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $audit->log('User changed password', [
            'event_type' => 'auth_password_change',
            'target_table' => 'users',
            'target_id' => $user->id,
            'http_method' => 'POST',
            'request_uri' => $request->path(),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function forgotPassword(
        ForgotPasswordRequest $request,
        AdminPasswordResetService $passwordReset,
        AuditLogService $audit,
        BlockedEmailService $blockedEmails,
    ): JsonResponse {
        $email = (string) $request->validated('email');

        if ($blockedEmails->isBlocked($email)) {
            $audit->log('Password reset blocked for blocked email', [
                'event_type' => 'auth_forgot_blocked_email',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'attempted_email' => $email,
                'new_values' => ['email' => $email, 'reason' => 'email_blocked'],
            ]);

            return response()->json([
                'message' => 'If an account exists for that email, a password reset link has been sent.',
            ]);
        }

        $passwordReset->sendResetLink($email);

        $audit->log('Password reset link requested', [
            'event_type' => 'auth_forgot',
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'attempted_email' => $email,
            'new_values' => ['email' => $email],
        ]);

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, AdminPasswordResetService $passwordReset, AuditLogService $audit): JsonResponse
    {
        $passwordReset->resetPassword(
            $request->validated('email'),
            $request->validated('token'),
            $request->validated('password'),
        );

        $user = User::query()->where('email', $request->validated('email'))->first();
        $audit->log('Password reset via email link', [
            'event_type' => 'auth_password_reset',
            'user' => $user,
            'target_table' => 'users',
            'target_id' => $user?->id,
            'http_method' => 'POST',
            'request_uri' => $request->path(),
        ]);

        return response()->json([
            'message' => 'Password updated. You can sign in with your new password.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'organisation' => $user->organisation,
            'division' => $user->division,
            'directorate' => $user->directorate,
            'staff_id' => $user->staff_id,
            'registration_source' => $user->registration_source instanceof UserRegistrationSource
                ? $user->registration_source->value
                : (string) ($user->registration_source ?? UserRegistrationSource::System->value),
            'is_admin' => (bool) $user->is_admin,
            'is_active' => (bool) $user->is_active,
            'approval_status' => $user->approval_status instanceof \App\Enums\UserApprovalStatus
                ? $user->approval_status->value
                : (string) $user->approval_status,
            'two_factor_email_enabled' => (bool) $user->two_factor_email_enabled,
            'two_factor_totp_enabled' => (bool) $user->two_factor_totp_enabled,
            'totp_required' => $user->requiresTotp(),
            'must_setup_totp' => $user->mustSetupTotp(),
        ];
    }
}
