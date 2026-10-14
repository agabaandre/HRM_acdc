@extends('layouts.app')

@section('title', 'Budget Execution')
@section('header', 'Budget Execution')

@push('head-meta')
<style>
    #budget-execution-app .be-vuetify-app { background: transparent !important; }
    #budget-execution-app .v-application__wrap { min-height: 0 !important; }
    #budget-execution-app .be-hero {
        background: linear-gradient(135deg, #0b3b5c 0%, #0f5f8a 50%, #1780b5 100%);
        color: #fff;
    }
    #budget-execution-app .be-kpi strong {
        font-size: 1.55rem; font-weight: 800; letter-spacing: -0.02em; color: #0f172a;
    }
    #budget-execution-app .be-fund {
        display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px;
        background: #e8f6ee; color: #0f5132; font-size: 0.78rem; font-weight: 700;
    }
    #budget-execution-app .be-row-link { cursor: pointer; }
    #budget-execution-app .be-row-link:hover { background: #f1f5f9; }
    #budget-execution-app .be-chart { min-height: 320px; }
</style>
@endpush

@section('content')
<div id="budget-execution-app" data-apm-vuetify-page="budget-execution">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 mb-0">Loading budget execution from Finance…</p>
    </div>
</div>
@endsection
