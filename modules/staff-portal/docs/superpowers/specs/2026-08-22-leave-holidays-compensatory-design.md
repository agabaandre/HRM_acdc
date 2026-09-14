# Leave holidays and holiday compensatory — design

**Date:** 2026-08-22  
**Status:** Approved  
**Scope:** Staff Portal Leave module (`staff-portal/`), settings at `/settings/leave`, balances at `/leave?view=balances`

## Problem

Leave settings only cover policy and types. There is no holiday calendar, weekday public holidays still count as leave days, and compensatory leave is display-only. Africa CDC needs:

1. Duty-station public holidays plus the staff member’s nationality independence day.
2. Holiday compensatory when a public holiday (including independence day) falls on a weekend, capped at 15 days per year and forfeited on 31 December of the year earned.
3. Other compensatory (travel/overtime) unchanged: 3-month expiry.
4. Shared/repeating holidays stored once globally; country-specific holidays once per `nationalities.iso2`.

## Goals

- HR can manage holiday **rules** (recurring month/day or one-off dates) under Settings → Leave → Holidays.
- Staff calendars = duty-station country holidays ∪ global holidays ∪ nationality independence day ∪ station-only exceptions.
- Nightly job grants holiday compensatory credits when an observed holiday falls on Saturday or Sunday (idempotent, cap 15).
- Leave requests skip weekday holidays by default (configurable A/B/C).
- Balances show **Holiday compensatory** and **Compensatory** separately; applying those leave types deducts the matching credit bucket.
- OpenHolidays API import where the country is supported; Ethiopia HQ 2026 list seeded as ET + global rules.

## Non-goals

- Rebuilding CI3 leave.
- Computing Islamic movable dates automatically (HR edits Eid/Mawlid each year).
- Flattening a full year of dated rows as the source of truth.
- Changing host crontab layout (use Laravel scheduler on the existing tick).

## Architecture

**Holiday rules + credit ledger**, not compute-only and not a fully exploded annual calendar table.

```
leave_holiday_rules  ──►  HolidayCalendarService  ──►  working-day count
        │                         │
        │                         ▼
        │               weekend + grant flag
        │                         │
        ▼                         ▼
nationalities.independence   staff_leave_compensatory_credits
duty_stations.country_iso2              │
                                        ▼
                              LeaveBalanceService
                              HOLIDAY_COMPENSATORY / COMPENSATORY types
```

### Staff calendar for a year

Observed dates are the unique union of:

| Source | When it applies |
|--------|-----------------|
| `scope=global` active rules | All staff |
| `scope=country` where `country_iso2` = duty station country | Staff whose latest contract duty station resolves to that ISO2 |
| `scope=duty_station` | Staff on that station (exceptions such as Meskel weekend compensatory for HQ/PANVAC) |
| Nationality independence month/day | Staff `nationality_id` → `nationalities.iso2` (Kenyan in Addis = ET holidays + Kenya Independence Day) |

Duty station country: `duty_stations.country_iso2` if set; otherwise parse `duty_stations.country` as ISO2 or match `nationalities` name/iso2/iso3.

Independence is **not** copied into `leave_holiday_rules`. It is generated at read/grant time from `nationalities.independence_month` / `independence_day`. Weekend independence days grant holiday compensatory the same way as weekend public holidays.

### Recurrence

- `yearly_md`: same month/day every year (Christmas, Adwa, Labour Day).
- `once`: a specific `once_date` (movable 2026 Eid, imported OpenHolidays row that should not recur).

Duplicate dates (global Christmas + imported ZA Christmas) collapse to one observed day.

### Weekday holidays inside a leave request

Policy key `weekday_holiday_in_request` (string):

| Value | Meaning | Default |
|-------|---------|---------|
| `skip_all` | A — do not count weekday public holidays as leave days | **Yes** |
| `count_all` | B — count them |
| `skip_annual_only` | C — skip holidays only when the request leave type is annual (`LeaveType::isAnnual()`) |

Weekends are never counted (existing Mon–Fri rule). `POST /leave/working-days` uses the authenticated staff calendar and optional `leave_id` for option C.

### Two compensatory buckets

