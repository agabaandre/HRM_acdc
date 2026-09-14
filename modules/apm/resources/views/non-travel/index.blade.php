@extends('layouts.app')

@section('title', 'Non-Travel Memos')
@section('header', 'Non-Travel Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('non-travel.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Create New Memo
    </a>
</div>
@endsection

@push('head-meta')
<style>
    #non-travel-index-app .nt-vuetify-app { background: transparent !important; }
    #non-travel-index-app .v-application__wrap { min-height: 0 !important; }
    #non-travel-index-app .apm-list-table thead th {
        background: #f1f5f9 !important;
        color: rgba(15, 23, 42, 0.88) !important;
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #non-travel-index-app .apm-list-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
@if (session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} mb-3">{{ session('msg') }}</div>
@endif

<div id="non-travel-index-app" data-apm-vuetify-page="non-travel-index">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading non-travel memos…</p>
    </div>
</div>
@endsection
