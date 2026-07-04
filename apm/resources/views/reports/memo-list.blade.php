@extends('layouts.app')

@section('title', 'Reports – Memo list')
@section('header', 'Memo list (details)')

@push('head-meta')
<style>
    #memo-list-report-app .mlr-vuetify-app { background: transparent !important; }
    #memo-list-report-app .v-application__wrap { min-height: 0 !important; }
    #memo-list-report-app .mlr-table thead th {
        background: #f8fafc !important; font-weight: 600 !important; font-size: 0.75rem !important;
        text-transform: uppercase; letter-spacing: 0.03em;
    }
    #memo-list-report-app .text-wrap { word-wrap: break-word; white-space: normal; }
    @media print {
        .no-print { display: none !important; }
        #mlr-print-area, #mlr-print-area * { visibility: visible; }
    }
</style>
@endpush

@section('content')
<div id="memo-list-report-app" data-apm-vuetify-page="memo-list-report">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading memo list…</p>
    </div>
</div>
@endsection
