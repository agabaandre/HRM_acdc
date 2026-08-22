<?php

namespace Modules\Workplan\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PraWorkplanClient
{
    public function __construct(
        protected PraWorkplanSettingsService $settings,
    ) {}

    /**
     * @return array{success?: bool, meta?: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function fetch(string $divisionCode, int $fiscalYear, ?string $tiers = null): array
    {
        $pra = $this->settings->resolved();
        $base = (string) $pra['base_url'];
        $key = (string) $pra['api_key'];
        if ($base === '' || $key === '') {
            throw new RuntimeException('PRA workplan API is not configured. Add the URL and API key in Settings → Workplan / PRA.');
        }

        $tiers = $tiers ?? (string) $pra['tiers'];
        $timeout = max(10, (int) $pra['timeout']);
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
