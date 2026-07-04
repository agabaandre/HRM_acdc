@extends('layouts.app')

@section('title', 'Division Details')
@section('header', 'Division Details')

@push('head-meta')
<style>
    #divisions-show-app .dv-show-vuetify-app { background: transparent !important; }
    #divisions-show-app .v-application__wrap { min-height: 0 !important; }
    #divisions-show-app .dv-show-section-card {
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
    }
    #divisions-show-app .dv-show-section-title {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1rem !important;
        font-weight: 600 !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        padding-bottom: 12px !important;
    }
    #divisions-show-app .dv-show-info-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #divisions-show-app .dv-show-info-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 0.95rem;
        font-weight: 500;
    }
    #divisions-show-app .dv-show-role-card {
        border-left: 4px solid rgba(0, 0, 0, 0.12) !important;
        height: 100%;
    }
    #divisions-show-app .dv-show-role-card--primary { border-left-color: #119a48 !important; }
    #divisions-show-app .dv-show-role-card--info { border-left-color: #0ea5e9 !important; }
    #divisions-show-app .dv-show-role-card--success { border-left-color: #119a48 !important; }
    #divisions-show-app .dv-show-role-card--warning { border-left-color: #f59e0b !important; }
</style>
@endpush

@section('content')
<div id="divisions-show-app" data-apm-vuetify-page="divisions-show">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading division…</p>
    </div>
</div>
@endsection
