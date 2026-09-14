<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\NonTravelMemo;
use App\Models\OtherMemo;
use App\Models\SpecialMemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolve pasted APM memo URLs to approved documents for other-memo references.
 */
final class ApprovedMemoReferenceResolver
{
    /**
     * @return array{model: string, id: int, url: string, document_number: ?string, title: string, memo_kind: string}|null
     */
    public function resolveFromUrl(string $url): ?array
    {
        $diagnosis = $this->diagnoseUrl($url);

        return $diagnosis['ok'] ? $diagnosis['row'] : null;
    }

    /**
     * @return array{ok: bool, row?: array{model: string, id: int, url: string, document_number: ?string, title: string, memo_kind: string}, error?: string}
     */
    public function diagnoseUrl(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['ok' => false, 'error' => 'Link is empty.'];
        }

        $parsed = $this->parsePath($url);
        if ($parsed === null) {
            return [
                'ok' => false,
                'error' => 'Unrecognized link. Paste the full address from your browser when viewing the memo (must include /apm/ and the memo id).',
            ];
        }

        [$modelClass, $id] = $parsed;
        $record = $modelClass::query()->find($id);
        if (! $record instanceof Model) {
            return ['ok' => false, 'error' => 'No memo found for that link (id '.$id.').'];
        }

        if (! $this->isApprovedMemo($record)) {
            $status = strtolower(trim((string) ($record->overall_status ?? 'unknown')));

            return [
                'ok' => false,
                'error' => 'That memo is not approved yet (current status: '.$status.'). Only approved memos can be referenced.',
            ];
        }

        $showUrl = $this->showUrlFor($modelClass, $id, $record);
        if ($showUrl === null) {
            return ['ok' => false, 'error' => 'This memo type cannot be opened from a reference link.'];
        }

        return [
            'ok' => true,
            'row' => [
                'model' => $modelClass,
                'id' => $id,
                'url' => $showUrl,
                'document_number' => $this->documentNumberFor($record),
                'title' => $this->titleFor($record),
                'memo_kind' => $this->memoKindLabel($modelClass, $record),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $links
     * @return array<int, array{model: string, id: int, url: string, document_number: ?string, title: string, memo_kind: string}>
     */
    public function resolveMany(array $links, int $maxAllowed, ?int $excludeOtherMemoId = null): array
    {
        if ($maxAllowed < 1) {
            return [];
        }

        $trimmed = [];
        foreach ($links as $link) {
            $link = trim((string) $link);
            if ($link !== '') {
                $trimmed[] = $link;
            }
        }

        if (count($trimmed) > $maxAllowed) {
            throw ValidationException::withMessages([
                'referenced_memo_links' => ["You may reference at most {$maxAllowed} approved memo(s)."],
            ]);
        }

        if ($trimmed === []) {
            return [];
        }

        $resolved = [];
        $seen = [];

        foreach ($trimmed as $index => $url) {
            $diagnosis = $this->diagnoseUrl($url);
            if (! $diagnosis['ok']) {
                throw ValidationException::withMessages([
                    'referenced_memo_links' => [
                        'Link '.($index + 1).': '.($diagnosis['error'] ?? 'Invalid memo link.'),
                    ],
                ]);
            }

            $row = $diagnosis['row'];

            if ($excludeOtherMemoId !== null
                && $row['model'] === OtherMemo::class
                && (int) $row['id'] === $excludeOtherMemoId) {
                throw ValidationException::withMessages([
                    'referenced_memo_links' => ['You cannot reference the same other memo you are editing.'],
                ]);
            }

            $key = $row['model'].'#'.$row['id'];
            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'referenced_memo_links' => ['Each referenced memo must be unique.'],
                ]);
            }
            $seen[$key] = true;
            $resolved[] = $row;
        }

