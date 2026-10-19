<?php

namespace App\Services\StaffShare;

use App\Models\StaffShareSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Client for the Africa CDC Staff Share API.
 *
 * Auth (preferred for directory sync): static Bearer token
 *   Authorization: Bearer {STAFF_API_TOKEN}
 * @see https://cbp.africacdc.org/staff/backend/share/docs
 */
class StaffShareClient
{
    public function enabled(): bool
    {
        $this->hydrateConfigFromSettings();

        return (bool) config('services.staff_share.enabled', true)
            && filled(config('services.staff_share.base_url'));
    }

    /**
     * Overlay UI-saved settings (encrypted Bearer token + base URL) onto config.
     */
    private function hydrateConfigFromSettings(): void
    {
        static $hydrated = false;
        if ($hydrated) {
            return;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('staff_share_settings')) {
                $hydrated = true;

                return;
            }
            StaffShareSetting::current()->applyToConfig();
        } catch (Throwable) {
            // Settings table may not exist yet during migrate.
        }

        $hydrated = true;
    }

    /**
     * Preferred auth (API v2.1+): POST /share/login with JSON username + password.
     *
     * @return array{
     *   access_token: string,
     *   token_type?: string,
     *   expires_in?: int,
     *   user?: array<string, mixed>|null
     * }
     */
    public function login(string $username, string $password): array
    {
        $response = $this->http()
            ->acceptJson()
            ->asJson()
            ->post($this->url('/share/login'), [
                'username' => $username,
                'password' => $password,
            ]);

        return $this->parseTokenResponse($response, 'login');
    }

    /**
     * Legacy auth: POST /share/token with HTTP Basic Auth.
     *
     * @return array{access_token: string, token_type?: string, expires_in?: int, user?: array<string, mixed>|null}
     */
    public function issueToken(string $username, string $password): array
    {
        $response = $this->http()
            ->withHeaders([
                'Authorization' => 'Basic '.base64_encode($username.':'.$password),
                'Accept' => 'application/json',
            ])
            ->asJson()
            ->post($this->url('/share/token'), []);

        return $this->parseTokenResponse($response, 'token');
    }

    /**
     * Authenticate using preferred /share/login, falling back to legacy /share/token.
     *
     * @return array{access_token: string, token_type?: string, expires_in?: int, user?: array<string, mixed>|null}
     */
    public function authenticate(string $username, string $password): array
    {
        try {
            return $this->login($username, $password);
        } catch (StaffShareAuthException $e) {
            // Prefer login errors for wrong passwords; only fall back on transport/shape issues.
            if (str_contains(strtolower($e->getMessage()), 'invalid')) {
                throw $e;
            }
        } catch (RuntimeException $e) {
            // Fall through to legacy Basic token endpoint.
        }

        return $this->issueToken($username, $password);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCurrentStaff(
        ?string $bearerToken = null,
        ?int $limit = null,
        int $start = 0,
        ?string $basicUsername = null,
        ?string $basicPassword = null,
    ): array {
        $query = array_filter([
            'limit' => $limit ?? (int) config('services.staff_share.page_size', 200),
            'start' => $start,
        ], fn ($v) => $v !== null);

        $request = $this->http()->acceptJson();
        $request = $this->withAuth($request, $bearerToken, $basicUsername, $basicPassword);

        $response = $request->get($this->url('/share/get_current_staff'), $query);

        if ($response->status() === 401) {
            throw new StaffShareAuthException(
                (string) ($response->json('error') ?: 'Staff Share authentication failed.'),
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                'Staff Share get_current_staff failed (HTTP '.$response->status().').',
            );
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return [];
        }

        if (array_is_list($payload)) {
            /** @var list<array<string, mixed>> $payload */
            return $payload;
        }

        foreach (['data', 'staff', 'rows'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) {
                /** @var list<array<string, mixed>> $rows */
                $rows = $payload[$key];

                return $rows;
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllStaff(
        ?string $bearerToken = null,
        ?string $basicUsername = null,
        ?string $basicPassword = null,
    ): array {
        $pageSize = max(1, (int) config('services.staff_share.page_size', 200));
        $start = 0;
        $all = [];

        $basicUsername ??= config('services.staff_share.username');
        $basicPassword ??= config('services.staff_share.password');

        do {
            $chunk = $this->getCurrentStaff(
                $bearerToken,
                $pageSize,
                $start,
                filled($basicUsername) ? (string) $basicUsername : null,
                $basicPassword !== null && $basicPassword !== '' ? (string) $basicPassword : null,
            );
            if ($chunk === []) {
                break;
            }
            array_push($all, ...$chunk);
            $start += $pageSize;
        } while (count($chunk) >= $pageSize);

        return $all;
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     * @return array{access_token: string, token_type?: string, expires_in?: int, user?: array<string, mixed>|null}
     */
    private function parseTokenResponse($response, string $endpoint): array
    {
        if ($response->status() === 401 || $response->status() === 422) {
            throw new StaffShareAuthException(
                (string) ($response->json('error') ?: 'Invalid Staff SSO credentials.'),
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                "Staff Share /share/{$endpoint} failed (HTTP ".$response->status().').',
            );
        }

        $token = (string) ($response->json('access_token') ?: $response->json('token') ?: '');
        if ($token === '') {
            throw new RuntimeException("Staff Share /share/{$endpoint} did not return access_token.");
        }

        $user = $response->json('user');

        return [
            'access_token' => $token,
            'token_type' => (string) $response->json('token_type', 'Bearer'),
            'expires_in' => (int) $response->json('expires_in', 3600),
            'user' => is_array($user) ? $user : null,
        ];
    }

    private function withAuth(
        PendingRequest $request,
        ?string $bearerToken = null,
        ?string $basicUsername = null,
        ?string $basicPassword = null,
    ): PendingRequest {
        $this->hydrateConfigFromSettings();

        if (filled($bearerToken)) {
            return $request->withToken($bearerToken);
        }

        if (filled($basicUsername) && $basicPassword !== null) {
            return $request->withHeaders([
                'Authorization' => 'Basic '.base64_encode($basicUsername.':'.$basicPassword),
            ]);
        }

        $apiToken = config('services.staff_share.api_token');
        if (filled($apiToken)) {
            // Static API token — Authorization: Bearer {STAFF_API_TOKEN}
            return $request->withToken((string) $apiToken);
        }

        $cfgUser = config('services.staff_share.username');
        $cfgPass = config('services.staff_share.password');
        if (filled($cfgUser) && $cfgPass !== null && $cfgPass !== '') {
            return $request->withHeaders([
                'Authorization' => 'Basic '.base64_encode($cfgUser.':'.$cfgPass),
            ]);
        }

        throw new RuntimeException(
            'Staff Share API credentials are not configured. Save a Bearer token in Staff Share settings.',
        );
    }

    private function http(): PendingRequest
    {
        $this->hydrateConfigFromSettings();

        return Http::timeout((int) config('services.staff_share.timeout', 30))
            ->connectTimeout(10)
            ->withOptions(['http_errors' => false]);
    }

    private function url(string $path): string
    {
        $this->hydrateConfigFromSettings();

        return rtrim((string) config('services.staff_share.base_url'), '/').'/'.ltrim($path, '/');
    }
}
