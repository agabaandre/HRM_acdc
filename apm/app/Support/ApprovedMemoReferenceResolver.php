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
        $parsed = $this->parsePath($url);
        if ($parsed === null) {
            return null;
        }

        [$modelClass, $id] = $parsed;
        $record = $modelClass::query()->find($id);
        if (! $record instanceof Model) {
            return null;
        }

        if (! $this->isApprovedMemo($record)) {
            return null;
        }

        $showUrl = MemoShowUrl::forMemo($modelClass, $id);
        if ($showUrl === null) {
            return null;
        }

        return [
            'model' => $modelClass,
            'id' => $id,
            'url' => $showUrl,
            'document_number' => $this->documentNumberFor($record),
            'title' => $this->titleFor($record),
            'memo_kind' => $this->memoKindLabel($modelClass, $record),
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
            $row = $this->resolveFromUrl($url);
            if ($row === null) {
                throw ValidationException::withMessages([
                    'referenced_memo_links' => [
                        'Link '.($index + 1).': paste a valid URL to an approved memo in this system (other, single, matrix activity, special, non-travel, or change request).',
                    ],
                ]);
            }

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
        $path = $url;
        if (preg_match('#^https?://#i', $url)) {
            $parts = parse_url($url);
            $path = (string) ($parts['path'] ?? '');
        } elseif (str_contains($url, '/')) {
            $path = parse_url($url, PHP_URL_PATH) ?: $url;
        }

        $path = '/'.trim(str_replace('\\', '/', $path), '/');
        if (preg_match('#/(?:staff/)?apm/(.+)$#i', $path, $m)) {
            $path = '/'.$m[1];
        }

        if (preg_match('#^/other-memos/(\d+)(?:/|$)#i', $path, $m)) {
            return [OtherMemo::class, (int) $m[1]];
        }
        if (preg_match('#^/single-memos/(\d+)(?:/|$)#i', $path, $m)) {
            return [Activity::class, (int) $m[1]];
        }
        if (preg_match('#^/special-memo/(\d+)(?:/|$)#i', $path, $m)) {
            return [SpecialMemo::class, (int) $m[1]];
        }
        if (preg_match('#^/non-travel/(\d+)(?:/|$)#i', $path, $m)) {
            return [NonTravelMemo::class, (int) $m[1]];
        }
        if (preg_match('#^/change-requests/(\d+)(?:/|$)#i', $path, $m)) {
            return [ChangeRequest::class, (int) $m[1]];
        }
        if (preg_match('#^/matrices/(\d+)/activities/(\d+)(?:/|$)#i', $path, $m)) {
            return [Activity::class, (int) $m[2]];
        }

        return null;
    }

    private function isApprovedMemo(Model $record): bool
    {
        $status = strtolower(trim((string) ($record->overall_status ?? '')));

        if ($record instanceof Activity) {
            if ($record->is_single_memo) {
                return $status === Activity::STATUS_APPROVED;
            }

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