| Kind | Leave type `code` | Grant | Expiry | Cap |
|------|-------------------|-------|--------|-----|
| `holiday` | `HOLIDAY_COMPENSATORY` | Weekend public holiday or weekend independence day | 31 Dec of year earned | 15 days granted per calendar year |
| `other` | `COMPENSATORY` | Manual / future overtime-travel tooling | `compensatory_expiry_months` (default 3) | none in this feature |

`available` for those types is remaining unexpired credits minus pending requests of that type. Other leave types do **not** add either bucket into `available` (Comp columns stay informational unless a later change implements deduct-compensatory-first against annual).

Credits are consumed when a request reaches `overall_status = Approved` (FIFO by `expires_on`). Submit still checks `available` so pending days reserve balance.

Cap 15 counts **days granted** of `kind=holiday` for the calendar year of `source_date`, not remaining unused. Nightly grant skips if the staff member is already at 15.

Idempotency: unique `(staff_id, kind, source_date)` for holiday rows.

Eligibility: latest contract status in **1 Active, 2 Due, 7 Under renewal**.

### Meskel weekend exception

Meskel is an Ethiopia (`ET`) country holiday (yearly 27 Sep). Weekend compensatory is only for HQ and PANVAC: `grants_compensatory_if_weekend` plus optional JSON `compensatory_duty_station_ids`. Empty JSON = all staff who observe the holiday; non-empty = only those stations get the credit (everyone in ET still observes the day off).

## Data model

### `leave_holiday_rules`

| Column | Type | Notes |
|--------|------|--------|
| `id` | PK | |
| `code` | string 80 unique nullable | Seeder upsert key (`global_new_year`, `et_adwa`) |
| `name` | string 150 | |
| `recurrence` | string 20 | `yearly_md` \| `once` |
| `month` | unsigned tinyint nullable | 1–12 |
| `day` | unsigned tinyint nullable | 1–31 |
| `once_date` | date nullable | |
| `scope` | string 20 | `global` \| `country` \| `duty_station` |
| `country_iso2` | char 2 nullable | `nationalities.iso2` |
| `duty_station_id` | unsigned int nullable | |
| `grants_compensatory_if_weekend` | boolean | default true |
| `compensatory_duty_station_ids` | json nullable | list of duty station PKs |
| `source` | string 20 | `manual` \| `openholidays` \| `seed` |
| `openholidays_id` | string 64 nullable | OpenHolidays UUID |
| `is_movable` | boolean | HR must confirm dates |
| `is_active` | boolean | |
| timestamps | | |

### `nationalities`

Add `independence_month`, `independence_day` (unsigned tinyint, nullable). Seed known AU dates; Ethiopia stays null (Adwa/Patriots already ET holidays).

### `duty_stations`

Add `country_iso2` char 2 nullable. Backfill from `country` / nationalities. Keep legacy `country` name column.

### `staff_leave_compensatory_credits`

Add:

- `kind` string 20 default `other` (`holiday` \| `other`)
- `source_holiday_rule_id` nullable FK-ish unsigned
- `source_date` date nullable

Unique index `staff_comp_credit_holiday_unique` on `(staff_id, kind, source_date)`.

Existing rows remain `kind=other`.

### Policy keys (defaults)

```
weekday_holiday_in_request = skip_all
holiday_compensatory_max_days_per_year = 15
compensatory_expiry_months = 3
```

`compensatory_public_holiday_months` is superseded for holiday credits (kept in stored settings for BC, not used for holiday grants).

### Leave type

Seed `HOLIDAY_COMPENSATORY` — “Holiday compensatory leave”, `leave_days` 0, not accrued. Identify by **code**, never hardcoded IDs.

## OpenHolidays

Base: `https://openholidaysapi.org`

- `GET /Countries?languageIsoCode=EN`
- `GET /PublicHolidays?countryIsoCode=&languageIsoCode=EN&validFrom=&validTo=`

Africa coverage is mainly **ZA**, not ET/KE/NG. Import **nationwide** holidays only. Movable-looking names (Eid, Mawlid, Good Friday, Easter) store as `once` + `is_movable`. Fixed nationwide dates store as `yearly_md` from `startDate` month/day unless the operator imports as once. Skip if a rule already exists for the same country + OpenHolidays id, or same country + month/day + name.

