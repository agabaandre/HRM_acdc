<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\DocumentCounter;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use App\Models\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentNumberSearchService
{
    public const VIEW_ALL_PERMISSION = 87;

    public const LIMIT = 20;

    /** @var list<string> */
    private const DOC_TYPES = [
        DocumentCounter::TYPE_QUARTERLY_MATRIX,
        DocumentCounter::TYPE_SINGLE_MEMO,
        DocumentCounter::TYPE_SPECIAL_MEMO,
        DocumentCounter::TYPE_NON_TRAVEL_MEMO,
        DocumentCounter::TYPE_CHANGE_REQUEST,
        DocumentCounter::TYPE_SERVICE_REQUEST,
        DocumentCounter::TYPE_ARF,
    ];

    /**
     * @param  list<int|string>  $permissions
     * @return list<array<string, mixed>>
     */
    public function search(
        string $q,
        int $year,
        ?int $staffId,
        ?int $divisionId,
        array $permissions,
        int $limit = self::LIMIT
    ): array {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return [];
        }

        $divisionIds = $this->resolveDivisionIds($staffId, $divisionId, $permissions);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = collect();

        foreach (self::DOC_TYPES as $type) {
            $rows = $rows->concat($this->rowsForType($type, $year, $like, $divisionIds, $staffId));
        }

        $labels = DocumentCounter::getDocumentTypes();

        return $rows
            ->sortByDesc(function (array $r) {
                $y = (int) ($r['year'] ?? 0);
                try {
                    $ts = isset($r['created_at']) && $r['created_at']
                        ? \Carbon\Carbon::parse($r['created_at'])->timestamp
                        : 0;
                } catch (\Throwable $e) {
                    $ts = 0;
                }

                return sprintf('%04d-%010d', $y, $ts);
            })
            ->values()
            ->take(max(1, min(100, $limit)))
            ->map(function (array $r) use ($labels) {
                $type = (string) ($r['document_type'] ?? '');

                return [
                    'id' => (int) ($r['id'] ?? 0),
                    'document_type' => $type,
                    'type_label' => $labels[$type] ?? $type,
                    'document_number' => $r['document_number'] ?? null,
                    'title' => $r['title'] ?? '—',
                    'overall_status' => $r['overall_status'] ?? null,
                    'year' => isset($r['year']) ? (int) $r['year'] : null,
                    'show_url' => $this->showUrl(
                        $type,
                        (int) ($r['id'] ?? 0),
                        isset($r['matrix_id']) ? (int) $r['matrix_id'] : null
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function availableYears(): array
    {
        $current = (int) date('Y');
        $years = collect();

        try {
            $years = $years->merge(
                Matrix::query()->whereNotNull('year')->distinct()->pluck('year')
            );
        } catch (\Throwable $e) {
            // ignore
        }

        foreach ([
            (new SpecialMemo)->getTable(),
            (new NonTravelMemo)->getTable(),
            (new ChangeRequest)->getTable(),
            (new ServiceRequest)->getTable(),
            (new RequestARF)->getTable(),
        ] as $table) {
            try {
                $years = $years->merge(
                    DB::table($table)->whereNotNull('created_at')->selectRaw('YEAR(created_at) as y')->distinct()->pluck('y')
                );
            } catch (\Throwable $e) {
                // ignore missing tables in test env
            }
        }

        $list = $years
            ->map(fn ($y) => (int) $y)
            ->filter(fn ($y) => $y >= 2000 && $y <= 2100)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        if (! in_array($current, $list, true)) {
            $list[] = $current;
            rsort($list);
        }

        return array_values($list);
    }

    public function showUrl(string $documentType, int $id, ?int $matrixId = null): string
    {
        return match ($documentType) {
            DocumentCounter::TYPE_QUARTERLY_MATRIX => $matrixId
                ? route('matrices.activities.show', [$matrixId, $id])
                : '#',
            DocumentCounter::TYPE_SINGLE_MEMO => route('activities.single-memos.show', $id),
            DocumentCounter::TYPE_SPECIAL_MEMO => route('special-memo.show', $id),
            DocumentCounter::TYPE_NON_TRAVEL_MEMO => route('non-travel.show', $id),
            DocumentCounter::TYPE_CHANGE_REQUEST => route('change-requests.show', $id),
            DocumentCounter::TYPE_SERVICE_REQUEST => route('service-requests.show', $id),
            DocumentCounter::TYPE_ARF => route('request-arf.show', $id),
            default => '#',
        };
    }

    /**
     * @param  list<int|string>  $permissions
     * @return list<int>|null  null = unrestricted
     */
    public function resolveDivisionIds(?int $staffId, ?int $divisionId, array $permissions): ?array
    {
        $normalized = array_map(static fn ($p) => (int) $p, $permissions);
        if (in_array(self::VIEW_ALL_PERMISSION, $normalized, true)) {
            return null;
        }

        $ids = [];
        if ($divisionId && $divisionId > 0) {
            $ids[] = $divisionId;
        }

        if ($staffId && $staffId > 0) {
            try {
                $staff = Staff::find($staffId);
                $raw = $staff?->associated_divisions ?? null;
                if (is_array($raw)) {
                    $ids = array_merge($ids, array_map('intval', array_filter($raw)));
                } elseif (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $ids = array_merge($ids, array_map('intval', array_filter($decoded)));
                    }
                }
            } catch (\Throwable $e) {
                // primary only
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  list<int>|null  $divisionIds
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsForType(string $documentType, int $year, string $like, ?array $divisionIds, ?int $staffId): Collection
    {
        $rows = collect();

        if ($documentType === DocumentCounter::TYPE_QUARTERLY_MATRIX || $documentType === DocumentCounter::TYPE_SINGLE_MEMO) {
            $activitiesTable = (new Activity)->getTable();
            $matricesTable = (new Matrix)->getTable();
            $q = Activity::query()
                ->select([
                    $activitiesTable.'.id',
                    $activitiesTable.'.matrix_id',
                    $activitiesTable.'.document_number',
                    $activitiesTable.'.activity_title',
                    $activitiesTable.'.division_id',
                    $activitiesTable.'.overall_status',
                    $activitiesTable.'.created_at',
                    $matricesTable.'.year as matrix_year',
                ])
                ->join($matricesTable, $activitiesTable.'.matrix_id', '=', $matricesTable.'.id')
                ->where(function ($query) use ($activitiesTable, $documentType) {
                    if ($documentType === DocumentCounter::TYPE_SINGLE_MEMO) {
                        $query->where($activitiesTable.'.is_single_memo', 1);
                    } else {
                        $query->where(function ($q2) use ($activitiesTable) {
                            $q2->where($activitiesTable.'.is_single_memo', 0)->orWhereNull($activitiesTable.'.is_single_memo');
                        });
                    }
                })
                // Year filter, but always keep drafts/archived (stale drafts + archived stale) findable.
                ->where(function ($query) use ($activitiesTable, $matricesTable, $year) {
                    $query->where($matricesTable.'.year', $year)
                        ->orWhereYear($activitiesTable.'.created_at', $year)
                        ->orWhereYear($activitiesTable.'.updated_at', $year)
                        ->orWhereIn($activitiesTable.'.overall_status', ['draft', 'archived']);
                })
                ->where(function ($query) use ($activitiesTable, $like) {
                    $query->where($activitiesTable.'.document_number', 'like', $like)
                        ->orWhere($activitiesTable.'.activity_title', 'like', $like);
                });

            if ($divisionIds !== null) {
                if ($divisionIds === [] && ! ($staffId && $staffId > 0)) {
                    return $rows;
                }
                $q->where(function ($query) use ($activitiesTable, $matricesTable, $divisionIds, $staffId) {
                    if ($divisionIds !== []) {
                        $query->whereIn($activitiesTable.'.division_id', $divisionIds)
                            ->orWhere(function ($q2) use ($activitiesTable, $matricesTable, $divisionIds) {
                                $q2->whereNull($activitiesTable.'.division_id')
                                    ->whereIn($matricesTable.'.division_id', $divisionIds);
                            });
                    }
                    if ($staffId && $staffId > 0) {
                        $query->orWhere($activitiesTable.'.staff_id', $staffId)
                            ->orWhere($activitiesTable.'.responsible_person_id', $staffId);
                    }
                });
            }

            foreach ($q->limit(self::LIMIT)->get() as $a) {
                $rows->push([
                    'document_type' => $documentType,
                    'id' => $a->id,
                    'matrix_id' => $a->matrix_id,
                    'document_number' => $a->document_number,
                    'title' => $a->activity_title,
                    'overall_status' => $a->overall_status,
                    'year' => $a->matrix_year,
                    'created_at' => $a->created_at?->toDateTimeString(),
                ]);
            }

            return $rows;
        }

        $model = match ($documentType) {
            DocumentCounter::TYPE_SPECIAL_MEMO => SpecialMemo::class,
            DocumentCounter::TYPE_NON_TRAVEL_MEMO => NonTravelMemo::class,
            DocumentCounter::TYPE_CHANGE_REQUEST => ChangeRequest::class,
            DocumentCounter::TYPE_SERVICE_REQUEST => ServiceRequest::class,
            DocumentCounter::TYPE_ARF => RequestARF::class,
            default => null,
        };
        if (! $model) {
            return $rows;
        }

        $q = $model::query()
            ->where(function ($query) use ($year) {
                $query->whereYear('created_at', $year)
                    ->orWhereYear('updated_at', $year)
                    ->orWhereIn('overall_status', ['draft', 'archived']);
            })
            ->where(function ($query) use ($documentType, $like) {
                $query->where('document_number', 'like', $like);
                if ($documentType === DocumentCounter::TYPE_SERVICE_REQUEST) {
                    $query->orWhere('title', 'like', $like)
                        ->orWhere('service_title', 'like', $like);
                } else {
                    $query->orWhere('activity_title', 'like', $like);
                }
            });

        if ($divisionIds !== null) {
            if ($divisionIds === [] && ! ($staffId && $staffId > 0)) {
                return $rows;
            }
            $q->where(function ($query) use ($divisionIds, $staffId, $documentType) {
                if ($divisionIds !== []) {
                    $query->whereIn('division_id', $divisionIds);
                }
                if ($staffId && $staffId > 0) {
                    $query->orWhere('staff_id', $staffId);
                    if ($documentType !== DocumentCounter::TYPE_NON_TRAVEL_MEMO) {
                        $query->orWhere('responsible_person_id', $staffId);
                    }
                }
            });
        }

        foreach ($q->orderByDesc('created_at')->limit(self::LIMIT)->get() as $m) {
            $title = $documentType === DocumentCounter::TYPE_SERVICE_REQUEST
                ? ($m->title ?? $m->service_title ?? '—')
                : ($m->activity_title ?? '—');

            $rows->push([
                'document_type' => $documentType,
                'id' => $m->id,
                'matrix_id' => null,
                'document_number' => $m->document_number ?? null,
                'title' => $title,
                'overall_status' => $m->overall_status ?? $m->status ?? null,
                'year' => $m->created_at ? (int) $m->created_at->format('Y') : $year,
                'created_at' => $m->created_at?->toDateTimeString(),
            ]);
        }

        return $rows;
    }
}
