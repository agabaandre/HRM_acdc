@extends('layouts.app')

@section('title', 'Weekly brief')
@section('header', 'Weekly brief')

@push('head-meta')
<style>
    #weekly-briefing-app .wb-vuetify-app {
        background: transparent !important;
    }
    #weekly-briefing-app .v-application__wrap {
        min-height: 0 !important;
    }
    #weekly-briefing-app .wb-hub-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.65) !important;
        font-size: 0.6875rem !important;
        font-weight: 600 !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    #weekly-briefing-app .wb-hub-table tbody td {
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
<div id="weekly-briefing-app" data-apm-vuetify-page="weekly-briefing">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading weekly brief…</p>
    </div>
</div>
@endsection
