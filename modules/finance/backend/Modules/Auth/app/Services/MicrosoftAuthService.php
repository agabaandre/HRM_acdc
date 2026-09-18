<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Auth\Models\PortalUser;

class MicrosoftAuthService
{
    public static function isConfigured(): bool
    {
        return self::tenantId() !== '' && self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function tenantId(): string
    {
        return trim((string) config('services.microsoft.tenant_id', ''));
    }

    public static function clientId(): string
    {
        return trim((string) config('services.microsoft.client_id', ''));
    }

    public static function clientSecret(): string
    {
        return trim((string) config('services.microsoft.client_secret', ''));
    }

    public static function redirectUri(): string
    {
        $configured = config('services.microsoft.redirect_uri');
        $fallback = route('auth.microsoft.callback', [], true);

        if (! is_string($configured) || trim($configured) === '') {
            return $fallback;
        }

        $configured = trim($configured);

        // Ignore leftover php artisan serve URIs when APP_URL is Apache/subdir.
        if (preg_match('#://[^/]+:(8\d{3}|8000)/#', $configured)) {
            $appUrl = rtrim((string) config('app.url'), '/');
            if ($appUrl !== '' && ! preg_match('#://[^/]+:(8\d{3}|8000)(/|$)#', $appUrl)) {
                return $appUrl.'/auth/microsoft/callback';
            }
        }

        // Legacy DocumentRoot path → backend
        if (str_contains($configured, '/staff-portal/public/')) {
            return str_replace('/staff-portal/public/', '/staff-portal/backend/', $configured);
        }

        return $configured;
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
        // Align with CI3: store OAuth state without rotating the session id mid-flow.
        // (Session fixation is mitigated by regenerate-after-login in PortalLoginService.)
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));

        session([
            'microsoft_oauth_state' => $state,
            'microsoft_oauth_nonce' => $nonce,
            'microsoft_oauth_started_at' => time(),
        ]);
        // Drop any leftover PKCE verifier from older builds.
        session()->forget('microsoft_oauth_code_verifier');

        $params = [
            'client_id' => self::clientId(),
            'response_type' => 'code',
            'redirect_uri' => self::redirectUri(),
            'response_mode' => 'query',
            'scope' => implode(' ', self::scopes()),
            'state' => $state,
            'nonce' => $nonce,
            // Force account picker when switching users (CI3-friendly UX).
            'prompt' => 'select_account',
        ];

        return 'https://login.microsoftonline.com/'.rawurlencode(self::tenantId()).'/oauth2/v2.0/authorize?'.http_build_query($params);
    }

    public function validateState(?string $state): bool
    {
        $expected = session('microsoft_oauth_state');
        $started = (int) session('microsoft_oauth_started_at', 0);
        session()->forget(['microsoft_oauth_state', 'microsoft_oauth_started_at']);

        if (! is_string($expected) || $expected === '' || ! is_string($state) || $state === '') {
            return false;
        }
        if (! hash_equals($expected, $state)) {
            return false;
        }
        // Abandon stale OAuth attempts (15 minutes).
        if ($started > 0 && (time() - $started) > 900) {
            return false;
        }

        return true;
    }

    /**
     * @return array{ok: bool, access_token?: string, error?: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        session()->forget('microsoft_oauth_code_verifier');

        $payload = [
            'client_id' => self::clientId(),
            'client_secret' => self::clientSecret(),
            'code' => $code,
            'redirect_uri' => self::redirectUri(),
            'grant_type' => 'authorization_code',
        ];

        $url = 'https://login.microsoftonline.com/'.rawurlencode(self::tenantId()).'/oauth2/v2.0/token';
        $response = Http::asForm()
            ->timeout(20)
            ->acceptJson()
            ->post($url, $payload);

        if (! $response->successful()) {
            $body = $response->json();
            $msError = is_array($body)
                ? (string) ($body['error_description'] ?? $body['error'] ?? 'token_exchange_failed')
                : 'token_exchange_failed';
            Log::warning('Microsoft OAuth token exchange failed', [
                'status' => $response->status(),
                'error' => is_array($body) ? ($body['error'] ?? null) : null,
                'redirect_uri' => self::redirectUri(),
            ]);

            return [
                'ok' => false,
                'error' => self::safePublicMessage($msError, 'Could not complete Microsoft sign-in (token exchange failed).'),
            ];
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            return [
                'ok' => false,
                'error' => 'Microsoft did not return an access token. Please try again.',
            ];
        }

        return ['ok' => true, 'access_token' => $token];
    }

    /**
     * @return array{ok: bool, user?: array<string, mixed>, error?: string}
     */
    public function fetchGraphUser(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(20)
            ->get('https://graph.microsoft.com/v1.0/me');

        if (! $response->successful()) {
            Log::warning('Microsoft Graph /me failed', ['status' => $response->status()]);

            return [
                'ok' => false,
                'error' => 'Could not load your Microsoft profile. Please try again.',
            ];
        }

        $data = $response->json();
        if (! is_array($data)) {
            return [
                'ok' => false,
                'error' => 'Microsoft profile response was invalid.',
            ];
        }

        return ['ok' => true, 'user' => $data];
    }

    public function resolveEmailFromGraphUser(array $graphUser): ?string
    {
        foreach (['mail', 'userPrincipalName'] as $key) {
            $email = $graphUser[$key] ?? null;
            if (is_string($email) && trim($email) !== '' && filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                return strtolower(trim($email));
            }
        }

        // Some tenants return UPN like alias@tenant.onmicrosoft.com — still usable if staff.work_email matches.
        $upn = $graphUser['userPrincipalName'] ?? null;
        if (is_string($upn) && trim($upn) !== '') {
            return strtolower(trim($upn));
        }

        return null;
    }

    public function findPortalUserByEmail(string $email): ?PortalUser
    {
        $email = strtolower(trim($email));

        return PortalUser::query()
            ->where('status', 1)
            ->whereHas('staff', function ($q) use ($email): void {
                $q->whereRaw('LOWER(TRIM(work_email)) = ?', [$email]);
            })
            ->first();
    }

    public function clearOauthSession(): void
    {
        session()->forget([
            'microsoft_oauth_state',
            'microsoft_oauth_code_verifier',
            'microsoft_oauth_nonce',
            'microsoft_oauth_started_at',
        ]);
    }

    protected static function safePublicMessage(string $raw, string $fallback): string
    {
        $raw = trim(html_entity_decode(strip_tags($raw)));
        // Drop AADSTS verbose traces; keep a short readable sentence.
        if ($raw === '') {
            return $fallback;
        }
        if (preg_match('/AADSTS\d+/', $raw)) {
            // Prefer the first sentence / clause.
            $parts = preg_split('/[\r\n]+/', $raw) ?: [];
            $raw = trim((string) ($parts[0] ?? $raw));
        }
        if (mb_strlen($raw) > 280) {
            $raw = mb_substr($raw, 0, 277).'…';
        }

        return $raw !== '' ? $raw : $fallback;
    }
}
