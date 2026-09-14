# Finance — Laravel + Inertia

Finance is a **Laravel 12** app at `staff/finance/` (same layout as `staff/apm/`), with **Inertia.js + React** for the UI.

## Layout (APM-aligned)

| Piece | React component |
|-------|-----------------|
| Shell | `resources/js/Layouts/AppLayout.jsx` |
| Top bar | `Components/Layout/TopHeader.jsx` |
| CBP Modules | `Components/Layout/CbpModulesDropdown.jsx` |
| Primary nav | `Components/Layout/PrimaryNav.jsx` |

Shared props are in `app/Http/Middleware/HandleInertiaRequests.php`.

## Adding a page

1. `Route::get(...)` in `routes/web.php` (inside `EnsureFinanceSession`).
2. `Inertia::render('YourPage', [...])` from a controller.
3. `resources/js/Pages/YourPage.jsx` using `<AppLayout>`.

## CBP Modules

`CbpModulesNavService` → Staff Share API with `active_module_key=finance_management`.

## Legacy stack removed

Former `finance/server` (Express) and `finance/frontend` (CRA) are gone. Former `finance/laravel/` nested folder is flattened into `finance/`.
