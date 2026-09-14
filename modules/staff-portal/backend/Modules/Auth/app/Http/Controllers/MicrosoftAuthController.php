<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Services\MicrosoftAuthService;
use Modules\Auth\Services\PortalLoginService;
use Modules\Auth\Support\SpaRedirect;

class MicrosoftAuthController extends Controller
{
    public function __construct(
        protected MicrosoftAuthService $microsoft,
        protected PortalLoginService $portalLogin,
    ) {}

    public function redirect(): RedirectResponse
    {
        // Already signed into the Laravel web session (e.g. after a prior SSO) but
        // the SPA may not have a Sanctum token yet — hand off instead of bouncing.
        if (auth()->check()) {
            return SpaRedirect::afterLogin();
        }

        if (! MicrosoftAuthService::isConfigured()) {
            return SpaRedirect::toLoginWithError(
                'Microsoft sign-in is not configured. Contact your administrator.',
                'ms_not_configured'
            );
        }

        try {
            return redirect()->away($this->microsoft->authorizationUrl());
        } catch (\Throwable $e) {
            Log::error('Microsoft OAuth authorize failed', ['message' => $e->getMessage()]);

            return SpaRedirect::toLoginWithError(
                'Could not start Microsoft sign-in. Please try again.',
                'ms_authorize_failed'
            );
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            $description = (string) $request->input('error_description', '');
            $error = (string) $request->input('error', 'access_denied');
            Log::warning('Microsoft OAuth error returned to callback', [
                'error' => $error,
                'description' => $description,
            ]);
            $this->microsoft->clearOauthSession();

            $message = $description !== ''
                ? $description
                : ($error === 'access_denied'
                    ? 'Microsoft sign-in was cancelled.'
                    : 'Microsoft sign-in failed ('.$error.').');

            return SpaRedirect::toLoginWithError($message, 'ms_'.$error);
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            $this->microsoft->clearOauthSession();

            return SpaRedirect::toLoginWithError('Invalid sign-in request (missing authorization code).', 'ms_missing_code');
        }

        if (! $this->microsoft->validateState($request->query('state'))) {
            $this->microsoft->clearOauthSession();

            return SpaRedirect::toLoginWithError(
                'Sign-in session expired or invalid. Close other tabs and try Microsoft sign-in again.',
                'ms_invalid_state'
            );
        }

        $tokenResult = $this->microsoft->exchangeCodeForToken($code);
        if (! ($tokenResult['ok'] ?? false)) {
            return SpaRedirect::toLoginWithError(
                (string) ($tokenResult['error'] ?? 'Could not complete Microsoft sign-in. Please try again.'),
                'ms_token'
            );
        }

        $graphResult = $this->microsoft->fetchGraphUser((string) $tokenResult['access_token']);
        if (! ($graphResult['ok'] ?? false)) {
            return SpaRedirect::toLoginWithError(
                (string) ($graphResult['error'] ?? 'Could not load your Microsoft profile.'),
                'ms_graph'
            );
        }

        /** @var array<string, mixed> $graphUser */
        $graphUser = $graphResult['user'];
        $email = $this->microsoft->resolveEmailFromGraphUser($graphUser);
        if ($email === null) {
            return SpaRedirect::toLoginWithError(
                'No work email found on your Microsoft account.',
                'ms_no_email'
            );
        }

        $user = $this->microsoft->findPortalUserByEmail($email);
        if ($user === null) {
            Log::info('Microsoft SSO rejected: no staff portal user', ['email' => $email]);

            return SpaRedirect::toLoginWithError(
                'Staff profile missing for '.$email.'. Contact HR.',
                'ms_no_staff'
            );
        }

        $this->portalLogin->login($user, false, 'User logged in successfully using Microsoft SSO');

        return SpaRedirect::afterLogin();
    }
}
