@extends('layouts.app')

@section('title', 'Quarterly Travel Matrices')
@section('header', 'Quarterly Travel Matrices')

@push('head-meta')
<style>
    #matrices-index-app .mx-vuetify-app {
        background: transparent !important;
    }
    #matrices-index-app .v-application__wrap {
        min-height: 0 !important;
    }
    #matrices-index-app .mx-matrix-table thead th {
        background: #f1f5f9 !important;
        color: rgba(15, 23, 42, 0.88) !important;
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #matrices-index-app .mx-matrix-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
    #matrices-index-app .mx-matrix-table .v-data-table-footer {
        border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
</style>
@endpush

@section('content')
<div id="matrices-index-app" data-apm-vuetify-page="matrices-index">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading matrices…</p>
    </div>
</div>
@endsection
