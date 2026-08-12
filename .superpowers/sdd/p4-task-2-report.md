# Plan 4 Task 2 Report

## Status

Completed.

## What changed

- Added write-action endpoints in `staff-portal/backend/Modules/Performance/app/Http/Controllers/Api/V1/PerformanceFormApiController.php` for draft save, submit, approve, return, and consent.
- Registered `PUT /api/v1/performance/entries/{entryId}` plus `/submit`, `/approve`, `/return`, and `/consent` routes in `staff-portal/backend/Modules/Performance/routes/api.php`.
- Mapped request payloads to the existing `PpaFormService` and `PerformanceApprovalService` method signatures instead of reimplementing workflow logic.
- Added JSON validation for invalid phase/action inputs and enforced owner/supervisor access checks before write actions.

## Verification

- `php artisan route:list --path=performance/entries`
- `php artisan test --filter=PerformanceFormApiTest`

## Notes / concerns

- The current focused coverage proves the happy path for create/bootstrap, show, and PPA draft save; submit/approve/return/consent are implemented but still need dedicated follow-up tests if deeper workflow regression coverage is wanted.
- `returnForRevision()` in the shared approval service does not perform its own actor check, so the controller now enforces that workflow permission explicitly before calling it.

## Follow-up: Important API review findings

- `printEntry()` now applies the same synced entry access authorization path as `show()` before rendering the PDF, preventing non-owner/non-supervisor access.
- `exportCsv()` now scopes role-17 users to their own staff analytics, matching the existing `analytics()` restriction.
- `returnEntry()` now requires both `ppa_configs.allow_supervisor_return` and permission `83` in addition to the workflow approval check before allowing a supervisor return.
- Added focused regression coverage in `staff-portal/backend/tests/Feature/PerformanceFormApiTest.php` for PDF access control, role-17 CSV scoping, missing permission `83`, and disabled supervisor-return config.
