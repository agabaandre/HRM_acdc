@extends('layouts.app')

@section('title', 'Divisions')
@section('header', 'Divisions')

@section('header-actions')
<div class="text-muted">
    <small><i class="bx bx-info-circle me-1"></i>Divisions are managed in the main system</small>
</div>
@endsection

@push('head-meta')
<style>
    #divisions-app .dv-vuetify-app { background: transparent !important; }
    #divisions-app .v-application__wrap { min-height: 0 !important; }
    #divisions-app .dv-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-left: 3px solid var(--dv-kpi-accent, #119a48) !important;
        height: 100%;
    }
    #divisions-app .dv-kpi-icon-wrap {
        width: 40px; height: 40px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #divisions-app .dv-kpi-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1.375rem; font-weight: 700; line-height: 1.2;
    }
    #divisions-app .dv-kpi-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem; font-weight: 500; margin-top: 2px;
    }
    #divisions-app .dv-table thead th {
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
<div id="divisions-app" data-apm-vuetify-page="divisions">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading divisions…</p>
    </div>
</div>
@endsection
