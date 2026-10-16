<?php

namespace App\Services;

use App\Support\RrSettingsBag;
use Throwable;

/**
 * Manage AfEF line categories used on Funding Portfolio data entry.
 */
final class FinanceAfefCategoryService
{
    public const KEY = 'finance_afef_categories';

    /** @var list<array{key: string, label: string, sort_order: int, is_active: bool}> */
    private const DEFAULTS = [
        ['key' => 'opening', 'label' => 'Opening / brought forward', 'sort_order' => 10, 'is_active' => true],
        ['key' => 'income', 'label' => 'Income / additional funds', 'sort_order' => 20, 'is_active' => true],
        ['key' => 'expense', 'label' => 'Expense', 'sort_order' => 30, 'is_active' => true],
        ['key' => 'earmarked', 'label' => 'Earmarked', 'sort_order' => 40, 'is_active' => true],
        ['key' => 'cphia', 'label' => 'CPHIA', 'sort_order' => 50, 'is_active' => true],
        ['key' => 'balance', 'label' => 'Balance / total', 'sort_order' => 60, 'is_active' => true],
    ];

    public function __construct(private readonly RrSettingsBag $bag = new RrSettingsBag) {}

    /**
     * @return list<array{key: string, label: string, sort_order: int, is_active: bool}>
     */
    public function all(bool $activeOnly = false): array
    {
        $rows = $this->load();
        if ($rows === []) {
            $rows = self::DEFAULTS;
            $this->persist($rows);
        }
        if ($activeOnly) {
            $rows = array_values(array_filter($rows, fn ($r) => ! empty($r['is_active'])));
        }
        usort($rows, fn ($a, $b) => ($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['label'], $b['label']));

        return $rows;
    }

    /**
     * @param  list<array{key?: string, label?: string, sort_order?: int|string, is_active?: bool|int|string}>  $rows
     * @return list<array{key: string, label: string, sort_order: int, is_active: bool}>
     */
    public function save(array $rows): array
    {
        $clean = [];
        $seen = [];
        foreach ($rows as $i => $row) {
            $key = $this->slug((string) ($row['key'] ?? ''), (string) ($row['label'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($key === '' || $label === '') {
                continue;
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = [
                'key' => $key,
                'label' => $label,
                'sort_order' => (int) ($row['sort_order'] ?? (($i + 1) * 10)),
                'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        $this->persist($clean);

        return $this->all();
    }

    public function ensureDefaults(): void
    {
        if ($this->load() !== []) {
            return;
        }
        $this->persist(self::DEFAULTS);
    }

    /**
     * @return list<array{key: string, label: string, sort_order: int, is_active: bool}>
     */
    private function load(): array
    {
        $json = $this->bag->get(self::KEY);
        if ($json === null || trim($json) === '') {
            return [];
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return [];
            }
            $out = [];
            foreach ($decoded as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $key = trim((string) ($row['key'] ?? ''));
                $label = trim((string) ($row['label'] ?? ''));
                if ($key === '' || $label === '') {
                    continue;
                }
                $out[] = [
                    'key' => $key,
                    'label' => $label,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ];
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<array{key: string, label: string, sort_order: int, is_active: bool}>  $rows
     */
    private function persist(array $rows): void
    {
        $this->bag->set(self::KEY, json_encode(array_values($rows), JSON_THROW_ON_ERROR));
    }

    private function slug(string $key, string $label): string
    {
        $raw = trim($key) !== '' ? $key : $label;
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $raw) ?? ''));
        $slug = trim($slug, '_');

        return $slug;
    }
}
