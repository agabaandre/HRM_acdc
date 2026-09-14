@extends('layouts.app')

@section('title', 'Special Memos')
@section('header', 'Special Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('special-memo.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(($pageConfig['pendingApprovalCount'] ?? 0) > 0)
            <span class="badge bg-danger ms-1">{{ $pageConfig['pendingApprovalCount'] }}</span>
        @endif
    </a>
    <a href="{{ route('special-memo.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Create New Memo
    </a>
</div>
@endsection

@push('head-meta')
<style>
    #special-memos-index-app .sm-vuetify-app { background: transparent !important; }
    #special-memos-index-app .v-application__wrap { min-height: 0 !important; }
    #special-memos-index-app .apm-list-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #special-memos-index-app .apm-list-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
@if (session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} mb-3">{{ session('msg') }}</div>
@endif

<div id="special-memos-index-app" data-apm-vuetify-page="special-memos-index">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading special memos…</p>
    </div>
</div>
@endsection
