@extends('layouts.app')

@section('title', 'Reports – Division memo counts')
@section('header', 'Division memo counts')

@push('head-meta')
<style>
    #division-counts-report-app .dcr-vuetify-app { background: transparent !important; }
    #division-counts-report-app .v-application__wrap { min-height: 0 !important; }
    #division-counts-report-app .dcr-kpi-card {
        background: #fff !important; border: 1px solid rgba(0,0,0,0.08) !important;
        border-left: 3px solid var(--dcr-kpi-accent, #119a48) !important; height: 100%;
    }
    #division-counts-report-app .dcr-kpi-icon-wrap {
        width: 40px; height: 40px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #division-counts-report-app .dcr-kpi-value { font-size: 1.375rem; font-weight: 700; }
    #division-counts-report-app .dcr-kpi-label { color: rgba(0,0,0,0.55); font-size: 0.75rem; }
    #division-counts-report-app .dcr-table thead th {
        background: #f8fafc !important; font-weight: 600 !important; font-size: 0.75rem !important;
        text-transform: uppercase; letter-spacing: 0.03em;
    }
    @media print {
        .no-print { display: none !important; }
        #dcr-print-area, #dcr-print-area * { visibility: visible; }
    }
</style>
@endpush

@section('content')
<div id="division-counts-report-app" data-apm-vuetify-page="division-counts-report">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading division counts…</p>
    </div>
</div>
@endsection
