# Finance Funding Portfolio, SAP Import & APM Intramural Budget Execution

**Date:** 2026-09-17  
**Status:** Approved for implementation planning  
**Apps:** `modules/finance` (CBP SPA, risk-register clone) · `modules/apm`

## Summary

Build an enterprise Finance module for Africa CDC Funding Portfolio oversight, SAP budget import, and sync of fund-code balances into APM. APM gains a Dashboard page **Intramural SAP Budget Execution** that lists fund codes (approved, balance, execution rate) and drills into approved activities and approved service requests linked to each fund code.

## Goals

- Funding Portfolio dashboard (mirrors workbook **Fund Portfolio Summary**).
- Tabbed portfolio data-entry (Extramural, Indirect Cost, Administrative Cost, Investments & Bank, AfEF) preloaded with demo data from *Africa CDC Funding Portfolio.xlsx*.
- SAP `.xlsx` upload in Finance: import full sheet with `budget_year = current calendar year`; sync APM from each Fund Center’s **Total** row only.
- APM Dashboard: Intramural SAP Budget Execution (API-backed); click code → approved activities + approved service requests.
- Professional enterprise UI; **Highcharts** for charts with **`credits.enabled: false`** (same pattern as Risk Register `PortalHighchart`).

## Non-goals (v1)

- Creating APM fund codes from SAP (APM remains master; unmatched codes skipped silently).
- Unmatched fund-center reporting UI.
- Extramural (or other portfolio blocks) sourced from SAP.
- Replacing APM’s existing matrix-based Budget Execution page.

## Architecture (Approach 1 — split ownership)

| App | Owns |
|-----|------|
| **Finance** `/staff/finance` | Portfolio dashboard; tabbed data-entry + demo seed; SAP upload; local SAP tables; service client that PATCHes matching APM fund codes |
| **APM** `/staff/apm` | Fund-code master (`code`, `division_id`, balances); Dashboard → Intramural SAP Budget Execution; document drill-down |

### Data flow

