@extends('layouts.app')

@section('title', 'Division Details')
@section('header', 'Division Details')

@section('header-actions')
<a wire:navigate href="{{ route('divisions.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-arrow-back"></i> Back to Divisions
</a>
@endsection

@push('head-meta')
<style>
    #division-show-app .ds-vuetify-app { background: transparent !important; }
    #division-show-app .v-application__wrap { min-height: 0 !important; }
</style>
@endpush

@section('content')
<div id="division-show-app" data-apm-vuetify-page="division-show">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading division…</p>
    </div>
</div>
@endsection
