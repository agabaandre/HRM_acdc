@extends('layouts.app')

@section('title', 'Pending Approvals')

@section('header', 'Pending Approvals Dashboard')

@push('head-meta')
<style>
    #pending-approvals-app .pa-vuetify-app { background: transparent !important; }
    #pending-approvals-app .v-application__wrap { min-height: 0 !important; }
    #pending-approvals-app .pa-stale-alert {
        border-radius: 8px !important;
        border: 1px solid rgba(245, 158, 11, 0.35) !important;
        background: linear-gradient(90deg, rgba(255, 251, 235, 0.95) 0%, #fff 14%) !important;
        border-left: 3px solid #f59e0b !important;
    }
    #pending-approvals-app .pa-stale-alert-body {
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
    #pending-approvals-app .pa-stale-heading {
        color: #92400e !important;
        font-size: 0.8125rem !important;
        line-height: 1.3 !important;
    }
    #pending-approvals-app .pa-stale-copy {
        color: #78350f !important;
        font-size: 0.75rem !important;
        line-height: 1.4 !important;
        margin-bottom: 0 !important;
    }
    #pending-approvals-app .pa-stale-link,
    #pending-approvals-app .pa-stale-date {
        color: #92400e !important;
    }
    #pending-approvals-app .pa-stale-link:hover {
        color: #78350f !important;
    }
    #pending-approvals-app .pa-stale-chip.v-chip {
        background: #fef3c7 !important;
        color: #92400e !important;
        font-weight: 600 !important;
        font-size: 0.6875rem !important;
        height: 22px !important;
        padding: 0 8px !important;
    }
    #pending-approvals-app .pa-chip-last5.v-chip {
        background: #fff7ed !important;
        color: #9a3412 !important;
        font-weight: 700 !important;
    }
    #pending-approvals-app .pa-chip-all.v-chip {
        background: #e0f2fe !important;
        color: #0369a1 !important;
        font-weight: 700 !important;
    }
    #pending-approvals-app .pa-avg-time-inline {
        padding: 0.5rem 0.75rem;
        background: #f0fdf4;
        border: 1px solid rgba(17, 154, 72, 0.15);
        border-left: 3px solid #119a48;
        border-radius: 8px;
    }
    #pending-approvals-app .pa-avg-time-card {
        border-left: 4px solid #119a48 !important;
        background: #fff !important;
    }
    #pending-approvals-app .pa-category-card {
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        overflow: hidden;
        background: #fff !important;
    }
    #pending-approvals-app .pa-category-header {
        background: #fff !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
        color: rgba(0, 0, 0, 0.87) !important;
    }
    #pending-approvals-app .pa-category-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(17, 154, 72, 0.08);
        color: #119a48;
        flex-shrink: 0;
    }
    #pending-approvals-app .pa-category-title {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 0.9375rem !important;
        font-weight: 600 !important;
        letter-spacing: 0.01em;
    }
    #pending-approvals-app .pa-open-badge.v-chip {
        background: rgba(17, 154, 72, 0.08) !important;
        color: #0d7a3a !important;
        font-weight: 600 !important;
        border: 1px solid rgba(17, 154, 72, 0.18) !important;
    }
    #pending-approvals-app .pa-approvals-table {
        border-top: none !important;
    }
    #pending-approvals-app .pa-approvals-table thead th,
    #pending-approvals-app .pa-approvals-table .v-data-table__th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.62) !important;
        font-weight: 600 !important;
        font-size: 0.6875rem !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }
    #pending-approvals-app .pa-approvals-table tbody td,
    #pending-approvals-app .pa-approvals-table .v-data-table__td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
        background: #fff !important;
    }
    #pending-approvals-app .pa-approvals-table tbody tr:hover td,
    #pending-approvals-app .pa-approvals-table .v-data-table__tr:hover .v-data-table__td {
        background: rgba(17, 154, 72, 0.04) !important;
    }
    #pending-approvals-app .pa-approvals-table tr.pa-row-stale td {
        background: rgba(255, 251, 235, 0.9) !important;
    }
    #pending-approvals-app .pa-approvals-table tr.pa-row-stale td:first-child {
        box-shadow: inset 4px 0 0 #f59e0b;
    }
    #pending-approvals-app .pa-chip-info.v-chip,
    #pending-approvals-app .pa-chip-division.v-chip,
    #pending-approvals-app .pa-chip-role.v-chip {
        height: auto !important;
        min-height: 28px !important;
        padding: 8px 12px !important;
        white-space: normal !important;
    }
    #pending-approvals-app .pa-chip-info.v-chip .v-chip__content,
    #pending-approvals-app .pa-chip-division.v-chip .v-chip__content,
    #pending-approvals-app .pa-chip-role.v-chip .v-chip__content {
        padding: 0 !important;
        white-space: normal !important;
        line-height: 1.45 !important;
    }
    #pending-approvals-app .pa-chip-info.v-chip {
        background: #eff6ff !important;
        color: #1d4ed8 !important;
        border: 1px solid rgba(37, 99, 235, 0.2) !important;
        font-weight: 600 !important;
    }
    #pending-approvals-app .pa-chip-division.v-chip {
        background: #f1f5f9 !important;
        color: #334155 !important;
        border: 1px solid rgba(100, 116, 139, 0.2) !important;
        font-weight: 500 !important;
    }
    #pending-approvals-app .pa-chip-role.v-chip {
        background: #fff7ed !important;
        color: #9a3412 !important;
        border: 1px solid rgba(234, 88, 12, 0.25) !important;
        font-weight: 600 !important;
    }
    #pending-approvals-app .pa-chip-stale.v-chip {
        background: #fff7ed !important;
        color: #c2410c !important;
        border: 1px solid rgba(234, 88, 12, 0.25) !important;
        font-weight: 600 !important;
    }
    #pending-approvals-app .pa-approver-card {
        border-left: 4px solid #119a48 !important;
        background: #fff !important;
    }
</style>
@endpush

@section('content')
<div id="pending-approvals-app" data-apm-vuetify-page="pending-approvals">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading pending approvals…</p>
    </div>
</div>
@endsection
