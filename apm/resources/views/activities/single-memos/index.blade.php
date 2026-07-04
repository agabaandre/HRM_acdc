@extends('layouts.app')

@section('title', 'Single Memos')
@section('header', 'Single Memos')

@push('head-meta')
<style>
    #single-memos-index-app .sm-vuetify-app { background: transparent !important; }
    #single-memos-index-app .v-application__wrap { min-height: 0 !important; }
    #single-memos-index-app .sm-list-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #single-memos-index-app .sm-list-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
<div id="single-memos-index-app" data-apm-vuetify-page="single-memos-index">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading single memos…</p>
    </div>
</div>
@endsection
