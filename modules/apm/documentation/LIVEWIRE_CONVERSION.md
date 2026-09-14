# APM Livewire Conversion Guide

The APM uses Livewire for dynamic UI while keeping existing routes and controllers. Pages render content through the **app-page** Livewire component.

## Converted Modules (use Livewire)

- **Home** (`/home`) – `pages/home-content.blade.php`
- **Reports index** (`/reports`) – `pages/reports-index-content.blade.php`
- **Approver Dashboard** (`/approver-dashboard`) – `pages/approver-dashboard-content.blade.php`
- **Matrices index** (`/matrices`) – `pages/matrices-index-content.blade.php`
- **Session expiry modal** – `@livewire('session-expiry-modal')` in main layout
- **Dashboard filters** (optional) – `@livewire('dashboard-filters')`

## How to Convert Another Module

1. **Create a content partial**  
   Copy the current view’s `@section('content')` body (only the inner HTML) into  
   `resources/views/pages/{module}-content.blade.php`.

2. **Update the main view**  
   Replace that `@section('content')` with:

   ```blade
   @section('content')
   @livewire('app-page', [
       'view' => 'pages.{module}-content',
       'data' => compact('var1', 'var2', ...)  % use the same vars the controller passes to the view
   ])
   @endsection
   ```

3. **Remove the old content**  
   Delete the original HTML that was moved to the partial (so it only lives in `pages/{module}-content.blade.php`).

4. **Keep everything else**  
   Leave `@extends`, `@section('title')`, `@push('scripts')`, `@push('styles')`, etc. in the main view. Controllers and routes stay unchanged.

## Components

- **app-page** – Renders any Blade view with the given data:  
  `@livewire('app-page', ['view' => 'pages.xyz-content', 'data' => $data])`
- **session-expiry-modal** – Session warning/expired modals (used in layout).
- **dashboard-filters** – Example Livewire filter block; use or copy for other filters.

## Notes

- Controllers still pass the same data to the view; the view forwards it to `app-page` via the `data` parameter.
- Existing JavaScript (DataTables, Select2, etc.) continues to work; use `wire:ignore` on nodes that must not be touched by Livewire if needed.
- To add Livewire-driven behavior (e.g. filters, modals), add `wire:model`, `wire:click`, etc. in the content partial or in dedicated Livewire components.
