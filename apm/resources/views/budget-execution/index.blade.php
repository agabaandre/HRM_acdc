@extends('layouts.app')

@section('title', 'Budget execution dashboard')
@section('header', 'Budget execution dashboard')

@push('head-meta')
<style>
    #budget-execution-app .be-vuetify-app {
        background: transparent !important;
    }
    #budget-execution-app .v-application__wrap {
        min-height: 0 !important;
    }
    #budget-execution-app .be-hero {
        background: linear-gradient(135deg, #0d7a38 0%, #119a48 45%, #1cb35c 100%);
        color: #fff;
    }
    #budget-execution-app .be-initiative-title {
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: anywhere;
        line-height: 1.35;
    }
</style>
@endpush

@section('content')
<div id="budget-execution-app" data-apm-vuetify-page="budget-execution">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading budget execution…</p>
    </div>
</div>
@endsection
