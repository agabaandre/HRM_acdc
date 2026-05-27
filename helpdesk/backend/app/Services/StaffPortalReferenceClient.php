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

    /**
     * Fetch staff currently sitting in any of the given division IDs, alongside
     * their existing `staff.helpdesk_agent_at` value so the helpdesk SPA can
     * render a preview before promoting them to agents.
     *
     * @param  array<int, int>  $divisionIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchAgentsInDivisions(array $divisionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $divisionIds), fn (int $n) => $n > 0)));
        $url = $this->buildUrl('agents_in_divisions');
        if (! empty($ids)) {
            $url .= '?division_ids='.implode(',', $ids);
        }

        $payload = $this->getJsonAssoc($url);

        return is_array($payload['data'] ?? null) ? $payload['data'] : [];
    }

    /**
     * Toggle the `staff.helpdesk_agent_at` column for the given staff_ids on the
     * CodeIgniter side (Settings → General → Mark / unmark agents).
     *
     * @param  array<int, int>  $staffIds
     * @return array<string, mixed>
     */
    public function markHelpdeskAgents(array $staffIds, bool $mark): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $staffIds), fn (int $n) => $n > 0)));
        if (empty($ids)) {
            throw new RuntimeException('No staff_ids supplied to markHelpdeskAgents.');
        }
        $url = $this->buildUrl('mark_agents');

        $response = Http::withBasicAuth($this->username(), $this->password())
            ->timeout(60)
            ->acceptJson()
            ->asJson()
            ->post($url, ['staff_ids' => $ids, 'mark' => $mark]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Staff API returned non-array JSON when marking agents.');
        }

        return $payload;
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

    /**
     * Same as getJson() but returns the full JSON envelope (associative array)
     * — used by endpoints that wrap rows in { success, data, total } instead of
     * returning a bare list.
     *
     * @return array<string, mixed>
     */
    private function getJsonAssoc(string $url): array
    {
        $response = Http::withBasicAuth($this->username(), $this->password())
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

        return $data;
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
