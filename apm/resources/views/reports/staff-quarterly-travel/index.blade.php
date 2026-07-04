@extends('layouts.app')

@section('title', 'Reports – Staff Quarterly Travel Days')
@section('header', 'Staff Quarterly Travel Days')

@push('head-meta')
<style>
    #staff-quarterly-travel-app .sqt-vuetify-app { background: transparent !important; }
    #staff-quarterly-travel-app .v-application__wrap { min-height: 0 !important; }
    #staff-quarterly-travel-app .sqt-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-left: 3px solid var(--sqt-kpi-accent, #119a48) !important;
        height: 100%;
    }
    #staff-quarterly-travel-app .sqt-kpi-icon-wrap {
        width: 40px; height: 40px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #staff-quarterly-travel-app .sqt-kpi-value {
        font-size: 1.375rem; font-weight: 700; line-height: 1.2;
    }
    #staff-quarterly-travel-app .sqt-kpi-label {
        color: rgba(0, 0, 0, 0.55); font-size: 0.75rem; font-weight: 500; margin-top: 2px;
    }
    #staff-quarterly-travel-app .sqt-table thead th,
    #staff-quarterly-travel-app .sqt-breakdown-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
</style>
@endpush

@section('content')
<div id="staff-quarterly-travel-app" data-apm-vuetify-page="staff-quarterly-travel">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading staff quarterly travel report…</p>
    </div>
</div>
@endsection
