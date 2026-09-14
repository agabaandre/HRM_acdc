@extends('layouts.app')

@section('title', 'Reports — Average time per document')
@section('header', 'Average time per document')

@push('head-meta')
<style>
    #approver-document-timing-app .adt-vuetify-app {
        background: transparent !important;
    }
    #approver-document-timing-app .v-application__wrap {
        min-height: 0 !important;
    }
    #approver-document-timing-app .adt-doc-title {
        word-break: break-word;
        overflow-wrap: anywhere;
        white-space: normal;
        line-height: 1.4;
    }
    #approver-document-timing-app .adt-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-top: 4px solid var(--adt-kpi-accent, #119a48) !important;
        height: 100%;
    }
</style>
@endpush

@section('content')
<div id="approver-document-timing-app" data-apm-vuetify-page="approver-document-timing">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading timing report…</p>
    </div>
</div>
@endsection
