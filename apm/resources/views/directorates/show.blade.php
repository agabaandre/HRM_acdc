@extends('layouts.app')

@section('title', 'Directorate Details')
@section('header', 'Directorate Details')

@push('head-meta')
<style>
    #directorates-show-app .dr-show-vuetify-app { background: transparent !important; }
    #directorates-show-app .v-application__wrap { min-height: 0 !important; }
    #directorates-show-app .dr-show-section-card {
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
    }
    #directorates-show-app .dr-show-section-title {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1rem !important;
        font-weight: 600 !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        padding-bottom: 12px !important;
    }
    #directorates-show-app .dr-show-info-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #directorates-show-app .dr-show-info-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1rem;
        font-weight: 500;
    }
    #directorates-show-app .dr-show-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #directorates-show-app .dr-show-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
    }
</style>
@endpush

@section('content')
<div id="directorates-show-app" data-apm-vuetify-page="directorates-show">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading directorate…</p>
    </div>
</div>
@endsection
