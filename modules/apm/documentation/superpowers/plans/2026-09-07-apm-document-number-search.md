# APM Document-Number Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a reusable Vue document-number search (year default = current year, live search at 3+ chars) on APM home and approver-dashboard, backed by a session-auth API.

**Architecture:** `DocumentNumberSearchService` queries QM/SM/SPM/NT/CR/SR/ARF by `document_number LIKE` + year with list-style visibility (permission 87 = all; else division + associated). Web JSON endpoints feed a shared Vuetify widget mounted via Blade partial / page config.

**Tech Stack:** Laravel 12 (PHP), Blade, Vue 3 + Vuetify 3 (CDN), Pest/PHPUnit

**Spec:** `apm/docs/superpowers/specs/2026-09-07-apm-document-number-search-design.md`

## Global Constraints

- Document number only (no title search)
- Year always required; default current calendar year; no “All years”
- Debounced realtime search; min 3 characters; no Search button
- Result click → document show URL
- Visibility: permission 87 all divisions; else primary + associated divisions
- Types v1: QM, SM, SPM, NT, CR, SR, ARF (exclude Other Memo)
- Cap results at 20
- Session-auth web routes under `/api/document-search`

## File map

| File | Responsibility |
|------|----------------|
| `apm/app/Services/DocumentNumberSearchService.php` | Search + years + show URLs + visibility |
| `apm/app/Http/Controllers/DocumentSearchController.php` | JSON endpoints |
| `apm/routes/web.php` | Register routes (session middleware group) |
| `apm/public/js/apm-document-search.js` | Reusable Vue widget |
| `apm/resources/views/partials/apm-document-search.blade.php` | Optional standalone mount |
| `apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php` | Load script |
| `apm/public/js/home-dashboard-app.js` | Embed search on home |
| `apm/app/Http/Controllers/HomeController.php` | Pass search routes in pageConfig |
| `apm/public/js/approver-dashboard-app.js` | Embed search on approver dashboard |
| `apm/app/Http/Controllers/ApproverDashboardController.php` | Pass search routes in pageConfig |
| `apm/tests/Unit/DocumentNumberSearchServiceTest.php` | Unit tests for show URL + visibility helpers |

---

### Task 1: DocumentNumberSearchService

**Files:**
- Create: `apm/app/Services/DocumentNumberSearchService.php`
- Test: `apm/tests/Unit/DocumentNumberSearchServiceTest.php`

**Interfaces:**
- Produces:
  - `search(string $q, int $year, ?int $staffId, ?int $divisionId, array $permissions, int $limit = 20): array`
  - `availableYears(): array` (list of int, desc)
  - `showUrl(string $documentType, int $id, ?int $matrixId = null): string`
  - `resolveDivisionIds(?int $staffId, ?int $divisionId, array $permissions): ?array` (`null` = all divisions)

- [ ] **Step 1: Implement service**

Create `DocumentNumberSearchService` that:

1. `VIEW_ALL_PERMISSION = 87`
2. `resolveDivisionIds`: if `in_array(87, $permissions)` return `null` (no division filter); else primary division + Staff `associated_divisions`
3. `search`: for each type in DOC_TYPES, query like `ApmMemoListController::getMemoListRowsForType` but **without** status filter; apply year; apply `document_number LIKE %escaped%`; apply division filter when `$divisionIds !== null`; select lightweight fields only (no approval trail enrichment)
4. Merge, sort by year desc then created_at desc, take `$limit`
5. Map each row with `type_label` from `DocumentCounter::getDocumentTypes()` and `show_url` via `showUrl`
6. `showUrl` match `ReportsController::memoListShowUrl`
7. `availableYears`: distinct `matrices.year` union `YEAR(created_at)` from special_memos, non_travel_memos, change_request, service_requests, request_arfs; ensure current year included; sort desc

