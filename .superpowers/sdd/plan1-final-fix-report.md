## 2026-08-13 Plan 1 fix report

- Branch: `feature/wave-staff-auth-performance`
- Scope: fixed Staff directory contract selection, CSV column selection, and duplicate search loading.

### Findings addressed

1. `StaffDirectoryService` now selects each staff member's current contract first (`status_id IN (1, 2, 7)`) and falls back to the latest contract only when no current contract exists.
2. `StaffApiController::exportCsv()` now honors the `columns` query using frontend column keys, while always prepending `Staff ID` and defaulting to the directory's standard visible columns when omitted.
3. `StaffIndexPage.vue` now avoids the duplicate search-triggered `load()` call when resetting pagination.

### Regression coverage

- Added feature coverage proving a staff member with an older active main-staff contract and a newer former other-staff contract still appears in the active/main-staff directory.
- Added feature coverage proving former listings still use the latest contract when no current contract exists.
- Added feature coverage proving CSV export honors requested columns.

### Verification

- `php artisan test tests/Feature/StaffDirectoryCategoryTest.php`
- `npm run build`
