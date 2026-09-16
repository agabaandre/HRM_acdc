# Finance Funding Portfolio + SAP→APM Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Finance Funding Portfolio (dashboard + tabbed entry + SAP import) and APM Intramural SAP Budget Execution (list + activity/SR drill-down), with Finance syncing Total-row balances into APM fund codes.

**Architecture:** Finance SPA owns portfolio data and SAP storage; server-side client PATCHes APM `fund_codes`. APM remains fund-code master and hosts the Intramural SAP Budget Execution dashboard (web session JSON, same pattern as existing Budget Execution). Division rollups use `fund_codes.division_id`.

**Tech Stack:** Laravel (Finance + APM), Vue 3 + Vuetify + PortalHighchart (Highcharts, `credits.enabled: false`), custom `XlsxSheetReader` (no PhpSpreadsheet), APM JWT API for sync, APM Blade + Vuetify page for intramural dashboard.

**Spec:** `modules/finance/docs/superpowers/specs/2026-09-17-finance-funding-portfolio-sap-design.md`

## Global Constraints

- SAP sync rows: only `GL Account` = `Total` (case-insensitive), last/total row per Fund Center.
- Map by header name: **Total Released Budget** → APM `approved_budget` + `uploaded_budget`; **Released Budget balance** → `budget_balance`.
- Import all SAP rows; `budget_year` = current calendar year.
- Unmatched APM fund centers: skip silently (no report UI).
- Do not create fund codes in APM from SAP.
- Charts: Highcharts via existing `PortalHighchart`; always `credits: { enabled: false }`.
- Table prefix for Finance finance tables: `fin_`.
- Keep Finance SSO / `AuthenticateRiskSession` middleware working (rename branding later if needed; do not break SSO in v1).
- APM intramural page: web routes + session JSON (not `api/apm/v1`), mirror `budget-execution`.
- Drill-down: approved **activities** + approved **service requests** whose `budget_id` array contains the fund code id (`overall_status = approved`; for activities also require matrix approved when that is how APM marks executed work — match `BudgetExecutionService::EXECUTED_STATUSES`).

## File structure (lock-in)

### Finance (create)

| Path | Responsibility |
|------|----------------|
| `backend/database/migrations/2026_09_17_100000_create_fin_sap_tables.php` | `fin_sap_imports`, `fin_sap_rows`, `fin_sap_fund_centers` |
| `backend/database/migrations/2026_09_17_100100_create_fin_portfolio_tables.php` | Portfolio entry tables per tab |
| `backend/app/Support/SapExportParser.php` | Parse SAP xlsx → rows + Total summaries |
| `backend/app/Services/SapImportService.php` | Persist import + derive fund centers |
| `backend/app/Services/ApmFundCodeSyncClient.php` | Login + list + PATCH fund codes |
| `backend/app/Http/Controllers/Api/V1/SapImportController.php` | Upload / status / retry-sync |
| `backend/app/Http/Controllers/Api/V1/PortfolioController.php` | Dashboard summary + tab CRUD |
| `backend/database/seeders/FinancePortfolioDemoSeeder.php` | Demo data for tabs |
| `backend/tests/Unit/SapExportParserTest.php` | Parser TDD |
| `backend/tests/Feature/SapImportApiTest.php` | Upload + mocked APM sync |
| `backend/tests/Feature/PortfolioApiTest.php` | Dashboard + tabs |
| `frontend/src/pages/finance/FundingPortfolioPage.vue` | Portfolio dashboard + Highcharts |
| `frontend/src/pages/finance/PortfolioEntryPage.vue` | Tabbed data entry |
| `frontend/src/pages/finance/SapImportPage.vue` | SAP upload UI |

### Finance (modify)

| Path | Change |
|------|--------|
| `backend/routes/api.php` | Add finance portfolio + SAP routes; keep health |
| `backend/.env.example` | `APM_API_EMAIL`, `APM_API_PASSWORD`, confirm `APM_BASE_URL` |
| `backend/config/services.php` (or new `config/apm.php`) | APM API client config |
| `frontend/src/lib/portalNav.ts` | Finance nav items only |
| `frontend/src/router/index.ts` | Finance routes; redirect `/` → portfolio |
| `frontend/src/components/molecules/PortalHighchart.vue` | Already has credits off — verify, do not regress |

