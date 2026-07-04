@extends('layouts.app')

@section('title', 'Directorates')
@section('header', 'Directorates')

@section('content')
<div id="directorates-app" data-apm-vuetify-page="directorates">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading directorates…</p>
    </div>
</div>
@endsection

@push('head-meta')
<style>
    #directorates-app .dr-vuetify-app { background: transparent !important; }
    #directorates-app .v-application__wrap { min-height: 0 !important; }
    #directorates-app .dr-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-left: 3px solid var(--dr-kpi-accent, #119a48) !important;
        height: 100%;
    }
    #directorates-app .dr-kpi-icon-wrap {
        width: 40px; height: 40px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #directorates-app .dr-kpi-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1.375rem; font-weight: 700; line-height: 1.2;
    }
    #directorates-app .dr-kpi-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem; font-weight: 500; margin-top: 2px;
    }
    #directorates-app .dr-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
</style>
@endpush
