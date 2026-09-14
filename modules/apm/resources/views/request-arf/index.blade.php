@extends('layouts.app')

@section('title', 'ActRF')
@section('header', 'Request for ARF')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('request-arf.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(($pendingArfCount ?? 0) > 0)
            <span class="badge bg-danger ms-1">{{ $pendingArfCount }}</span>
        @endif
    </a>
</div>
@endsection

@push('head-meta')
<style>
    #request-arf-index-app .arf-vuetify-app { background: transparent !important; }
    #request-arf-index-app .v-application__wrap { min-height: 0 !important; }
    #request-arf-index-app .arf-list-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #request-arf-index-app .arf-list-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
</style>
@endpush

@section('content')
@if (session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} mb-3">{{ session('msg') }}</div>
@endif

<div id="request-arf-index-app" data-apm-vuetify-page="request-arf-index">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading ARF requests…</p>
    </div>
</div>
@endsection