### APM (create)

| Path | Responsibility |
|------|----------------|
| `app/Http/Controllers/IntramuralSapBudgetExecutionController.php` | index + data + documents |
| `app/Services/IntramuralSapBudgetExecutionService.php` | List + execution % + document query |
| `resources/views/intramural-sap-budget-execution/index.blade.php` | Mount point |
| `public/js/intramural-sap-budget-execution-app.js` | Vuetify list + drawer |

### APM (modify)

| Path | Change |
|------|--------|
| `routes/web.php` | Register intramural routes |
| `resources/views/layouts/partials/nav.blade.php` | Dashboard dropdown item + active state |
| `resources/views/partials/apm-vuetify-runtime-scripts.blade.php` | Register JS |

---

### Task 1: SAP export parser (TDD)

**Files:**
- Create: `modules/finance/backend/app/Support/SapExportParser.php`
- Create: `modules/finance/backend/tests/Unit/SapExportParserTest.php`
- Create: `modules/finance/backend/tests/fixtures/sap_export_sample.xlsx` (tiny fixture built in test or checked in)
- Modify: `modules/finance/backend/app/Support/XlsxSheetReader.php` — add `readFirstSheetRows(string $path): array` that reads the first workbook sheet (SAP sample uses `Sheet1`)

**Interfaces:**
- Consumes: `XlsxSheetReader::readFirstSheetRows`
- Produces: `SapExportParser::parse(string $path): array{rows: list<array<string,string|float|null>>, totals: list<array{fund_center: string, total_released_budget: float, released_budget_balance: float, raw: array}}`

- [ ] **Step 1: Extend XlsxSheetReader with first-sheet reader**

Add method that resolves the first sheet in `workbook.xml` (or matches sheet name `Sheet1` / empty filter). Keep existing `readSheetRows` intact for risk import.

```php
/** @return list<array<int, string>> */
public function readFirstSheetRows(string $path): array
{
    return $this->readSheetRows($path, ''); // empty => first sheet; implement resolveSheetPath accordingly
}
```

Update `resolveSheetPath` so `$sheetNameContains === ''` returns the first worksheet path.

- [ ] **Step 2: Write failing parser unit test**

```php
<?php
namespace Tests\Unit;

use App\Support\SapExportParser;
use Tests\TestCase;
use ZipArchive;

class SapExportParserTest extends TestCase
{
    public function test_total_rows_map_released_budget_and_balance(): void
    {
        $path = $this->makeSapFixture([
            ['Fund center', 'Fund center Text', 'GL Account', 'Total Released Budget', 'Released Budget balance'],
            ['CDC0300001SP', 'Example', '', '6000000', '0'],
            ['CDC0300001SP', '', '0000512012', '100', '50'],
            ['CDC0300001SP', '', 'Total', '6000000', '35582.69'],
            ['CDC0300002SP', 'Other', '', '4500000', '0'],
            ['CDC0300002SP', '', 'Total', '4500000', '1990668.19'],
        ]);

        $result = (new SapExportParser)->parse($path);

        $this->assertCount(6, $result['rows']);
        $this->assertCount(2, $result['totals']);
        $this->assertSame('CDC0300001SP', $result['totals'][0]['fund_center']);
        $this->assertSame(6000000.0, $result['totals'][0]['total_released_budget']);
        $this->assertSame(35582.69, $result['totals'][0]['released_budget_balance']);
    }

    /** @param list<list<string>> $matrix */
    private function makeSapFixture(array $matrix): string
    {
        return $this->makeMinimalXlsx($matrix, 'Sheet1');
    }
}
```

Extract `makeFixtureXlsx` / `colLetters` from `modules/finance/backend/tests/Feature/ExcelRiskImportTest.php` into `modules/finance/backend/tests/Support/BuildsMinimalXlsx.php` as a trait with signature `makeMinimalXlsx(array $matrix, string $sheetName = 'Sheet1'): string` (workbook sheet name must be `Sheet1` for SAP). Use the trait in both `ExcelRiskImportTest` and `SapExportParserTest`.

