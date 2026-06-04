<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Services\MicrosoftAuthService;
use Modules\Auth\Services\PortalLoginService;

class MicrosoftAuthController extends Controller
{
    public function __construct(
        protected MicrosoftAuthService $microsoft,
        protected PortalLoginService $portalLogin,
    ) {}

    public function redirect(): RedirectResponse
    {
        if (! MicrosoftAuthService::isConfigured()) {
            return redirect()
                ->route('login')
                ->with('error', __('Microsoft sign-in is not configured. Contact your administrator.'));
        }

        return redirect()->away($this->microsoft->authorizationUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            Log::warning('Microsoft OAuth error', [
                'error' => $request->input('error'),
                'description' => $request->input('error_description'),
            ]);

            $message = $request->input('error_description', $request->input('error', 'Login was cancelled.'));

            return redirect()->route('login')->with('error', (string) $message);
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect()->route('login')->with('error', __('Invalid sign-in request.'));
        }

        if (! $this->microsoft->validateState($request->query('state'))) {
            return redirect()->route('login')->with('error', __('Sign-in session expired. Please try again.'));
        }

        $accessToken = $this->microsoft->exchangeCodeForToken($code);
        if ($accessToken === null) {
            return redirect()->route('login')->with('error', __('Could not complete Microsoft sign-in. Please try again.'));
        }

        $graphUser = $this->microsoft->fetchGraphUser($accessToken);
        if ($graphUser === null) {
            return redirect()->route('login')->with('error', __('Could not load your Microsoft profile.'));
        }

        $email = $this->microsoft->resolveEmailFromGraphUser($graphUser);
        if ($email === null) {
            return redirect()->route('login')->with('error', __('No work email found on your Microsoft account.'));
        }

        $user = $this->microsoft->findPortalUserByEmail($email);
        if ($user === null) {
            return redirect()->route('login')->with('error', __('Staff profile missing. Contact HR.'));
        }

        $this->portalLogin->login($user, false, 'User logged in successfully using Microsoft SSO');

        return redirect()->route('core.home');
    }
}
