<?php

namespace Modules\Leave\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class OpenHolidaysClient
{
    public const BASE = 'https://openholidaysapi.org';

    /**
     * @return list<array{iso: string, name: string}>
     */
    public function countries(string $languageIsoCode = 'EN'): array
    {
        $rows = $this->getJson('/Countries', ['languageIsoCode' => $languageIsoCode]);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $iso = strtoupper((string) ($row['isoCode'] ?? ''));
            if ($iso === '') {
                continue;
            }
            $out[] = [
                'iso' => $iso,
                'name' => $this->localized($row['name'] ?? [], $languageIsoCode) ?: $iso,
            ];
        }
        usort($out, static fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * @return list<array{
     *     openholidays_id: string,
     *     name: string,
     *     start_date: string,
     *     nationwide: bool,
     *     recurrence: string,
     *     is_movable: bool
     * }>
     */
    public function publicHolidays(string $countryIsoCode, int $year, string $languageIsoCode = 'EN'): array
    {
        $iso = strtoupper($countryIsoCode);
        $rows = $this->getJson('/PublicHolidays', [
            'countryIsoCode' => $iso,
            'languageIsoCode' => $languageIsoCode,
            'validFrom' => sprintf('%d-01-01', $year),
            'validTo' => sprintf('%d-12-31', $year),
        ]);

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['nationwide'])) {
                continue;
            }
            $name = $this->localized($row['name'] ?? [], $languageIsoCode);
            $start = (string) ($row['startDate'] ?? '');
            if ($name === '' || $start === '') {
                continue;
            }
            $movable = $this->looksMovable($name);
            $out[] = [
                'openholidays_id' => (string) ($row['id'] ?? ''),
                'name' => $name,
                'start_date' => $start,
                'nationwide' => true,
                'recurrence' => $movable ? 'once' : 'yearly_md',
                'is_movable' => $movable,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getJson(string $path, array $query): array
    {
        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->get(self::BASE.$path, $query)
                ->throw();
        } catch (RequestException $e) {
            throw new \RuntimeException('OpenHolidays request failed: '.$e->getMessage(), 0, $e);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  mixed  $names
     */
    protected function localized(mixed $names, string $languageIsoCode): string
    {
        if (! is_array($names)) {
            return is_string($names) ? $names : '';
        }
        $want = strtoupper($languageIsoCode);
        $fallback = '';
        foreach ($names as $item) {
            if (! is_array($item)) {
                continue;
            }
            $text = trim((string) ($item['text'] ?? ''));
            $lang = strtoupper((string) ($item['language'] ?? ''));
            if ($text === '') {
                continue;
            }
            if ($lang === $want) {
                return $text;
            }
            if ($fallback === '') {
                $fallback = $text;
            }
        }

        return $fallback;
    }

    protected function looksMovable(string $name): bool
    {
        return (bool) preg_match('/eid|mawlid|good friday|easter|ascension|whit|corpus/i', $name);
    }
}