- [ ] **Step 3: Run test — expect FAIL**

Run: `cd modules/finance/backend && php artisan test --filter=SapExportParserTest`

Expected: FAIL (class not found or method missing).

- [ ] **Step 4: Implement SapExportParser**

```php
<?php
namespace App\Support;

final class SapExportParser
{
    public function __construct(private readonly XlsxSheetReader $reader = new XlsxSheetReader) {}

    /** @return array{rows: list<array<string, mixed>>, totals: list<array<string, mixed>>} */
    public function parse(string $path): array
    {
        $raw = $this->reader->readFirstSheetRows($path);
        if ($raw === []) {
            throw new \RuntimeException('SAP export is empty.');
        }
        $headerRow = array_shift($raw);
        $headers = [];
        foreach ($headerRow as $i => $label) {
            $headers[$i] = $this->normalizeHeader((string) $label);
        }
        $required = ['fund center', 'gl account', 'total released budget', 'released budget balance'];
        foreach ($required as $h) {
            if (! in_array($h, $headers, true)) {
                throw new \RuntimeException('Missing required header: '.$h);
            }
        }
        $rows = [];
        $totals = [];
        foreach ($raw as $cells) {
            $assoc = [];
            foreach ($headers as $i => $key) {
                $assoc[$key] = $cells[$i] ?? null;
            }
            $rows[] = $assoc;
            $gl = strtolower(trim((string) ($assoc['gl account'] ?? '')));
            $fc = trim((string) ($assoc['fund center'] ?? ''));
            if ($fc === '' || $gl !== 'total') {
                continue;
            }
            $totals[] = [
                'fund_center' => $fc,
                'total_released_budget' => $this->decimal($assoc['total released budget'] ?? 0),
                'released_budget_balance' => $this->decimal($assoc['released budget balance'] ?? 0),
                'raw' => $assoc,
            ];
        }
        return ['rows' => $rows, 'totals' => $totals];
    }

    private function normalizeHeader(string $h): string
    {
        $h = str_replace("\xEF\xBB\xBF", '', $h);
        return strtolower(trim(preg_replace('/\s+/', ' ', $h) ?? $h));
    }

    private function decimal(mixed $v): float
    {
        $clean = str_replace([',', ' '], '', (string) $v);
        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
```

- [ ] **Step 5: Run test — expect PASS**

Run: `cd modules/finance/backend && php artisan test --filter=SapExportParserTest`

- [ ] **Step 6: Commit**

```bash
git add modules/finance/backend/app/Support/XlsxSheetReader.php \
  modules/finance/backend/app/Support/SapExportParser.php \
  modules/finance/backend/tests/Unit/SapExportParserTest.php
git commit -m "feat(finance): parse SAP Total rows for released budget fields"
```

---

### Task 2: Finance SAP tables + import service + APM sync client

**Files:**
- Create: `modules/finance/backend/database/migrations/2026_09_17_100000_create_fin_sap_tables.php`
- Create: `modules/finance/backend/app/Services/SapImportService.php`
- Create: `modules/finance/backend/app/Services/ApmFundCodeSyncClient.php`
- Create: `modules/finance/backend/config/apm.php`
- Modify: `modules/finance/backend/.env.example` — add `APM_API_EMAIL=`, `APM_API_PASSWORD=`
- Create: `modules/finance/backend/tests/Feature/SapImportApiTest.php` (skeleton + DB setup; controller in Task 3)

**Interfaces:**
- Consumes: `SapExportParser::parse`
- Produces:
  - `SapImportService::import(string $path, int $userId): array{import_id: int, rows: int, totals: int, apm_updated: int, sync_status: string}`
  - `ApmFundCodeSyncClient::syncTotals(int $year, array $totals): int` // returns updated count; silently skips unmatched

- [ ] **Step 1: Migration**