```php
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
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $rows = collect();

        foreach (self::DOC_TYPES as $type) {
            $rows = $rows->concat($this->rowsForType($type, $year, $like, $divisionIds));
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
            (new SpecialMemo())->getTable(),
            (new NonTravelMemo())->getTable(),
            (new ChangeRequest())->getTable(),
            (new ServiceRequest())->getTable(),
            (new RequestARF())->getTable(),
        ] as $table) {
            try {
                $years = $years->merge(
                    DB::table($table)->whereNotNull('created_at')->selectRaw('YEAR(created_at) as y')->distinct()->pluck('y')
                );
            } catch (\Throwable $e) {
                // ignore missing tables in test env
            }
        }

        $list = $years->map(fn ($y) => (int) $y)->filter(fn ($y) => $y >= 2000 && $y <= 2100)->unique()->sortDesc()->values()->all();
        if (! in_array($current, $list, true)) {
            array_unshift($list, $current);
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
        if (in_array(self::VIEW_ALL_PERMISSION, $permissions, false)
            || in_array((string) self::VIEW_ALL_PERMISSION, $permissions, true)) {
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
    private function rowsForType(string $documentType, int $year, string $like, ?array $divisionIds): Collection
    {
        $rows = collect();

        if ($documentType === DocumentCounter::TYPE_QUARTERLY_MATRIX || $documentType === DocumentCounter::TYPE_SINGLE_MEMO) {
            $activitiesTable = (new Activity())->getTable();
            $matricesTable = (new Matrix())->getTable();
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
                ->where($matricesTable.'.year', $year)
                ->where($activitiesTable.'.document_number', 'like', $like);

            if ($divisionIds !== null) {
                if ($divisionIds === []) {
                    return $rows;
                }
                $q->where(function ($query) use ($activitiesTable, $matricesTable, $divisionIds) {
                    $query->whereIn($activitiesTable.'.division_id', $divisionIds)
                        ->orWhere(function ($q2) use ($activitiesTable, $matricesTable, $divisionIds) {
                            $q2->whereNull($activitiesTable.'.division_id')
                                ->whereIn($matricesTable.'.division_id', $divisionIds);
                        });
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
            ->whereYear('created_at', $year)
            ->where('document_number', 'like', $like);

        if ($divisionIds !== null) {
            if ($divisionIds === []) {
                return $rows;
            }
            $q->whereIn('division_id', $divisionIds);
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
```

- [ ] **Step 2: Unit tests for showUrl + resolveDivisionIds**

```php
<?php

use App\Models\DocumentCounter;
use App\Services\DocumentNumberSearchService;

it('maps show urls for known document types', function () {
    $svc = new DocumentNumberSearchService();

    expect($svc->showUrl(DocumentCounter::TYPE_SPECIAL_MEMO, 12))
        ->toContain('/special-memo/12')
        ->and($svc->showUrl(DocumentCounter::TYPE_SINGLE_MEMO, 5))
        ->toContain('single-memos')
        ->and($svc->showUrl(DocumentCounter::TYPE_QUARTERLY_MATRIX, 9, 3))
        ->toContain('/matrices/3/activities/9')
        ->and($svc->showUrl('UNKNOWN', 1))
        ->toBe('#');
});

it('treats permission 87 as unrestricted divisions', function () {
    $svc = new DocumentNumberSearchService();
    expect($svc->resolveDivisionIds(1, 10, [87]))->toBeNull();
});

it('returns empty division list when no division and no staff context', function () {
    $svc = new DocumentNumberSearchService();
    expect($svc->resolveDivisionIds(null, null, []))->toBe([]);
});

it('includes primary division id when present', function () {
    $svc = new DocumentNumberSearchService();
    expect($svc->resolveDivisionIds(null, 42, []))->toBe([42]);
});
```

- [ ] **Step 3: Run tests**

```bash
cd apm && ./vendor/bin/pest tests/Unit/DocumentNumberSearchServiceTest.php
```

Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add apm/app/Services/DocumentNumberSearchService.php apm/tests/Unit/DocumentNumberSearchServiceTest.php
git commit -m "Add DocumentNumberSearchService for cross-type number lookup."
```

---

### Task 2: Controller + routes

**Files:**
- Create: `apm/app/Http/Controllers/DocumentSearchController.php`
- Modify: `apm/routes/web.php` (near other `/api/...` session routes, inside `CheckSessionMiddleware` group)

**Interfaces:**
- Consumes: `DocumentNumberSearchService`
- Produces: routes `document-search.search`, `document-search.years`

- [ ] **Step 1: Controller**

```php
<?php

namespace App\Http\Controllers;

