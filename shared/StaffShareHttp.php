<?php

namespace Staff\Shared;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for Laravel Staff Share API (CI3-compatible /share/*).
 *
 * Auth (in order):
 * 1. POST /share/token with STAFF_API_USERNAME + STAFF_API_PASSWORD → Bearer JWT
 * 2. Else STAFF_API_TOKEN as Bearer and/or path segment (CI3 / APM style)
 *
 * Docs: /staff/backend/share/docs
 */
final class StaffShareHttp
{
    private ?string $jwt = null;

    private int $jwtExpiresAt = 0;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly string $staticToken = '',
        private readonly int $timeoutSeconds = 120,
    ) {}

    /**
     * @param  array<string, mixed>  $config  services.staff_api or helpdesk.staff_api
     */
    public static function fromConfig(array $config, int $timeoutSeconds = 120): self
    {
        $base = rtrim(trim((string) ($config['base_url'] ?? '')), '/');
        if ($base === '') {
            $base = 'http://127.0.0.1/staff/backend';
        }
        // Legacy /staff → /staff/backend (Laravel Share mount).
        $path = (string) (parse_url($base, PHP_URL_PATH) ?? '');
        $path = rtrim($path, '/');
        if ($path === '/staff' || $path === '/demo_staff') {
            $base .= '/backend';
        }

        return new self(
            $base,
            trim((string) ($config['username'] ?? '')),
            trim((string) ($config['password'] ?? '')),
            trim((string) ($config['token'] ?? '')),
            $timeoutSeconds,
        );
    }

    public function isConfigured(): bool
    {
        $hasBasic = $this->username !== '' && $this->password !== '';
        $hasToken = $this->staticToken !== '';

        return $hasBasic || $hasToken;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * GET a Share path (e.g. /share/get_current_staff) and return decoded JSON.
     *
     * @param  array<string, scalar|null>  $query
     * @return array<int|string, mixed>
     */
    public function getJson(string $path, array $query = []): array
    {
        $url = $this->url($path);
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        $response = $this->http()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Staff Share API returned non-array JSON.');
        }

        return $data;
    }

    /**
     * POST JSON to a Share path.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $body = []): array
    {
        $response = $this->http()->asJson()->post($this->url($path), $body);
        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Staff Share API returned non-array JSON.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * Obtain a Bearer JWT via POST /share/token (Basic Auth). Cached until near expiry.
     * Returns null when credentials are missing or the Share API rejects them
     * (callers then use STAFF_API_TOKEN).
     */
    public function accessToken(): ?string
    {
        if ($this->jwt !== null && time() < $this->jwtExpiresAt) {
            return $this->jwt;
        }
        if ($this->username === '' || $this->password === '') {
            return null;
        }

        $response = Http::withBasicAuth($this->username, $this->password)
            ->timeout(30)
            ->acceptJson()
            ->post($this->baseUrl.'/share/token');

        if (! $response->successful()) {
            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600));
        $this->jwt = $token;
        $this->jwtExpiresAt = time() + $ttl - 60;

        return $this->jwt;
    }

    private function http(): PendingRequest
    {
        $req = Http::timeout($this->timeoutSeconds)
            ->retry(2, 1000, null, false)
            ->acceptJson();

        $jwt = $this->accessToken();
        if ($jwt !== null) {
            return $req->withToken($jwt);
        }

        // Static Share token works as Bearer (AuthenticateShareApi::staticTokenValid).
        if ($this->staticToken !== '') {
            $req = $req->withToken($this->staticToken);
        }

        // Keep Basic Auth for CI3 parity when credentials are present (even if token issue failed).
        if ($this->username !== '' && $this->password !== '') {
            $req = $req->withBasicAuth($this->username, $this->password);
        }

        return $req;
    }

    private function url(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        // When using JWT, path token is unnecessary. Otherwise append STAFF_API_TOKEN
        // for CI3 / APM URL shape: /share/get_current_staff/{token}
        if ($this->accessToken() === null && $this->staticToken !== '') {
            return $this->baseUrl.$path.'/'.$this->staticToken;
        }

        return $this->baseUrl.$path;
    }

    private function formatHttpError(Response $response): string
    {
        $status = $response->status();
        $body = $response->json();
        $remote = '';
        if (is_array($body)) {
            $remote = (string) ($body['message'] ?? $body['error'] ?? '');
        }
        $msg = 'Staff Share API HTTP '.$status;
        if ($remote !== '') {
            $msg .= ': '.$remote;
        }
        if ($status === 401) {
            $msg .= ' — Auth failed. Set STAFF_API_USERNAME + STAFF_API_PASSWORD (portal login email/password)'
                .' so POST /share/token can issue a JWT, and/or set STAFF_API_TOKEN to the Share static token.'
                .' Base URL must be the Laravel Share mount (…/staff/backend). See /staff/backend/share/docs.';
        }

        return $msg;
    }
}
