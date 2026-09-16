@extends('layouts.app')

@section('title', 'Intramural SAP Budget Execution')
@section('header', 'Intramural SAP Budget Execution')

@push('head-meta')
<style>
    #intramural-sap-budget-execution-app .isbe-vuetify-app {
        background: transparent !important;
    }
    #intramural-sap-budget-execution-app .v-application__wrap {
        min-height: 0 !important;
    }
    #intramural-sap-budget-execution-app .isbe-hero {
        background: linear-gradient(135deg, #0b3b5c 0%, #0f5f8a 50%, #1780b5 100%);
        color: #fff;
    }
    #intramural-sap-budget-execution-app .isbe-code-link {
        font-weight: 650;
        text-decoration: none;
    }
</style>
@endpush

@section('content')
<div id="intramural-sap-budget-execution-app" data-apm-vuetify-page="intramural-sap-budget-execution">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 mb-0">Loading intramural SAP budget execution…</p>
    </div>
</div>
@endsection
