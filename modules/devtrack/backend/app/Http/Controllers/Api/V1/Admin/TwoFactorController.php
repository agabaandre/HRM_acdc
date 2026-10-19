<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ConfirmPasswordRequest;
use App\Http\Requests\Api\V1\Admin\ConfirmTotpSetupRequest;
use App\Http\Requests\Api\V1\Admin\ResendTwoFactorEmailRequest;
use App\Http\Requests\Api\V1\Admin\SetupTotpRequest;
use App\Http\Requests\Api\V1\Admin\VerifyTwoFactorRequest;
use App\Services\AdminTwoFactorService;
use App\Services\AuditLogService;
use App\Support\ApiDocsAuthCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function status(Request $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        return response()->json([
            'data' => $twoFactor->status($request->user()),
        ]);
    }

    public function enableEmail(ConfirmPasswordRequest $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $twoFactor->enableEmail($request->user(), $request->validated('password'));

        return response()->json([
            'data' => $twoFactor->status($request->user()->fresh()),
            'message' => 'Email verification is now enabled for sign-in.',
        ]);
    }

    public function disableEmail(ConfirmPasswordRequest $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $twoFactor->disableEmail($request->user(), $request->validated('password'));

        return response()->json([
            'data' => $twoFactor->status($request->user()->fresh()),
            'message' => 'Email verification has been disabled.',
        ]);
    }

    public function setupTotp(SetupTotpRequest $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $setup = $twoFactor->beginTotpSetup(
            $request->user(),
            $request->validated('password'),
        );

        return response()->json(['data' => $setup]);
    }

    public function confirmTotp(ConfirmTotpSetupRequest $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $result = $twoFactor->confirmTotpSetup($request->user(), $request->validated('code'));

        return response()->json([
            'data' => array_merge($twoFactor->status($request->user()->fresh()), $result),
            'message' => 'Authenticator app verification is now enabled.',
        ]);
    }

    public function disableTotp(ConfirmPasswordRequest $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $twoFactor->disableTotp($request->user(), $request->validated('password'));

        return response()->json([
            'data' => $twoFactor->status($request->user()->fresh()),
            'message' => 'Authenticator app verification has been disabled.',
        ]);
    }

    public function verify(VerifyTwoFactorRequest $request, AdminTwoFactorService $twoFactor, AuditLogService $audit): JsonResponse
    {
        try {
            $user = $twoFactor->verifyLoginChallenge(
                $request->validated('challenge_token'),
                $request->validated('method'),
                $request->validated('code'),
            );
        } catch (ValidationException $e) {
            $audit->log('Failed two-factor verification', [
                'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
                'event_type' => 'auth_2fa_failed',
                'http_method' => 'POST',
                'request_uri' => $request->path(),
                'new_values' => ['method' => $request->validated('method')],
            ]);

            throw $e;
        }

        $token = $user->createToken('admin-panel')->plainTextToken;

        $audit->log('User logged in (2FA)', [
            'actor_type' => AuditLogService::ACTOR_SYSTEM_USER,
            'event_type' => 'auth_login',
            'user' => $user,
            'http_method' => 'POST',
            'request_uri' => $request->path(),
            'target_table' => 'users',
            'target_id' => $user->id,
            'new_values' => ['method' => $request->validated('method')],
        ]);

        return ApiDocsAuthCookie::attach(response()->json([
            'token' => $token,
            'user' => $this->transformUser($user),
        ]), $token);
    }

    public function resendEmail(ResendTwoFactorEmailRequest $request, AdminTwoFactorService $twoFactor): JsonResponse
    {
        $twoFactor->resendEmailCode($request->validated('challenge_token'));

        return response()->json([
            'message' => 'A new verification code has been sent to your email.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformUser(\App\Models\User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'is_active' => (bool) $user->is_active,
            'two_factor_email_enabled' => (bool) $user->two_factor_email_enabled,
            'two_factor_totp_enabled' => (bool) $user->two_factor_totp_enabled,
            'totp_required' => $user->requiresTotp(),
            'must_setup_totp' => $user->mustSetupTotp(),
        ];
    }
}