1. User uploads SAP export in Finance.
2. Finance stores **all** rows locally, tagged with **budget year = current year**.
3. For each Fund Center, identify the **last / Total row** (`GL Account` = `Total`, case-insensitive).
4. Match `Fund center` → APM `fund_codes.code` (same year, prefer active). On match, PATCH balances. Unmatched: skip silently.
5. Portfolio **Intramural** KPIs aggregate from latest SAP Total rows; division linkage via APM `fund_codes.division_id` ([APM API](https://cbp.africacdc.org/staff/apm/docs/)).
6. Other portfolio blocks read/write Finance portfolio entry tables (demo-seeded).
7. APM Budget Execution UI reads fund codes via APM API; drill-down queries activities and service requests by `budget_id`.

### Auth

- Finance: CBP SSO (same pattern as risk-register clone).
- Finance → APM: server-side service JWT (credentials in Finance `.env`), not the browser user’s token.
- APM page: existing APM session; same audience as Dashboard / Budget Execution.

## SAP → APM field mapping

Match columns by **header name** (not Excel letter). Source rows: **GL Account = Total** only (one series end per Fund Center).

| SAP header | APM field(s) |
|------------|----------------|
| **Total Released Budget** | `approved_budget` **and** `uploaded_budget` |
| **Released Budget balance** | `budget_balance` |

Reference sample: `modules/finance/EXPORT.xlsx` (headers include Total Released Budget, Released Budget balance, Fund center, GL Account).

**Execution rate** (display): when `approved > 0`, `(approved − balance) / approved`; else `—`. May exceed 100% or be negative if overspent.

## Finance data model

1. **`fin_sap_imports`** — batch metadata: filename, uploader, `budget_year`, counts, sync status (`pending` / `synced` / `sync_failed`), timestamps. Keep history; mark latest for year.
2. **`fin_sap_rows`** — full imported sheet (all line items + totals), `budget_year`, `fund_center`, GL fields, amounts, `is_total_row`.
3. **`fin_sap_fund_centers`** — derived from Total rows per import: fund center, released budget, released balance, and other columns useful for Portfolio/Intramural.
4. **Portfolio entry tables** (one logical set per tab: Extramural, Indirect Cost, Administrative Cost, Investments & Bank, AfEF) — columns aligned to Funding Portfolio workbook sheets; seeded with demo data; CRUD from tab UI.

## Finance UI

Replace risk-register feature routes with finance navigation; retain portal shell, branding, SSO, and Highcharts infrastructure (`PortalHighchart` / `ensureHighcharts`, `credits: { enabled: false }`).

### 1. Funding Portfolio dashboard (home)

Enterprise layout inspired by **Fund Portfolio Summary**:

- KPI cards: Total Intramural / Extramural, execution %, fund balances, AfEF, investments summary.
- **Highcharts** (credits disabled): e.g. intramural execution by BU/division (column/bar), portfolio mix (pie), quarterly fund-list trend (area/column) where data exists.
- Professional density: clear hierarchy, muted chrome, consistent currency formatting (USD), period chip (as-of / budget year), export affordances where Risk Register already supports chart exporting.
- Intramural figures from latest SAP import; other blocks from portfolio entry tables.

### 2. Portfolio data-entry (tabs)

Single page, tabs:

- Extramural  
- Indirect Cost  
- Administrative Cost  
- Investments & Bank  
- AfEF  

Editable tables matching workbook columns; **demo data preloaded**; save updates dashboard.

### 3. SAP Import

- Accept `.xlsx` (SAP export shape).
- Validate required headers: Fund center, GL Account, Total Released Budget, Released Budget balance.
- Import all rows with `budget_year = current year`; derive Total summaries; PATCH APM for matches only.
- Success summary: rows imported, Total fund centers processed, APM codes updated (no unmatched list).

## APM: Intramural SAP Budget Execution

### Navigation

Dashboard dropdown → **Intramural SAP Budget Execution** (alongside existing Budget Execution).

### List view

API from `fund_codes` (default current year, active):

| Column | Source |
|--------|--------|
| Budget code | `code` |
| Approved budget | `approved_budget` |
| Budget balance | `budget_balance` |
| Execution rate | formula above |

Filters: division (`division_id`), search by code, year.

### Drill-down

On budget code click: documents where `budget_id` JSON contains this fund-code id and status is approved:

- **Activities** (approved)
- **Service requests** (`overall_status` / approved equivalent)

Show type, title/ref, amount if available, deep link to existing APM document UI.

### APM endpoints

- `GET .../sap-budget-execution` — list codes with approved, balance, execution %.
- `GET .../sap-budget-execution/{fundCodeId}/documents` — approved activities + approved service requests.

No SAP file upload in APM; balances arrive from Finance sync. Existing APM APIs remain: `GET/PATCH /fund-codes`, `GET /sap_budgets` ([docs](https://cbp.africacdc.org/staff/apm/docs/)).

## Errors & edge cases

- Reject invalid files / missing headers.
- Empty amounts → `0`; still PATCH matched codes.
- Unmatched Fund Centers → skip silently.
- APM sync failure after local save → `sync_failed`; user can retry; internal logging only.
- Partial PATCH → continue; report updated count only.

## Charts & enterprise UX standard

- Reuse / port **PortalHighchart** pattern from Risk Register: Highcharts + more/export modules as needed; **`credits: { enabled: false }`** globally via `setOptions` and per-chart options.
- Prefer chart + accessible data-table toggle where Risk Register does.
- Visual language: CBP portal shell, Africa CDC branding, consistent spacing/typography with Risk Register dashboards — finance-grade, not a prototype aesthetic.

## Testing

**Finance**

- Parser: Total-row detection; field mapping; `budget_year`; line items stored but not used for APM sync.
- Upload feature test with slim SAP fixture; APM client mocked; unmatched ignored.
- Portfolio seeder + dashboard API; tab CRUD persistence.

**APM**

- List execution % and filters.
- Drill-down only approved activities + approved SRs with matching `budget_id`.
- Menu route under Dashboard.

**Manual smoke**

1. Seed portfolio demo → Finance Portfolio dashboard (Highcharts render, no Highcharts credit).  
2. Upload sample SAP → APM fund codes updated.  
3. APM Intramural SAP Budget Execution → open code with linked approved activity/SR.

## Implementation order (for planning)

1. Finance: strip/replace risk routes; shell + Highcharts wiring.  
2. Finance: SAP schema, parser, upload API, APM sync client.  
3. Finance: portfolio tables, demo seed, tab UI, Portfolio dashboard + charts.  
4. APM: sap-budget-execution APIs + Dashboard page + drill-down.  
5. Tests + smoke against `EXPORT.xlsx` and local APM.
