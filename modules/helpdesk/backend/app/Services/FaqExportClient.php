<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches FAQ export JSON from APM (or any URL using the same schema + Basic Auth).
 */
class FaqExportClient
{
    public function isConfigured(): bool
    {
        return $this->username() !== '' && $this->password() !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function fetch(string $url): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Staff Share API credentials are not configured (STAFF_API_USERNAME / STAFF_API_PASSWORD).');
        }

        $response = Http::withBasicAuth($this->username(), $this->password())
            ->timeout(60)
            ->retry(2, 1000, null, false)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response, $url));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('FAQ export returned non-JSON from '.$url);
        }

        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            throw new RuntimeException('FAQ export payload missing data object from '.$url);
        }

        return $data;
    }

    private function username(): string
    {
        return trim((string) config('helpdesk.staff_api.username'));
    }

    private function password(): string
    {
        return (string) config('helpdesk.staff_api.password');
    }

    private function formatHttpError(Response $response, string $url): string
    {
        $status = $response->status();
        $body = $response->json();
        $remote = '';
        if (is_array($body)) {
            $remote = (string) ($body['message'] ?? $body['error'] ?? '');
        }

        $msg = 'FAQ export HTTP '.$status.' from '.$url;
        if ($remote !== '') {
            $msg .= ': '.$remote;
        }

        return $msg;
    }
}
