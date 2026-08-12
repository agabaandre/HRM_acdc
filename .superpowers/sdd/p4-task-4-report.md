# Plan 4 Task 4 Report

## Status

Completed.

## What changed

- Replaced the legacy Performance Livewire web entry routes in `staff-portal/backend/Modules/Performance/routes/web.php` with redirects to the Vue SPA paths under the configured `staff-portal.spa_url` base.
- Preserved the `period` query string on the legacy create route so older backend links still land on the correct SPA create screen.
- Added focused coverage in `staff-portal/backend/tests/Feature/PerformanceWebRoutesTest.php` for the create, PPA, midterm, and endterm redirect targets.

## Verification

- `php artisan test tests/Feature/PerformanceWebRoutesTest.php`
- `rg "PerformanceFormBridge|iframe|view_ppa" staff-portal/frontend/src` returned zero matches.
- Reviewed `staff-portal/frontend/src/router/index.ts` and confirmed the performance create/form routes both load `PerformanceFormPage.vue`.
- Reviewed `staff-portal/backend/Modules/Performance/app/Http/Controllers/Api/V1/PerformanceHubApiController.php` and confirmed `form_url`, `midterm_url`, and `endterm_url` already point to SPA paths.

## Notes / concerns

- The hub payload still exposes legacy-named compatibility fields such as `livewire_url` and `create_ppa_livewire_url`; the SPA path fields used by the frontend are already correct.
