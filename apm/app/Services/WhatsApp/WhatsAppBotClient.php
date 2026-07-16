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
    public function publicStatus(): array
    {
        try {
            $response = Http::timeout(8)->get($this->config->apiUrl().'/api/status');

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
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function adminStats(): array
    {
        return $this->authorizedJson('GET', '/api/admin/stats');
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
    private function authorizedJson(string $method, string $path, array $body = []): array
    {
        if (! $this->config->isConfigured()) {
            throw new RuntimeException('WhatsApp bot is not configured. Set API URL and admin password in System configs → WhatsApp.');
        }

        $jar = new CookieJar;
        $base = $this->config->apiUrl();

        $login = Http::timeout(12)
            ->withOptions(['cookies' => $jar])
            ->post($base.'/api/admin/login', [
                'password' => $this->config->adminPassword(),
            ]);

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
}
