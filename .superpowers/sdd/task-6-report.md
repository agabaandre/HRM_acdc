# Task 6 Report: Contract CRUD API + staff show UI

## Status
- Completed contract create/update API support for staff profiles and replaced the read-only contracts section on `/staff/:id` with add/edit contract management UI.

## Backend
- Added `POST /api/v1/staff/{staff}/contracts` and `PUT /api/v1/staff/{staff}/contracts/{contract}` to `Modules/Staff/routes/api.php`.
- Extended `Modules/Staff/app/Http/Controllers/Api/V1/StaffApiController.php` with `storeContract()` and `updateContract()` methods gated by manage-contracts permission.
- Added contract-form request validation in the controller and returned JSON `422` payloads when `StaffContractService` raises uniqueness validation errors.
- Kept contract existence checks lightweight by querying `staff` directly instead of loading the full profile join tree.
- Added focused feature coverage in `tests/Feature/StaffContractApiTest.php` for create success, update success, and uniqueness `422` behavior.

## Frontend
- Extended `frontend/src/lib/staffApi.ts` with typed contract row/payload interfaces plus `createContract()` and `updateContract()` helpers.
- Rebuilt `frontend/src/pages/staff/StaffShowPage.vue` to:
  - keep the contract history table,
  - show add/renew and edit actions when `can_manage_contracts` is true,
  - lazy-load form lookups,
  - prefill renew values from the latest contract,
  - surface inline `422` validation messages from the API.

## Verification
- `./vendor/bin/phpunit tests/Feature/StaffContractApiTest.php tests/Feature/StaffCreateApiTest.php`
- `npm run build`

## Concerns
- The SPA renew flow reuses `staff/form-lookups` and latest-contract data, so no dedicated `contract-lookups` endpoint was added in this task.
- Contract PDF upload was not added to the SPA form because it was not part of the requested deliverables for Task 6.
