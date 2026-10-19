<?php

namespace App\Services;

use App\Enums\UserRegistrationSource;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class MicrosoftAuthService
{
    public function enabled(): bool
    {
        return (bool) config('services.microsoft.enabled')
            && filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'))
            && filled(config('services.microsoft.tenant_id'))
            && filled($this->redirectUri());
    }

    public function redirectUri(): string
    {
        $configured = trim((string) config('services.microsoft.redirect_uri'));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/login/redirect';
    }

    /**
     * @return array{url: string, state: string}
     */
    public function authorizationUrl(?string $loginHint = null): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Microsoft 365 SSO is not configured.');
        }

        $state = Str::random(40);
        Cache::put($this->stateKey($state), [
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        $params = [
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $this->redirectUri(),
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read offline_access',
            'state' => $state,
            'prompt' => 'select_account',
            // Prefer organisational Africa CDC accounts over personal Microsoft IDs.
            'domain_hint' => 'africacdc.org',
        ];

        $hint = is_string($loginHint) ? Str::lower(trim($loginHint)) : '';
        if ($hint !== '' && filter_var($hint, FILTER_VALIDATE_EMAIL)) {
            $params['login_hint'] = $hint;
        }

        $query = http_build_query($params);
        $tenant = (string) config('services.microsoft.tenant_id');

        return [
            'url' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$query}",
            'state' => $state,
        ];
    }

    public function consumeState(string $state): bool
    {
        $key = $this->stateKey($state);
        if (! Cache::has($key)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    /**
     * @return array{email: string, name: string, oid: string|null}
     */
    public function exchangeCode(string $code): array
    {
        $tenant = (string) config('services.microsoft.tenant_id');
        $tokenResponse = Http::asForm()
            ->timeout(30)
            ->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
                'scope' => 'openid profile email User.Read offline_access',
            ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException(
                (string) ($tokenResponse->json('error_description') ?: 'Could not exchange Microsoft authorization code.'),
            );
        }

        $accessToken = (string) $tokenResponse->json('access_token');
        if ($accessToken === '') {
            throw new RuntimeException('Microsoft token response did not include an access token.');
        }

        $profileResponse = Http::withToken($accessToken)
            ->timeout(30)
            ->acceptJson()
            ->get('https://graph.microsoft.com/v1.0/me', [
                '$select' => 'id,displayName,mail,userPrincipalName,givenName,surname',
            ]);

        if (! $profileResponse->successful()) {
            throw new RuntimeException('Could not load the Microsoft user profile.');
        }

        $profile = $profileResponse->json();
        $email = Str::lower(trim((string) (
            $profile['mail']
            ?? $profile['userPrincipalName']
            ?? ''
        )));

        if ($email === '' || ! str_contains($email, '@')) {
            throw new RuntimeException('Microsoft account did not return a usable email address.');
        }

        $name = trim((string) ($profile['displayName'] ?? ''));
        if ($name === '') {
            $name = trim(implode(' ', array_filter([
                (string) ($profile['givenName'] ?? ''),
                (string) ($profile['surname'] ?? ''),
            ])));
        }
        if ($name === '') {
            $name = Str::before($email, '@');
        }

        return [
            'email' => $email,
            'name' => $name,
            'oid' => isset($profile['id']) ? (string) $profile['id'] : null,
        ];
    }

    /**
     * SSO may only sign in staff emails that already have a platform user account.
     *
     * @param  array{email: string, name: string, oid: string|null}  $profile
     */
    public function resolveAuthorizedUser(array $profile): User
    {
        $email = $profile['email'];

        $staff = Staff::query()
            ->where(function ($q) use ($email) {
                $q->whereRaw('LOWER(work_email) = ?', [$email]);
                if (Schema::hasColumn('staff', 'private_email')) {
                    $q->orWhereRaw('LOWER(private_email) = ?', [$email]);
                }
            })
            ->first();

        if ($staff === null) {
            throw new RuntimeException(
                "Sign-in denied for {$email}. Use your Africa CDC Microsoft 365 account (@africacdc.org) that is linked to a staff record.",
            );
        }

        $user = User::query()
            ->where(function ($q) use ($email, $staff) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
                if ($staff->staff_id) {
                    $q->orWhere('staff_id', $staff->staff_id);
                }
            })
            ->first();

        if ($user === null) {
            throw new RuntimeException(
                "No platform user exists for {$email}. Ask an administrator to add you under Users before signing in with Microsoft 365.",
            );
        }

        $updates = [
            'name' => $user->name ?: ($profile['name'] ?: $staff->fullName()),
            'email' => $user->email ?: $email,
            'staff_id' => $staff->staff_id,
            'staff_synced_at' => now(),
        ];

        if ($user->registration_source === null) {
            $updates['registration_source'] = UserRegistrationSource::MicrosoftSso;
        }

        if (blank($user->division) && filled($staff->division_name)) {
            $updates['division'] = $staff->division_name;
        }
        if (blank($user->phone) && filled($staff->phone())) {
            $updates['phone'] = $staff->phone();
        }

        $user->fill($updates);
        $user->save();

        return $user->fresh();
    }

    private function stateKey(string $state): string
    {
        return 'microsoft_oauth_state:'.$state;
    }
}
