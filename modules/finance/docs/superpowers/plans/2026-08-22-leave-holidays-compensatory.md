# Leave holidays and holiday compensatory — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Staff Portal holiday rules, ISO2 calendars, OpenHolidays import, holiday vs other compensatory credits, and weekday-holiday skip in leave requests.

**Architecture:** Recurring/once `leave_holiday_rules` plus nationality independence dates; `HolidayCalendarService` expands a staff calendar; a nightly command writes `staff_leave_compensatory_credits` (`kind=holiday`) when an observed holiday falls on a weekend, capped at 15 and expiring 31 Dec; balances and apply-leave use two leave type codes.

**Tech Stack:** Laravel 12 modules, Sanctum, Vue 3 + Vuetify, PHPUnit, OpenHolidays HTTP API.

**Spec:** `staff-portal/docs/superpowers/specs/2026-08-22-leave-holidays-compensatory-design.md`

## Global Constraints

- Identify leave types by `code` (`HOLIDAY_COMPENSATORY`, `COMPENSATORY`, `ANNUAL`), never hardcoded IDs.
- Permission **98** `manage_leave_holidays`; policy/types stay **97**; default groups 10, 20, 22.
- Holiday compensatory: weekend observed holidays, cap 15 granted days/year, forfeit 31 Dec of year earned.
- Other compensatory: keep 3-month expiry (`compensatory_expiry_months`).
- Shared repeating holidays stored once as `scope=global`; country holidays keyed by `nationalities.iso2`.
- Active staff = latest contract status in 1, 2, 7.
- Do not rebuild CI3 leave.
- PHPUnit uses sqlite `:memory:`; Feature tests that need tables must `Schema::create` them (same pattern as `StaffCreateApiTest`).
- Run tests from `staff-portal/backend` with `./vendor/bin/phpunit --filter=...`.

## File map

**Create**

- `backend/Modules/Leave/database/migrations/2026_08_22_120000_add_leave_holiday_management.php`
- `backend/Modules/Leave/database/seeders/LeaveHolidaySeeder.php`
- `backend/Modules/Leave/app/Models/LeaveHolidayRule.php`
- `backend/Modules/Leave/app/Services/HolidayRuleOccurrenceExpander.php`
- `backend/Modules/Leave/app/Services/HolidayCalendarService.php`
- `backend/Modules/Leave/app/Services/HolidayCompensatoryGrantService.php`
- `backend/Modules/Leave/app/Services/OpenHolidaysClient.php`
- `backend/Modules/Leave/app/Support/IndependenceDayCatalog.php`
- `backend/Modules/Leave/app/Http/Controllers/Api/V1/LeaveHolidaySettingsController.php`
- `backend/Modules/Leave/app/Http/Resources/Api/V1/LeaveHolidayRuleResource.php`
- `backend/Modules/Leave/app/Console/GrantHolidayCompensatoryCommand.php`
- `backend/tests/Unit/HolidayRuleOccurrenceExpanderTest.php`
- `backend/tests/Feature/LeaveHolidayCalendarTest.php`

**Modify**

- `LeavePermissions.php`, `LeavePermissionsSeeder.php`, leave admin permissions migration pattern (new migration seeds 98)
- `LeaveAccess.php`, `LeavePolicyService.php`, `LeavePolicySeeder.php`
- `StaffLeaveCompensatoryCredit.php`, `LeaveType.php`, `LeaveBalanceService.php`, `LeaveRequestService.php`
- `LeaveServiceProvider.php`, `routes/api.php`, `LeaveMetaController.php`, `LeaveSettingsController.php` (policy keys only if needed)
- `MemberState.php`, `lookup-tables.php`
- `frontend/src/lib/leaveApi.ts`, `leavePermissions.ts`, `router/index.ts`
- `LeaveSettingsPage.vue`, `LeavePage.vue`, `LeaveApplyPage.vue`
- `SettingsApiController.php` hub label (optional)

---

### Task 1: Occurrence expander (pure)

**Files:** `HolidayRuleOccurrenceExpander.php`, `HolidayRuleOccurrenceExpanderTest.php`

