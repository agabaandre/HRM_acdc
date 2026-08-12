# Plan 4 Task 3 Report

## Status
- Implemented the SPA performance form page at `frontend/src/pages/performance/PerformanceFormPage.vue`.
- Replaced the old iframe bridge routes with the direct Vue page and removed `PerformanceFormBridgePage.vue`.
- Extended `frontend/src/lib/performanceApi.ts` with typed form/workflow clients for create, show, draft save, submit, approve, return, and consent.

## UI Scope
- Added `frontend/src/components/performance/PpaSections.vue` for Sections A-C.
- Added `frontend/src/components/performance/ReviewSections.vue` as the pragmatic consolidated midterm/endterm Sections A-E renderer.
- Added `frontend/src/components/performance/PerformanceWorkflowCard.vue` and `frontend/src/components/performance/PerformanceApprovalTrail.vue`.
- Updated `frontend/src/pages/performance/PerformancePage.vue` so create links always stay in the SPA and carry the selected period into `/performance/create`.

## Validation
- Client-side submit validation now enforces that the first three PPA objectives are filled and that objective weights total exactly `100%` before submission.
- Return actions require comments before the API call is made.

## Verification
- `npm run build` ✅
- `npm run typecheck` failed before file-level checking because `tsconfig.app.json` still uses deprecated `baseUrl`.
- `npx vue-tsc --noEmit --project tsconfig.app.json --ignoreDeprecations 6.0` exposed pre-existing cross-workspace Vue/Vuetify typing conflicts in `src/main.ts`.

## Concerns
- The API payload exposes supervisor/staff IDs, not names, so the new SPA currently shows numeric supervisor/staff identifiers in workflow/staff-detail surfaces where the legacy blades resolved names server-side.

## Follow-up: can_return parity
- Updated `staff-portal/backend/Modules/Performance/app/Http/Controllers/Api/V1/PerformanceFormApiController.php` so `show()` now exposes `can_return` only when the actor can approve in the workflow, has permission `83`, and `allow_supervisor_return` is enabled.
- Added focused regression coverage in `staff-portal/backend/tests/Feature/PerformanceFormApiTest.php` proving `can_return` is false without permission `83`, true with the permission plus enabled config, and false again when the config is disabled.
