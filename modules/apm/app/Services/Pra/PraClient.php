<?php

namespace App\Services\Pra;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PraClient
{
    public function __construct(
        protected PraSettingsService $settings,
    ) {}

    /**
     * Fetch workplan indicators (all divisions when $divisionCode is null).
     *
     * @return array{success?: bool, meta?: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function fetchWorkplan(?string $divisionCode = null, ?int $fiscalYear = null, ?string $tiers = null): array
    {
        $pra = $this->settings->resolved();
        $base = (string) $pra['base_url'];
        $key = (string) $pra['api_key'];
        if ($base === '' || $key === '') {
            throw new RuntimeException('PRA API is not configured. Add the URL and API key on the PRA integration tab.');
        }

        $year = $fiscalYear ?? (int) ($pra['fiscal_year'] ?: now()->year);
        $tiers = $tiers ?? (string) $pra['tiers'];
        $timeout = max(10, (int) $pra['timeout']);
        // Full-FY workplan (all divisions) can take several minutes — do not time out.
        $isFullSync = $divisionCode === null || $divisionCode === '';
        $requestTimeout = $isFullSync ? 0 : $timeout;
        $connectTimeout = min(30, max(10, $timeout));

        $query = [
            'fiscal_year' => $year,
            'tier' => $tiers,
            'format' => 'json',
        ];
        if (! $isFullSync) {
            $query['division'] = strtoupper($divisionCode);
        }

        $response = Http::timeout($requestTimeout)
            ->connectTimeout($connectTimeout)
            ->retry(2, 500, throw: false)
            ->acceptJson()
            ->withHeaders(['X-API-Key' => $key])
            ->get($base, $query);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'PRA API failed (HTTP %d): %s',
                $response->status(),
                mb_substr($response->body(), 0, 240),
            ));
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('PRA API returned invalid JSON.');
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
