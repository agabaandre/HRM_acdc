<?php

namespace App\Services;

use App\Models\HelpdeskCategory;
use App\Models\HelpdeskKbArticle;
use App\Models\HelpdeskSetting;
use App\Models\User;
use Illuminate\Support\Str;

class FaqIngestService
{
    public function __construct(
        private readonly FaqExportClient $client,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function configuredSources(): array
    {
        $raw = HelpdeskSetting::getValue(HelpdeskSetting::KEY_FAQ_SOURCES_JSON);
        if ($raw === null || trim($raw) === '') {
            return $this->defaultSources();
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $this->defaultSources();
        }

        return is_array($decoded) ? $decoded : $this->defaultSources();
    }

    /**
     * @return array<string, mixed>
     */
    public function ingestAll(?User $actor = null): array
    {
        $sources = array_values(array_filter(
            $this->configuredSources(),
            fn (array $s) => filter_var($s['enabled'] ?? true, FILTER_VALIDATE_BOOL)
        ));

        $summary = [
            'started_at' => now()->toIso8601String(),
            'sources' => [],
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
            'errors' => [],
        ];

        foreach ($sources as $source) {
            $sourceId = (string) ($source['id'] ?? 'unknown');
            $url = $this->resolveSourceUrl($source);
            $row = [
                'id' => $sourceId,
                'label' => (string) ($source['label'] ?? $sourceId),
                'url' => $url,
                'created' => 0,
                'updated' => 0,
                'deactivated' => 0,
                'status' => 'ok',
            ];

            try {
                $payload = $this->client->fetch($url);
                $counts = $this->upsertPayload($payload, $source, $actor);
                $row['created'] = $counts['created'];
                $row['updated'] = $counts['updated'];
                $row['deactivated'] = $counts['deactivated'];
                $summary['created'] += $counts['created'];
                $summary['updated'] += $counts['updated'];
                $summary['deactivated'] += $counts['deactivated'];
            } catch (\Throwable $e) {
                $row['status'] = 'error';
                $row['error'] = $e->getMessage();
                $summary['errors'][] = [
                    'source' => $sourceId,
                    'message' => $e->getMessage(),
                ];
            }

            $summary['sources'][] = $row;
        }

        $summary['finished_at'] = now()->toIso8601String();
        HelpdeskSetting::setValue(HelpdeskSetting::KEY_FAQ_INGEST_LAST_RESULT, json_encode($summary));

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function resolveSourceUrl(array $source): string
    {
        $url = trim((string) ($source['url'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $apmBase = rtrim((string) config('helpdesk.apm_base_url'), '/');

        return $apmBase.'/api/apm/v1/faqs/export';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $source
     * @return array{created: int, updated: int, deactivated: int}
     */
    private function upsertPayload(array $payload, array $source, ?User $actor): array
    {
        $sourceKey = (string) ($payload['source'] ?? $source['id'] ?? 'external');
        $sourceUrl = (string) ($payload['source_url'] ?? $source['url'] ?? '');
        /** @var array<string, string> $categoryMap */
        $categoryMap = is_array($source['category_map'] ?? null) ? $source['category_map'] : $this->defaultCategoryMap();
        $deactivateMissing = filter_var($source['deactivate_missing'] ?? true, FILTER_VALIDATE_BOOL);

        $faqs = is_array($payload['faqs'] ?? null) ? $payload['faqs'] : [];
        $seenExternalIds = [];
        $created = 0;
        $updated = 0;

        foreach ($faqs as $faq) {
            if (! is_array($faq)) {
                continue;
            }

            $externalId = trim((string) ($faq['external_id'] ?? ''));
            if ($externalId === '') {
                continue;
            }

            $question = trim((string) ($faq['question'] ?? ''));
            if ($question === '') {
                continue;
            }

            $answerHtml = (string) ($faq['answer_html'] ?? $faq['answer'] ?? '');
            $sanitized = HtmlSanitizer::sanitize($answerHtml);
            if ($sanitized === null) {
                $sanitized = trim(strip_tags($answerHtml));
            }
            if ($sanitized === '') {
                continue;
            }

            $categorySlug = (string) ($faq['category_slug'] ?? '');
            $mappedSlug = $categoryMap[$categorySlug] ?? $categorySlug;
            if ($mappedSlug === '') {
                $mappedSlug = 'other-systems-support';
            }

            $category = HelpdeskCategory::query()->where('slug', $mappedSlug)->first();
            if ($category === null) {
                $category = HelpdeskCategory::query()->where('is_active', true)->orderBy('sort_order')->first();
            }
            if ($category === null) {
                continue;
            }

            $hash = hash('sha256', $question.'|'.$sanitized);
            $seenExternalIds[] = $externalId;

            $attributes = [
                'category_id' => $category->id,
                'question' => Str::limit($question, 255, ''),
                'answer' => $sanitized,
                'sort_order' => (int) ($faq['sort_order'] ?? 0),
                'is_active' => filter_var($faq['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                'source' => $sourceKey,
                'source_url' => $sourceUrl !== '' ? $sourceUrl : null,
                'search_keywords' => $this->normalizeKeywords($faq['search_keywords'] ?? null),
                'ingested_at' => now(),
                'content_hash' => $hash,
                'updated_by_user_id' => $actor?->id,
            ];

            $existing = HelpdeskKbArticle::query()
                ->where('source', $sourceKey)
                ->where('external_id', $externalId)
                ->first();

            if ($existing === null) {
                HelpdeskKbArticle::query()->create(array_merge($attributes, [
                    'external_id' => $externalId,
                    'created_by_user_id' => $actor?->id,
                ]));
                $created++;

                continue;
            }

            if ($existing->content_hash !== $hash
                || $existing->category_id !== $category->id
                || $existing->is_active !== $attributes['is_active']) {
                $existing->fill($attributes);
                $existing->save();
                $updated++;
            } else {
                $existing->forceFill([
                    'ingested_at' => now(),
                    'source_url' => $attributes['source_url'],
                ]);
                $existing->save();
            }
        }

        $deactivated = 0;
        if ($deactivateMissing && $seenExternalIds !== []) {
            $deactivated = HelpdeskKbArticle::query()
                ->where('source', $sourceKey)
                ->whereNotIn('external_id', $seenExternalIds)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deactivated' => (int) $deactivated,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultSources(): array
    {
        $apmBase = rtrim((string) config('helpdesk.apm_base_url'), '/');

        return [
            [
                'id' => 'apm',
                'label' => 'APM & Staff Portal FAQs',
                'url' => $apmBase.'/api/apm/v1/faqs/export',
                'format' => 'apm_export',
                'enabled' => true,
                'deactivate_missing' => true,
                'category_map' => $this->defaultCategoryMap(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function defaultCategoryMap(): array
    {
        return [
            'approvals-management-system' => 'apm-support',
            'staff-portal' => 'staff-portal',
        ];
    }

    private function normalizeKeywords(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = trim((string) $raw);

        return $text !== '' ? Str::limit($text, 1000, '') : null;
    }
}