        return $resolved;
    }

    /**
     * @return array{0: class-string<Model>, 1: int}|null
     */
    private function parsePath(string $url): ?array
    {
        $path = $this->extractPath($url);
        if ($path === '') {
            return null;
        }

        if (preg_match('#^other-memos/(\d+)#i', $path, $m)) {
            return [OtherMemo::class, (int) $m[1]];
        }
        if (preg_match('#^single-memos/(\d+)#i', $path, $m)) {
            return [Activity::class, (int) $m[1]];
        }
        if (preg_match('#^special-memo/(\d+)#i', $path, $m)) {
            return [SpecialMemo::class, (int) $m[1]];
        }
        if (preg_match('#^non-travel/(\d+)#i', $path, $m)) {
            return [NonTravelMemo::class, (int) $m[1]];
        }
        if (preg_match('#^change-requests/(\d+)#i', $path, $m)) {
            return [ChangeRequest::class, (int) $m[1]];
        }
        if (preg_match('#^matrices/(\d+)/activities/(\d+)#i', $path, $m)) {
            return [Activity::class, (int) $m[2]];
        }

        return null;
    }

    private function extractPath(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url)) {
            $parts = parse_url($url);
            $path = (string) ($parts['path'] ?? '');
        } elseif (str_starts_with($url, '/')) {
            $path = parse_url($url, PHP_URL_PATH) ?: $url;
        } else {
            $path = $url;
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('/\?.*$/', '', $path) ?? $path;
        $path = preg_replace('/#.*$/', '', $path) ?? $path;
        $path = trim($path, '/');

        if (preg_match('#(?:^|/)(?:staff/)?apm/(.+)$#i', $path, $m)) {
            $path = trim($m[1], '/');
        }

        return strtolower($path) === 'apm' ? '' : trim($path, '/');
    }

    private function showUrlFor(string $modelClass, int $id, Model $record): ?string
    {
        $via = MemoShowUrl::forMemo($modelClass, $id);
        if ($via !== null) {
            return $via;
        }

        return match (class_basename($modelClass)) {
            'OtherMemo' => route('other-memos.show', $id),
            'SpecialMemo' => route('special-memo.show', $id),
            'NonTravelMemo' => route('non-travel.show', $id),
            'ChangeRequest' => route('change-requests.show', $id),
            'Activity' => $record instanceof Activity && $record->matrix_id
                ? route('matrices.activities.show', [$record->matrix_id, $id])
                : route('activities.single-memos.show', $id),
            default => null,
        };
    }

    private function isApprovedMemo(Model $record): bool
    {
        $status = strtolower(trim((string) ($record->overall_status ?? '')));

        if ($record instanceof Activity) {
            return $status === Activity::STATUS_APPROVED;
        }

        if ($record instanceof SpecialMemo) {
            return $status === SpecialMemo::STATUS_APPROVED;
        }

        if ($record instanceof OtherMemo) {
            return $status === OtherMemo::STATUS_APPROVED;
        }

        if ($record instanceof NonTravelMemo || $record instanceof ChangeRequest) {
            return $status === 'approved';
        }

        return false;
    }

    private function documentNumberFor(Model $record): ?string
    {
        $num = $record->document_number ?? null;

        return is_string($num) && trim($num) !== '' ? trim($num) : null;
    }

    private function titleFor(Model $record): string
    {
        if ($record instanceof OtherMemo) {
            $payload = is_array($record->payload) ? $record->payload : [];
            $title = trim((string) (data_get($payload, 'title') ?: ''));

            return $title !== '' ? $title : (string) ($record->memo_type_name_snapshot ?? 'Other memo');
        }

        if ($record instanceof Activity || $record instanceof SpecialMemo || $record instanceof NonTravelMemo || $record instanceof ChangeRequest) {
            $title = trim((string) ($record->activity_title ?? ''));

            return $title !== '' ? $title : class_basename($record).' #'.$record->getKey();
        }

        return class_basename($record).' #'.$record->getKey();
    }

    private function memoKindLabel(string $modelClass, Model $record): string
    {
        return match (class_basename($modelClass)) {
            'OtherMemo' => 'Other memo',
            'Activity' => ($record instanceof Activity && $record->is_single_memo)
                ? 'Single memo'
                : 'Matrix activity',
            'SpecialMemo' => 'Special memo',
            'NonTravelMemo' => 'Non-travel memo',
            'ChangeRequest' => 'Change request',
            default => Str::headline(class_basename($modelClass)),
        };
    }
}