```php
Schema::create('fin_sap_imports', function (Blueprint $t) {
    $t->id();
    $t->unsignedBigInteger('uploaded_by')->nullable();
    $t->string('original_filename');
    $t->unsignedSmallInteger('budget_year');
    $t->unsignedInteger('row_count')->default(0);
    $t->unsignedInteger('total_row_count')->default(0);
    $t->unsignedInteger('apm_updated_count')->default(0);
    $t->string('sync_status', 32)->default('pending'); // pending|synced|sync_failed
    $t->text('sync_error')->nullable();
    $t->boolean('is_latest')->default(false);
    $t->timestamps();
});
Schema::create('fin_sap_rows', function (Blueprint $t) {
    $t->id();
    $t->foreignId('import_id')->constrained('fin_sap_imports')->cascadeOnDelete();
    $t->unsignedSmallInteger('budget_year');
    $t->string('fund_center', 64)->nullable()->index();
    $t->string('gl_account', 64)->nullable();
    $t->boolean('is_total_row')->default(false);
    $t->json('payload'); // full assoc row
    $t->timestamps();
});
Schema::create('fin_sap_fund_centers', function (Blueprint $t) {
    $t->id();
    $t->foreignId('import_id')->constrained('fin_sap_imports')->cascadeOnDelete();
    $t->unsignedSmallInteger('budget_year')->index();
    $t->string('fund_center', 64)->index();
    $t->decimal('total_released_budget', 18, 2)->default(0);
    $t->decimal('released_budget_balance', 18, 2)->default(0);
    $t->json('payload')->nullable();
    $t->timestamps();
    $t->unique(['import_id', 'fund_center']);
});
```

- [ ] **Step 2: config/apm.php + env**

```php
return [
    'base_url' => rtrim(env('APM_BASE_URL', ''), '/'),
    'api_prefix' => env('APM_API_PREFIX', '/api/apm/v1'),
    'email' => env('APM_API_EMAIL'),
    'password' => env('APM_API_PASSWORD'),
    'timeout' => (int) env('APM_API_TIMEOUT', 60),
];
```

- [ ] **Step 3: ApmFundCodeSyncClient**

```php
public function syncTotals(int $year, array $totals): int
{
    $token = $this->login();
    $byCode = $this->fetchFundCodesByCode($token, $year); // paginate GET /fund-codes?year=&is_active=1
    $updated = 0;
    foreach ($totals as $row) {
        $code = $row['fund_center'];
        if (! isset($byCode[$code])) {
            continue; // silent
        }
        $id = $byCode[$code]['id'];
        $approved = number_format((float) $row['total_released_budget'], 2, '.', '');
        $balance = number_format((float) $row['released_budget_balance'], 2, '.', '');
        $this->patch($token, $id, [
            'approved_budget' => $approved,
            'uploaded_budget' => $approved,
            'budget_balance' => $balance,
        ]);
        $updated++;
    }
    return $updated;
}
```

Login: `POST {base}{prefix}/auth/login` with email/password → `data.access_token`.  
PATCH: `PATCH {base}{prefix}/fund-codes/{id}`.

- [ ] **Step 4: SapImportService**

Mark previous `is_latest` false for same `budget_year`; insert import + rows + fund_centers; call sync; set `sync_status`.

- [ ] **Step 5: Commit**

```bash
git add modules/finance/backend/database/migrations/2026_09_17_100000_create_fin_sap_tables.php \
  modules/finance/backend/app/Services/SapImportService.php \
  modules/finance/backend/app/Services/ApmFundCodeSyncClient.php \
  modules/finance/backend/config/apm.php \
  modules/finance/backend/.env.example
git commit -m "feat(finance): SAP import persistence and APM fund-code sync client"
```

---

### Task 3: SAP import API + feature test

**Files:**
- Create: `modules/finance/backend/app/Http/Controllers/Api/V1/SapImportController.php`
- Modify: `modules/finance/backend/routes/api.php`
- Create: `modules/finance/backend/tests/Feature/SapImportApiTest.php`

**Interfaces:**
- Produces: `POST /api/v1/sap/import` (multipart `file`), `GET /api/v1/sap/imports/latest`, `POST /api/v1/sap/imports/{id}/retry-sync`

