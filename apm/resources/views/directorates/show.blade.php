@extends('layouts.app')

@section('title', 'Directorate Details')
@section('header', 'Directorate Details')

@section('header-actions')
<a wire:navigate href="{{ route('directorates.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to List
</a>
@endsection

@push('head-meta')
<style>
    #directorate-show-app .drs-vuetify-app { background: transparent !important; }
    #directorate-show-app .v-application__wrap { min-height: 0 !important; }
</style>
@endpush

@section('content')
<div id="directorate-show-app" data-apm-vuetify-page="directorate-show">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading directorate…</p>
    </div>
</div>
@endsection
