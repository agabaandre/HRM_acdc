# Wave 2 — Permissions + Settings SPA

**Status:** Done (2026-08-12)

## Delivered

- `PortalPermission` Sanctum-aware gate (15 / 17)
- Permissions API `/api/v1/permissions/*` + Vue `/permissions`
- Settings API `/api/v1/settings/*` + Vue `/settings`, `/settings/performance`, `/settings/lookup/:table`
- Livewire removed; web routes redirect to SPA
- `/admin/rbac` redirects to `/permissions`

## Gates

- Settings: permission **15**
- Permissions: permission **17**
- Leave settings (Wave 1): HR `role_id === 20` (unchanged)
