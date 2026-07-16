@extends('layouts.app')

@section('title', 'Staff Management')

@section('header', 'Staff Management')

@section('header-actions')
@if(in_array(89, user_session('permissions', [])))
<a href="{{ route('whatsapp-groups.index') }}" class="btn btn-outline-success btn-sm me-2" wire:navigate>
    <i class="bx bxl-whatsapp me-1"></i> WhatsApp groups
</a>
@endif
@if(user_session('division_id'))
<a href="{{ route('participant-groups.index') }}" class="btn btn-outline-success btn-sm me-2">
    <i class="fas fa-layer-group me-1"></i> Participant Groups
</a>
@endif
@endsection

@push('head-meta')
<style>
    #staff-directory-app .sd-vuetify-app {
        background: transparent !important;
    }
    #staff-directory-app .v-application__wrap {
        min-height: 0 !important;
    }
    #staff-directory-app .sd-staff-table .v-data-table-footer {
        border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
    #staff-directory-app .sd-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-left: 3px solid var(--sd-kpi-accent, #119a48) !important;
        height: 100%;
    }
    #staff-directory-app .sd-kpi-icon-wrap {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #staff-directory-app .sd-kpi-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1.375rem;
        font-weight: 700;
        line-height: 1.2;
    }
    #staff-directory-app .sd-kpi-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem;
        font-weight: 500;
        margin-top: 2px;
    }
    #staff-directory-app .sd-staff-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #staff-directory-app .sd-staff-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
    }
</style>
@endpush

@section('content')
<div id="staff-directory-app" data-apm-vuetify-page="staff">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading staff directory…</p>
    </div>
</div>
@endsection
