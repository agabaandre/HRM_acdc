<?php

namespace Modules\Auth\Livewire;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Auth\Models\PortalUser;
use Modules\Auth\Services\MicrosoftAuthService;
use Modules\Auth\Services\PortalLoginService;

#[Layout('auth::components.layouts.login')]
class LoginForm extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public bool $showAlternative = false;

    public function login(PortalLoginService $portalLogin): void
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = PortalUser::query()
            ->whereHas('staff', fn ($q) => $q->where('work_email', $this->email))
            ->where('status', 1)
            ->first();

        if (! $user || ! $user->password || ! password_verify($this->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Invalid email or password.'),
            ]);
        }

        if (! $user->allow_email_login) {
            throw ValidationException::withMessages([
                'email' => __('Email and password sign-in is not enabled for your account. Use “Sign in with Staff Email” (Microsoft) or contact an administrator.'),
            ]);
        }

        $portalLogin->login($user, $this->remember, 'User logged in successfully using email and password');

        $this->redirect(route('core.home'), navigate: true);
    }

    public function render()
    {
        return view('auth::livewire.login-form', [
            'microsoftEnabled' => MicrosoftAuthService::isConfigured(),
            'allowAlternativeLogin' => (bool) config('auth.allow_alternative_login', true),
            'apmBaseUrl' => rtrim((string) config('staff-portal.apm_base_url'), '/'),
            'flashError' => session('error'),
            'flashSuccess' => session('success'),
        ]);
    }
}