- [ ] **Step 1: Write failing feature test** (Http::fake APM login + fund-codes list + patch)

```php
public function test_sap_upload_updates_matched_apm_codes_only(): void
{
    Http::fake([
        '*/api/apm/v1/auth/login' => Http::response(['success' => true, 'data' => ['access_token' => 't', 'token_type' => 'bearer', 'expires_in' => 3600]], 200),
        '*/api/apm/v1/fund-codes*' => Http::response([
            'success' => true,
            'data' => [['id' => 9, 'code' => 'CDC0300001SP', 'year' => (int) date('Y'), 'is_active' => true]],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 100, 'total' => 1],
        ], 200),
        '*/api/apm/v1/fund-codes/9' => Http::response(['success' => true, 'data' => []], 200),
    ]);
    // actingAs finance session user → POST /api/v1/sap/import with fixture
    // assert 200, apm_updated_count === 1, fin_sap_rows count > 0
}
```

Use the same auth helper other Finance feature tests use for `AuthenticateRiskSession`.

- [ ] **Step 2: Run — expect FAIL** (route missing)

- [ ] **Step 3: Implement controller + routes** under authenticated `api/v1` group

Validate: `file` required, `mimes:xlsx`, max 20MB.

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(finance): SAP import API with mocked APM sync"
```

---

### Task 4: Portfolio tables, seeder, API

**Files:**
- Create: `modules/finance/backend/database/migrations/2026_09_17_100100_create_fin_portfolio_tables.php`
- Create: `modules/finance/backend/app/Http/Controllers/Api/V1/PortfolioController.php`
- Create: `modules/finance/backend/database/seeders/FinancePortfolioDemoSeeder.php`
- Create: `modules/finance/backend/tests/Feature/PortfolioApiTest.php`
- Modify: `modules/finance/backend/routes/api.php`

**Schema (pragmatic v1):** one table `fin_portfolio_entries`:

```php
Schema::create('fin_portfolio_entries', function (Blueprint $t) {
    $t->id();
    $t->string('section', 64); // extramural|indirect_cost|administrative_cost|investments|afef
    $t->unsignedSmallInteger('budget_year');
    $t->unsignedInteger('sort_order')->default(0);
    $t->json('data'); // sheet-shaped row
    $t->timestamps();
    $t->index(['section', 'budget_year']);
});
```

**API:**
- `GET /api/v1/portfolio/summary` — intramural from latest `fin_sap_fund_centers` + section aggregates from entries
- `GET /api/v1/portfolio/sections/{section}` — list rows
- `PUT /api/v1/portfolio/sections/{section}` — replace all rows for current year (`data: [...]`)

**Seeder:** hard-code a few demo rows per section from Funding Portfolio workbook (Indirect Cost / Admin / AfEF / Investments samples — not full bank-balance history).

- [ ] **Step 1: Failing PortfolioApiTest** for summary shape + section PUT round-trip  
- [ ] **Step 2: Implement migration, seeder, controller, routes**  
- [ ] **Step 3: Pass tests + `php artisan db:seed --class=FinancePortfolioDemoSeeder`**  
- [ ] **Step 4: Commit**

```bash
git commit -m "feat(finance): portfolio entry tables, demo seed, and summary API"
```

---

### Task 5: Finance SPA nav + routes (replace risk primary UX)

**Files:**
- Modify: `modules/finance/frontend/src/lib/portalNav.ts`
- Modify: `modules/finance/frontend/src/router/index.ts`
- Create stub pages under `modules/finance/frontend/src/pages/finance/` (filled in Tasks 6–7)

**Nav items:**
- Funding Portfolio → `/dashboard` (or `/portfolio`)
- Portfolio entry → `/portfolio/entry`
- SAP Import → `/sap-import`

Remove Risks / Approvals / Workflows / Reference / Risk Settings from primary nav (leave routes temporarily unused or redirect).

```ts
export const PORTAL_NAV_ITEMS: PortalNavItem[] = [
  { label: 'Funding Portfolio', i18nKey: 'finance.portfolio', to: '/dashboard', match: ['/dashboard', '/portfolio'], group: 'primary', icon: 'fa-solid fa-chart-pie', module: 'dashboard' },
  { label: 'Data entry', i18nKey: 'finance.entry', to: '/portfolio/entry', match: ['/portfolio/entry'], group: 'primary', icon: 'fa-solid fa-table', module: 'portfolio' },
  { label: 'SAP import', i18nKey: 'finance.sap', to: '/sap-import', match: ['/sap-import'], group: 'primary', icon: 'fa-solid fa-file-import', module: 'sap' },
]
```

- [ ] **Step 1: Update nav + router; stub three Vue pages**  
- [ ] **Step 2: `cd modules/finance/frontend && npm run build`** — expect success  
- [ ] **Step 3: Commit**

```bash
git commit -m "feat(finance): replace risk nav with portfolio and SAP routes"
```

---

### Task 6: Funding Portfolio dashboard UI (Highcharts)

**Files:**
- Create: `modules/finance/frontend/src/pages/finance/FundingPortfolioPage.vue`
- Wire router `dashboard` → this page
- Reuse: `components/molecules/PortalHighchart.vue` (credits already disabled)

**UI requirements:**
- Enterprise KPI strip: Intramural approved/released/exec %, Extramural placeholders from entries, IC/Admin/AfEF/Investments cards.
- Charts (credits off via PortalHighchart):
  1. Column: intramural execution by fund center (top N from summary API)
  2. Pie: portfolio section mix (balances from entries + intramural)
- Currency formatting USD; period chip with budget year; loading/error states like RiskDashboardPage.

- [ ] **Step 1: Implement page calling `GET /api/v1/portfolio/summary`**  
- [ ] **Step 2: Visual check locally; confirm no Highcharts.com credit link**  
- [ ] **Step 3: Commit**

```bash
git commit -m "feat(finance): Funding Portfolio dashboard with Highcharts"
```

---

### Task 7: Portfolio entry tabs + SAP import page

**Files:**
- Create: `modules/finance/frontend/src/pages/finance/PortfolioEntryPage.vue`
- Create: `modules/finance/frontend/src/pages/finance/SapImportPage.vue`

**PortfolioEntryPage:** `v-tabs` for five sections; `v-data-table` editable or simple form rows; Load section on tab change; Save → `PUT /api/v1/portfolio/sections/{section}`.

**SapImportPage:** file input, upload progress, result card (`rows`, `totals`, `apm_updated`, `sync_status`). Optional retry button if `sync_failed`.

- [ ] **Step 1: Implement both pages**  
- [ ] **Step 2: Manual smoke with `EXPORT.xlsx` against local APM (with `APM_API_*` set)**  
- [ ] **Step 3: Commit**

```bash
git commit -m "feat(finance): portfolio data-entry tabs and SAP import UI"
```

---

### Task 8: APM Intramural SAP Budget Execution service + web JSON

**Files:**
- Create: `modules/apm/app/Services/IntramuralSapBudgetExecutionService.php`
- Create: `modules/apm/app/Http/Controllers/IntramuralSapBudgetExecutionController.php`
- Modify: `modules/apm/routes/web.php`
- Create: `modules/apm/tests/Feature/IntramuralSapBudgetExecutionTest.php` (if APM has Feature tests; otherwise Unit service test)

**Interfaces:**
- `list(int $year, ?int $divisionId, ?string $search): array`
- `documents(int $fundCodeId): array{activities: list, service_requests: list}`

Execution rate:

```php
$approved = (float) str_replace(',', '', (string) ($fc->approved_budget ?? 0));
$balance = (float) str_replace(',', '', (string) ($fc->budget_balance ?? 0));
$rate = $approved > 0 ? (($approved - $balance) / $approved) : null;
```

Documents query (MySQL JSON):

```php
Activity::query()
  ->where('overall_status', 'approved')
  ->whereJsonContains('budget_id', $fundCodeId)
  ->...
