@extends('layouts.app')

@section('title', 'Service Requests')
@section('header', 'Service Requests')

@push('head-meta')
<style>
    #service-requests-index-app .sr-vuetify-app { background: transparent !important; }
    #service-requests-index-app .v-application__wrap { min-height: 0 !important; }
    #service-requests-index-app .apm-list-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #service-requests-index-app .apm-list-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
@if (session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} mb-3">{{ session('msg') }}</div>
@endif

<div id="service-requests-index-app" data-apm-vuetify-page="service-requests-index">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading service requests…</p>
    </div>
</div>
@endsection
