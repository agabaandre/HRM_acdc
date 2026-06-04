<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Auth\Models\PortalUser;

class MicrosoftAuthService
{
    public static function isConfigured(): bool
    {
        return self::tenantId() !== '' && self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function tenantId(): string
    {
        return (string) config('services.microsoft.tenant_id', '');
    }

    public static function clientId(): string
    {
        return (string) config('services.microsoft.client_id', '');
    }

    public static function clientSecret(): string
    {
        return (string) config('services.microsoft.client_secret', '');
    }

    public static function redirectUri(): string
    {
        $configured = config('services.microsoft.redirect_uri');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route('auth.microsoft.callback', [], true);
    }

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        $scopes = config('auth.microsoft.scopes', 'openid profile email offline_access User.Read');

        return array_values(array_filter(preg_split('/\s+/', (string) $scopes) ?: []));
    }

    public function authorizationUrl(): string
    {
        $state = Str::random(40);
        session([
            'microsoft_oauth_state' => $state,
            'microsoft_oauth_intended' => url()->previous(),
        ]);

        $params = http_build_query([
            'client_id' => self::clientId(),
            'response_type' => 'code',
            'redirect_uri' => self::redirectUri(),
            'response_mode' => 'query',
            'scope' => implode(' ', self::scopes()),
            'state' => $state,
        ]);

        return 'https://login.microsoftonline.com/'.self::tenantId().'/oauth2/v2.0/authorize?'.$params;
    }

    public function validateState(?string $state): bool
    {
        $expected = session('microsoft_oauth_state');
        session()->forget('microsoft_oauth_state');

        return is_string($expected) && $expected !== '' && is_string($state) && hash_equals($expected, $state);
    }

    public function exchangeCodeForToken(string $code): ?string
    {
        $url = 'https://login.microsoftonline.com/'.self::tenantId().'/oauth2/v2.0/token';
        $response = Http::asForm()->post($url, [
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'code' => $code,
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('access_token');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchGraphUser(string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->get('https://graph.microsoft.com/v1.0/me');

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    public function resolveEmailFromGraphUser(array $graphUser): ?string
    {
        $email = $graphUser['mail'] ?? $graphUser['userPrincipalName'] ?? null;
        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        return strtolower(trim($email));
    }

    public function findPortalUserByEmail(string $email): ?PortalUser
    {
        return PortalUser::query()
            ->whereHas('staff', fn ($q) => $q->where('work_email', $email))
            ->where('status', 1)
            ->first();
    }
}