ServiceRequest::query()
  ->where('overall_status', 'approved')
  ->whereJsonContains('budget_id', $fundCodeId)
```

For activities, also constrain parent matrix `overall_status = approved` when that matches existing BudgetExecutionService rules.

Routes:

```php
Route::get('intramural-sap-budget-execution', [..., 'index'])->name('intramural-sap-budget-execution.index');
Route::get('intramural-sap-budget-execution/data', [..., 'data'])->name('intramural-sap-budget-execution.data');
Route::get('intramural-sap-budget-execution/{fundCode}/documents', [..., 'documents'])->name('intramural-sap-budget-execution.documents');
```

- [ ] **Step 1: Write service test for rate formula + document filtering**  
- [ ] **Step 2: Implement service + controller**  
- [ ] **Step 3: Pass tests**  
- [ ] **Step 4: Commit**

```bash
git commit -m "feat(apm): intramural SAP budget execution data API"
```

---

### Task 9: APM Dashboard UI + nav link

**Files:**
- Create: `modules/apm/resources/views/intramural-sap-budget-execution/index.blade.php`
- Create: `modules/apm/public/js/intramural-sap-budget-execution-app.js`
- Modify: `modules/apm/resources/views/layouts/partials/nav.blade.php` (Dashboard dropdown + `Request::is('intramural-sap-budget-execution*')` active)
- Modify: `modules/apm/resources/views/partials/apm-vuetify-runtime-scripts.blade.php`

**UI:** Professional table — Budget code (link), Approved, Balance, Execution %. Filters: year, division, search. Click code → right drawer/dialog listing approved activities and approved service requests with links to existing show routes.

Mirror `budget-execution-app.js` mount pattern: `window.ApmVuetifyPage.bind(...)`.

- [ ] **Step 1: Blade + JS + nav**  
- [ ] **Step 2: Manual check at `http://localhost/staff/apm/intramural-sap-budget-execution`**  
- [ ] **Step 3: Commit**

