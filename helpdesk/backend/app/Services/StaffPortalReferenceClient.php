<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Calls CodeIgniter Staff Share API using the same URL + Basic Auth pattern as APM
 * (`staff:sync`, `divisions:sync` — same `BASE_URL`, path `/share/...`, URL token, and `STAFF_API_*` credentials).
 */
class StaffPortalReferenceClient
{
    public function isConfigured(): bool
    {
        $u = $this->username();

        $p = $this->password();

        return $u !== '' && $p !== '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchDivisions(): array
    {
        return $this->getJson($this->buildUrl('divisions'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchDirectorates(): array
    {
        return $this->getJson($this->buildUrl('directorates'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchStaff(int $limit, int $start = 0): array
    {
        $url = $this->buildUrl('staff');
        $url .= '?limit='.max(1, min($limit, 20000)).'&start='.max(0, $start);

        return $this->getJson($url);
    }

    private function buildUrl(string $endpointKey): string
    {
        $base = rtrim((string) config('helpdesk.staff_api.base_url'), '/');
        $path = trim((string) config('helpdesk.staff_api.endpoints.'.$endpointKey));
        if ($path === '') {
            throw new RuntimeException('Missing staff_api endpoint: '.$endpointKey);
        }
        $token = trim((string) config('helpdesk.staff_api.token'));
        if ($token === '') {
            throw new RuntimeException('Missing HELPDESK_STAFF_API_TOKEN / STAFF_API_TOKEN.');
        }

        return $base.$path.'/'.$token;
    }

    private function username(): string
    {
        return trim((string) config('helpdesk.staff_api.username', ''));
    }

    private function password(): string
    {
        return trim((string) config('helpdesk.staff_api.password', ''));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getJson(string $url): array
    {
        $username = $this->username();
        $password = $this->password();

        $response = Http::withBasicAuth($username, $password)
            ->timeout(120)
            ->retry(2, 1000, null, false)
            ->acceptJson()
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Staff API returned non-array JSON.');
        }

        /** @var array<int, array<string, mixed>> $out */
        $out = array_values(array_map(function ($row) {
            return is_array($row) ? $row : (array) $row;
        }, $data));

        return $out;
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
            $msg .= ' — Basic Auth failed. `STAFF_API_USERNAME` must be the **login email** of a Staff portal user authorised for the Share API, and `STAFF_API_PASSWORD` must be that user’s current password (same values as in `apm/.env` where `php artisan staff:sync` works). Placeholder values from `.env.example` will not work. Trim any accidental spaces in `.env`; run `php artisan config:clear` after edits.';
        }

        return $msg;
    }
}