- [ ] Write failing unit tests: `yearly_md` → that year’s date; `once` only if year matches; invalid dates skipped; output sorted unique Y-m-d.
- [ ] Run `./vendor/bin/phpunit --filter HolidayRuleOccurrenceExpanderTest` and confirm RED.
- [ ] Implement expander.
- [ ] Re-run until GREEN.

---

### Task 2: Schema, models, permission 98, policy keys

**Files:** migration, `LeaveHolidayRule`, credit model, `LeavePermissions`, seeders, `LeavePolicyService`, `LeaveType` helpers, lookup columns

- [ ] Migration: `leave_holiday_rules`; nationality independence columns; `duty_stations.country_iso2`; credit `kind` / `source_holiday_rule_id` / `source_date` + unique index; upsert permission 98 onto groups 10/20/22.
- [ ] Defaults: `weekday_holiday_in_request=skip_all`, `holiday_compensatory_max_days_per_year=15`.
- [ ] Seed `HOLIDAY_COMPENSATORY` type.
- [ ] Feature test can `Schema::create` the same shapes without running the full legacy dump.

---

### Task 3: Calendar + grants + working days (TDD)

**Files:** `HolidayCalendarService`, `HolidayCompensatoryGrantService`, `LeaveRequestService`, `LeaveHolidayCalendarTest`

- [ ] RED: Kenyan at ET station observes ET country holiday + Kenya independence + global Christmas; not a NG-only holiday.
- [ ] RED: Saturday holiday with grant flag creates 1.0 `kind=holiday` credit expiring 31 Dec; second run is idempotent; 16th grant blocked by cap 15.
- [ ] RED: `skip_all` omits a Wednesday holiday from working days; `count_all` includes it; weekends never count.
- [ ] GREEN: implement services; inject calendar into `LeaveRequestService` (nullable so `LeaveRequestNoticeTest` still constructs with two mocks).
- [ ] Consume holiday/other credits when `overall_status` becomes Approved.

---

### Task 4: OpenHolidays client + settings API

**Files:** `OpenHolidaysClient`, `LeaveHolidaySettingsController`, routes, resource

- [ ] Http::fake test: maps nationwide EN name + startDate; import skips duplicates by `openholidays_id`.
- [ ] CRUD + preview + independence + duty-station ISO endpoints; `LeaveAccess::authorizeHolidays()`.
- [ ] `LeaveMetaController::workingDays` accepts optional `leave_id`.

---

### Task 5: Seed ET/global rules + independence catalog + nightly command

**Files:** `LeaveHolidaySeeder`, `IndependenceDayCatalog`, `GrantHolidayCompensatoryCommand`, `LeaveServiceProvider`, `LeaveDatabaseSeeder`

- [ ] Seed global + ET rules from spec; Meskel compensatory station IDs resolved by HQ/PANVAC name match when present.
- [ ] Independence catalog upsert month/day where iso2 matches and columns are empty.
- [ ] Backfill `country_iso2` from `country` / nationalities.
- [ ] Register `leave:grant-holiday-compensatory` dailyAt `01:15`.

---

### Task 6: Vue settings Holidays tab + balances + apply

**Files:** `leaveApi.ts`, `LeaveSettingsPage.vue`, `LeavePage.vue`, `LeaveApplyPage.vue`, `leavePermissions.ts`, router

- [ ] Holidays tab: rules CRUD, year preview, OpenHolidays preview/import, independence + station ISO grids.
- [ ] Policy fields: weekday holiday mode, holiday cap 15; other compensatory months.
- [ ] Balances: `holiday_compensatory` + `compensatory` columns; include 98 in nav/settings access.
- [ ] Apply: `fetchWorkingDays(start, end, leave_id)`.

---

### Task 7: Verify

- [ ] `./vendor/bin/phpunit --filter 'HolidayRuleOccurrenceExpanderTest|LeaveHolidayCalendarTest|LeaveRequestNoticeTest'`
- [ ] `php -l` on new PHP files if pint is unavailable.