```bash
git commit -m "feat(apm): Intramural SAP Budget Execution dashboard under Dashboard menu"
```

---

### Task 10: End-to-end smoke + README touch-up

**Files:**
- Modify: `modules/finance/README.md` — describe Finance URLs, SAP import, APM sync env vars (replace risk-register copy)
- Optional: copy slim fixture under `modules/finance/backend/tests/fixtures/`

- [ ] **Step 1: Run Finance tests**

```bash
cd modules/finance/backend && php artisan test --filter='SapExportParserTest|SapImportApiTest|PortfolioApiTest'
```

- [ ] **Step 2: Run APM intramural tests**

```bash
cd modules/apm && php artisan test --filter=IntramuralSapBudgetExecution
```

- [ ] **Step 3: Manual E2E**
  1. Seed portfolio demo  
  2. Open Finance Funding Portfolio (charts, no Highcharts credit)  
  3. Upload `modules/finance/EXPORT.xlsx`  
  4. Confirm APM fund code approved/balance updated for a matched code  
  5. Open APM Intramural SAP Budget Execution → click code → see approved activity/SR if linked  

- [ ] **Step 4: Commit README**

```bash
git commit -m "docs(finance): operator notes for portfolio, SAP import, and APM sync"
```

---

## Spec coverage checklist

| Spec item | Task |
|-----------|------|
| Finance Portfolio dashboard | 4, 6 |
| Tabbed entry + demo seed | 4, 7 |
| SAP import all rows + current year | 1–3, 7 |
| Total-row mapping released→approved/uploaded, balance→budget_balance | 1–2 |
| Silent unmatched skip | 2 |
| APM Intramural list + execution % | 8–9 |
| Drill-down approved activities + SRs | 8–9 |
| Highcharts credits disabled | 6 (PortalHighchart) |
| Enterprise UX | 6, 7, 9 |
| Division via fund_codes.division_id | 8 filters + 4 intramural rollup when wiring summary |

## Execution notes

- Tasks **1–7** = Finance track; **8–9** = APM track (can start after Task 2 defines sync field contract).
- Do not commit `EXPORT.xlsx` unless explicitly requested (large binary); tests use tiny fixtures.
- Configure `APM_BASE_URL`, `APM_API_EMAIL`, `APM_API_PASSWORD` in Finance `.env` before live sync smoke.
