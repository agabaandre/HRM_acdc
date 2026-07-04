@extends('layouts.app')

@section('title', 'Dashboard')

@section('header', 'Dashboard')

@push('head-meta')
<style>
    #home-dashboard-app .hd-vuetify-app {
        background: transparent !important;
    }
    #home-dashboard-app .v-application__wrap {
        min-height: 0 !important;
    }
    #home-dashboard-app .hd-title {
        color: #119a48 !important;
    }
    #home-dashboard-app .hd-subtitle {
        color: rgba(0, 0, 0, 0.6) !important;
    }
    #home-dashboard-app .hd-pending-chip.v-chip {
        color: #92400e !important;
        background: #fffbeb !important;
        border: 1px solid #fcd34d !important;
    }
    #home-dashboard-app .hd-clear-chip.v-chip {
        color: #166534 !important;
        background: #dcfce7 !important;
        border: 1px solid #86efac !important;
    }
    #home-dashboard-app .hd-module-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-top: 4px solid #2ecc71 !important;
        height: 100%;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    #home-dashboard-app .hd-module-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08) !important;
    }
    #home-dashboard-app .hd-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #home-dashboard-app .hd-module-title {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.3;
    }
    #home-dashboard-app .hd-module-desc {
        color: rgba(0, 0, 0, 0.6) !important;
        font-size: 0.875rem;
        line-height: 1.45;
    }
    #home-dashboard-app .hd-module-card .v-chip {
        color: #991b1b !important;
        background: #fee2e2 !important;
    }
    #home-dashboard-app .hd-actions-label {
        color: rgba(0, 0, 0, 0.45) !important;
        letter-spacing: 0.04em;
    }
    #home-dashboard-app .hd-open-btn.v-btn {
        font-weight: 600;
    }
    #home-dashboard-app .hd-pending-btn.v-btn {
        font-weight: 600;
        color: #119a48 !important;
        border-color: rgba(17, 154, 72, 0.35) !important;
    }
    #home-dashboard-app .hd-pending-btn.v-btn:hover {
        background: rgba(17, 154, 72, 0.06) !important;
    }
</style>
@endpush

@section('content')
<div id="home-dashboard-app" data-apm-vuetify-page="home">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading dashboard…</p>
    </div>
</div>
@endsection
