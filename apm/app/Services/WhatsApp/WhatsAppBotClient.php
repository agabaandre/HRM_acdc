<?php

namespace App\Services\WhatsApp;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for jacktheboss220/WhatsAppBotMultiDevice admin API.
 *
 * @see https://github.com/jacktheboss220/WhatsAppBotMultiDevice
 */
class WhatsAppBotClient
{
    public function __construct(
        private readonly WhatsAppConfig $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function publicStatus(?string $apiUrl = null): array
    {
        $base = $this->resolveApiUrl($apiUrl);

        try {
            $response = Http::timeout(8)->get($base.'/api/status');

            if (! $response->successful()) {
                return [
                    'reachable' => true,
                    'connected' => false,
                    'registered' => false,
                    'error' => 'Status endpoint returned HTTP '.$response->status(),
                ];
            }

            $data = $response->json();

            return [
                'reachable' => true,
                'connected' => (bool) ($data['connected'] ?? false),
                'registered' => (bool) ($data['registered'] ?? false),
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            return [
                'reachable' => false,
                'connected' => false,
                'registered' => false,
                'error' => $this->connectionHint($base, $e),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function adminStats(?string $apiUrl = null, ?string $adminPassword = null): array
    {
        return $this->authorizedJson('GET', '/api/admin/stats', [], $apiUrl, $adminPassword);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groups(): array
    {
        $data = $this->authorizedJson('GET', '/api/admin/groups');

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function updateGroup(string $jid, array $fields): array
    {
        $encoded = rawurlencode($jid);

        return $this->authorizedJson('PATCH', '/api/admin/groups/'.$encoded, $fields);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groupMembers(string $jid): array
    {
        $encoded = rawurlencode($jid);
        $data = $this->authorizedJson('GET', '/api/admin/groups/'.$encoded.'/members');

        return is_array($data) ? $data : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizedJson(
        string $method,
        string $path,
        array $body = [],
        ?string $apiUrl = null,
        ?string $adminPassword = null,
    ): array {
        $base = $this->resolveApiUrl($apiUrl);
        $password = $adminPassword ?? $this->config->adminPassword();

        if ($password === '') {
            throw new RuntimeException('Admin password is not set. Enter ADMIN_PASSWORD from the bot .env and save settings.');
        }

        $jar = new CookieJar;

        try {
            $login = Http::timeout(12)
                ->withOptions(['cookies' => $jar])
                ->post($base.'/api/admin/login', [
                    'password' => $password,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException($this->connectionHint($base, $e));
        }

        if (! $login->successful()) {
            throw new RuntimeException('WhatsApp bot login failed. Check the admin password in settings.');
        }

        $loginBody = $login->json();
        if (! ($loginBody['ok'] ?? false)) {
            throw new RuntimeException('WhatsApp bot rejected the admin password.');
        }

        $request = Http::timeout(20)->withOptions(['cookies' => $jar])->acceptJson();

        $response = match (strtoupper($method)) {
            'GET' => $request->get($base.$path),
            'PATCH' => $request->patch($base.$path, $body),
            'POST' => $request->post($base.$path, $body),
            default => throw new RuntimeException('Unsupported HTTP method: '.$method),
        };

        if ($response->status() === 401) {
            throw new RuntimeException('WhatsApp bot session unauthorized.');
        }

        if (! $response->successful()) {
            $message = $response->json('error') ?? $response->body();
            throw new RuntimeException(is_string($message) ? $message : 'WhatsApp bot request failed.');
        }

        $json = $response->json();

        return is_array($json) ? $json : ['ok' => true];
    }

    private function resolveApiUrl(?string $apiUrl): string
    {
        $url = trim($apiUrl ?? $this->config->apiUrl());

        return rtrim($url !== '' ? $url : 'http://127.0.0.1:8000', '/');
    }

    private function connectionHint(string $base, ConnectionException $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'Failed to connect') || str_contains($message, 'Connection refused')) {
            return 'Cannot reach the bot at '.$base.'. Start WhatsAppBotMultiDevice on that host (e.g. `pnpm start` in the bot folder; default port 8000). '
                .'If APM runs in Docker or on another machine, use a URL reachable from the PHP server — not 127.0.0.1 unless the bot runs on the same host as PHP.';
        }

        return $message;
    }
}