use App\Services\DocumentNumberSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSearchController extends Controller
{
    public function __construct(private DocumentNumberSearchService $search)
    {
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $results = $this->search->search(
            $validated['q'],
            (int) $validated['year'],
            (int) user_session('staff_id') ?: null,
            (int) user_session('division_id') ?: null,
            user_session('permissions', []) ?: [],
        );

        return response()->json(['success' => true, 'data' => $results]);
    }

    public function years(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'years' => $this->search->availableYears(),
                'default_year' => (int) date('Y'),
            ],
        ]);
    }
}
```

- [ ] **Step 2: Routes**

Inside the same auth/session area as approver-dashboard API routes:

```php
Route::get('/api/document-search', [App\Http\Controllers\DocumentSearchController::class, 'search'])->name('document-search.search');
Route::get('/api/document-search/years', [App\Http\Controllers\DocumentSearchController::class, 'years'])->name('document-search.years');
```

Confirm they sit behind `CheckSessionMiddleware` like other `/api/approver-dashboard*` routes.

- [ ] **Step 3: Commit**

```bash
git add apm/app/Http/Controllers/DocumentSearchController.php apm/routes/web.php
git commit -m "Expose session document-search JSON endpoints."
```

---

### Task 3: Reusable Vue widget

**Files:**
- Create: `apm/public/js/apm-document-search.js`
- Create: `apm/resources/views/partials/apm-document-search.blade.php`
- Modify: `apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php` (add script tag)

**Interfaces:**
- Produces: `window.ApmDocumentSearch.mount(el, cfg)` and `window.ApmDocumentSearch.createComponent(cfg)` (Vue options/setup factory usable inside parent apps)

- [ ] **Step 1: Implement `apm-document-search.js`**

Widget behavior:
- year select from `cfg.years` or fetch `cfg.yearsUrl`
- `defaultYear` = cfg.defaultYear || current year
- text field; debounce 300ms; search when `q.length >= 3`
- GET `cfg.searchUrl?q=&year=`
- results list; click sets `window.location.href = item.show_url`
- states: hint / loading / empty / error

Expose both standalone mount (for Blade partial) and a `componentDefinition(cfg)` object that parent Vue apps can embed via nested createApp OR simpler: export a render helper that returns HTML string template + setup factory used by home/approver by including search UI inline calling shared functions.

Preferred pattern for this codebase: **`window.ApmDocumentSearch`** with:

```js
window.ApmDocumentSearch = {
  setupSearchState(cfg, vueApis), // returns reactive state + methods for embedding
  mount(el, cfg), // standalone
};
```

`setupSearchState` returns: `{ year, years, q, results, loading, error, hint, onSelect, ... }` wired with debounce.

- [ ] **Step 2: Blade partial** (standalone optional mount)

```blade
@php
    $docSearchConfig = $docSearchConfig ?? [
        'searchUrl' => route('document-search.search'),
        'yearsUrl' => route('document-search.years'),
        'defaultYear' => (int) date('Y'),
        'placeholder' => 'Document number…',
    ];
@endphp
<div id="{{ $docSearchMountId ?? 'apm-document-search' }}" class="apm-document-search-mount mb-3">
    <script type="application/json" class="apm-document-search-config">@json($docSearchConfig)</script>
</div>
```

- [ ] **Step 3: Register script in runtime scripts**

```blade
<script src="{{ asset('js/apm-document-search.js') }}?v=1"></script>
```

- [ ] **Step 4: Commit**

```bash
git add apm/public/js/apm-document-search.js apm/resources/views/partials/apm-document-search.blade.php apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php
git commit -m "Add reusable APM document-number search Vue widget."
```

---

### Task 4: Wire into home + approver-dashboard

**Files:**
- Modify: `apm/app/Http/Controllers/HomeController.php` — add `documentSearch` routes to `pageConfig`
- Modify: `apm/public/js/home-dashboard-app.js` — search bar under welcome card header
- Modify: `apm/app/Http/Controllers/ApproverDashboardController.php` — add routes to `pageConfig.routes`
- Modify: `apm/public/js/approver-dashboard-app.js` — search bar under title card
- Bump `?v=` on home-dashboard-app and approver-dashboard-app in runtime scripts

- [ ] **Step 1: HomeController pageConfig**

```php
'documentSearch' => [
    'searchUrl' => route('document-search.search'),
    'yearsUrl' => route('document-search.years'),
    'defaultYear' => (int) date('Y'),
    'placeholder' => 'Document number…',
],
```

- [ ] **Step 2: Embed in home-dashboard-app.js**

In setup, call `window.ApmDocumentSearch.setupSearchState(cfg.documentSearch || {}, { ref, watch, onMounted, computed })` and add template block after welcome chips / before module grid:

Year select + search field + results `v-list`.

- [ ] **Step 3: Approver dashboard same pattern** near top of overview card

- [ ] **Step 4: Manual smoke** — load `/apm/home`, type 3+ chars of a known number for current year, click through

- [ ] **Step 5: Commit**

```bash
git add apm/app/Http/Controllers/HomeController.php apm/public/js/home-dashboard-app.js apm/app/Http/Controllers/ApproverDashboardController.php apm/public/js/approver-dashboard-app.js apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php
git commit -m "Mount document search on home and approver dashboard."
```

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| Reusable component | T3 |
| All primary types | T1 |
| Year default current | T3/T4 |
| Years from system | T1/T2 |
| Realtime 3+ debounce | T3 |
| Show page on click | T1 show_url + T3 |
| Visibility 87 / division | T1 |
| Session API | T2 |
| Home + approver | T4 |
| Exclude Other Memo | T1 DOC_TYPES |
| Cap 20 | T1 LIMIT |
| Tests | T1 |