Ethiopia is **not** expected from this API; use the HQ seed.

## Permission

| ID | Name | Groups |
|----|------|--------|
| **98** | `manage_leave_holidays` | 10, 20, 22 |

Policy and types remain **97**. Holidays API requires 98 or HR role 20. Settings page: 97, 98, 15, or HR; Holidays tab only if 98 or HR. Module nav includes 98.

## API

Prefix `/api/v1`, Sanctum.

| Method | Path | Auth |
|--------|------|------|
| CRUD | `leave/settings/holidays` | 98 / HR |
| GET | `leave/settings/holidays/preview` | 98 / HR |
| GET | `leave/settings/holidays/openholidays/countries` | 98 / HR |
| GET | `leave/settings/holidays/openholidays/preview` | 98 / HR |
| POST | `leave/settings/holidays/openholidays/import` | 98 / HR |
| GET/PUT | `leave/settings/holidays/independence` | 98 / HR |
| GET/PUT | `leave/settings/holidays/duty-stations` | 98 / HR |
| POST | `leave/working-days` | + optional `leave_id` |

Balances payload adds `holiday_compensatory` next to `compensatory`.

## Nightly job

`leave:grant-holiday-compensatory {--year=}`  
Scheduled daily ~01:15 in `LeaveServiceProvider`. For each eligible staff, for each observed holiday with `date <= today` in the year where weekday is Sat/Sun and the rule (or independence) grants compensatory, insert 1.0 day `kind=holiday`, `expires_on` = 31 Dec that year, until cap 15. `--year` backfills up to today.

## Settings UI

Third tab **Holidays** on `LeaveSettingsPage.vue`:

- Rules table + create/edit (recurring vs once, scope, ISO2, station, weekend grant, movable, active).
- Year preview for a country and optional station.
- OpenHolidays: country + year → preview → import.
- Independence dates grid (from nationalities).
- Duty station ISO2 grid.

Lookup tables also expose the new nationality and duty-station columns.

## Balances UI

Replace the single Comp column with **Holiday comp** and **Comp**. Compensatory type rows use `available` from the matching ledger.

## Ethiopia HQ seed (2026 reference)

**Global yearly:** New Year 1 Jan, Labour Day 1 May, Africa Day 25 May, AU Day 9 Sep, International Christmas 25 Dec. Good Friday (international) 2026-04-03 as `once` + movable.

**ET yearly:** Ethiopian Christmas 7 Jan, Timket 19 Jan, Adwa 2 Mar, Patriots Day 5 May, Ethiopian New Year 11 Sep, Meskel 27 Sep (weekend compensatory HQ/PANVAC only).

**ET once + movable (HR confirms):** Ethiopian Good Friday 2026-04-10, Eid al-Fitr, Eid al-Adha, Mawlid — seeded inactive until HR sets `once_date` if unknown; if a 2026 HQ date is stored, keep `is_movable`.

## Files (implementation map)

- Migrations under `Modules/Leave/database/migrations/`
- `HolidayCalendarService`, `HolidayCompensatoryGrantService`, `OpenHolidaysClient`, `HolidayRuleOccurrenceExpander`
- `LeaveHolidaySettingsController`, routes in `Modules/Leave/routes/api.php`
- `LeavePermissions` 98, seeders, `LeavePolicySeeder` type
- Vue: `LeaveSettingsPage.vue`, `LeavePage.vue`, `leaveApi.ts`, `leavePermissions.ts`
- Tests: `tests/Unit/HolidayRuleOccurrenceExpanderTest.php`, `tests/Feature/LeaveHolidayCalendarTest.php`

## Risks

- `duty_stations.country` is messy (names vs ids); `country_iso2` plus Holidays tab mapping is the fix.
- OpenHolidays will not populate Ethiopia; seed + HR edits are required.
- `max(1, workingDays)` historically turned weekend-only ranges into 1 day; after this change, a range that is only weekend/skipped holidays returns **0** so staff cannot submit a 1-day phantom request.
