@extends('layouts.app')

@section('title', 'Change Requests')
@section('header', 'Change Requests')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('change-requests.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(($pageConfig['pendingApprovalCount'] ?? 0) > 0)
            <span class="badge bg-danger ms-1">{{ $pageConfig['pendingApprovalCount'] }}</span>
        @endif
    </a>
</div>
@endsection

@push('head-meta')
<style>
    #change-requests-index-app .cr-vuetify-app { background: transparent !important; }
    #change-requests-index-app .v-application__wrap { min-height: 0 !important; }
    #change-requests-index-app .apm-list-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #change-requests-index-app .apm-list-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
@if (session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} mb-3">{{ session('msg') }}</div>
@endif

<div id="change-requests-index-app" data-apm-vuetify-page="change-requests-index">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading change requests…</p>
    </div>
</div>
@endsection
