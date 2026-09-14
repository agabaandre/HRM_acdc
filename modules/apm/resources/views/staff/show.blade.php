@extends('layouts.app')

@section('title', 'Staff Details')

@section('header', 'Staff Details')

@push('head-meta')
<style>
    #staff-show-app .ss-vuetify-app {
        background: transparent !important;
    }
    #staff-show-app .v-application__wrap {
        min-height: 0 !important;
    }
    #staff-show-app .ss-section-card {
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
    }
    #staff-show-app .ss-section-title {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1rem !important;
        font-weight: 600 !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        padding-bottom: 12px !important;
    }
    #staff-show-app .ss-info-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #staff-show-app .ss-info-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1rem;
        font-weight: 500;
    }
    #staff-show-app .ss-activities-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #staff-show-app .ss-activities-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
    }
</style>
@endpush

@section('content')
<div id="staff-show-app" data-apm-vuetify-page="staff-show">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading staff profile…</p>
    </div>
</div>
@endsection
