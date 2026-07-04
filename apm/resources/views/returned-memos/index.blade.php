@extends('layouts.app')

@section('title', 'Returned Memos')

@section('header', 'My Returned/Draft Memos')

@push('head-meta')
<style>
    #returned-memos-app .rm-vuetify-app { background: transparent !important; }
    #returned-memos-app .v-application__wrap { min-height: 0 !important; }
    #returned-memos-app .rm-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-top: 4px solid var(--rm-accent, #119a48) !important;
        height: 100%;
    }
    #returned-memos-app .rm-kpi-icon-wrap {
        width: 44px; height: 44px; border-radius: 12px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    #returned-memos-app .rm-kpi-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1.5rem; font-weight: 700;
    }
    #returned-memos-app .rm-kpi-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
    }
    #returned-memos-app .rm-returned-table thead th {
        background: #2c3e50 !important;
        color: #fff !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
    }
    #returned-memos-app .rm-returned-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
<div id="returned-memos-app" data-apm-vuetify-page="returned-memos">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading returned memos…</p>
    </div>
</div>
@endsection
