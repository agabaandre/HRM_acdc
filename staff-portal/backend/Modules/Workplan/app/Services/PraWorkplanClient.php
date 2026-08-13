<?php

namespace Modules\Workplan\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PraWorkplanClient
{
    /**
     * @return array{success?: bool, meta?: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function fetch(string $divisionCode, int $fiscalYear, ?string $tiers = null): array
    {
        $base = (string) config('workplan.pra.base_url');
        $key = (string) config('workplan.pra.api_key');
        if ($base === '' || $key === '') {
            throw new RuntimeException('PRA workplan API is not configured (PRA_WORKPLAN_API_URL / PRA_WORKPLAN_API_KEY).');
        }

        $tiers = $tiers ?? (string) config('workplan.pra.tiers', '3,4');
        $timeout = max(10, (int) config('workplan.pra.timeout', 60));
        $connectTimeout = min(30, $timeout);

        $response = Http::timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->withHeaders(['X-API-Key' => $key])
            ->get($base, [
                'fiscal_year' => $fiscalYear,
                'division' => strtoupper($divisionCode),
                'tier' => $tiers,
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'PRA workplan API failed for %s/%d (HTTP %d): %s',
                $divisionCode,
                $fiscalYear,
                $response->status(),
                mb_substr($response->body(), 0, 240),
            ));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('PRA workplan API returned invalid JSON.');
        }

        $data = $json['data'] ?? [];
        if (! is_array($data)) {
            $data = [];
        }

        return [
            'success' => (bool) ($json['success'] ?? true),
            'meta' => is_array($json['meta'] ?? null) ? $json['meta'] : [],
            'data' => array_values($data),
        ];
    }
}
