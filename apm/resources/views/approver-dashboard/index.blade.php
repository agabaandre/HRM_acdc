@extends('layouts.app')

@section('title', 'Approver Dashboard')

@section('header', 'Approver Dashboard')

@section('header-actions')
<button type="button" class="btn btn-info btn-sm" onclick="window.approverDashboardRefresh && window.approverDashboardRefresh()">
    <i class="fa fa-sync-alt"></i> Refresh
</button>
@endsection

@push('head-meta')
<style>
    #approver-dashboard-app .ad-vuetify-app {
        background: transparent !important;
    }
    #approver-dashboard-app .v-application__wrap {
        min-height: 0 !important;
    }
    #approver-dashboard-app .ad-approver-col {
        min-width: 16rem;
    }
    #approver-dashboard-app .ad-role-col {
        max-width: 350px;
        white-space: normal;
    }
    #approver-dashboard-app .workflow-pipeline-arrow {
        opacity: 0.55;
    }
    /* KPI cards — white surface, accent top bar (readable on all themes) */
    #approver-dashboard-app .ad-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-top: 4px solid var(--ad-kpi-accent, #119a48) !important;
        height: 100%;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    #approver-dashboard-app .ad-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08) !important;
    }
    #approver-dashboard-app .ad-kpi-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    #approver-dashboard-app .ad-kpi-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.2;
    }
    #approver-dashboard-app .ad-kpi-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-top: 0.25rem;
    }
    /* Approver table — high-contrast headers and body text */
    #approver-dashboard-app .ad-approver-table {
        --v-table-header-height: 44px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 8px;
        overflow: hidden;
    }
    #approver-dashboard-app .ad-approver-table .ad-table-th,
    #approver-dashboard-app .ad-approver-table thead th,
    #approver-dashboard-app .ad-approver-table .v-data-table__th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.6875rem !important;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 0.75rem 1rem !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
        vertical-align: middle;
    }
    #approver-dashboard-app .ad-approver-table .ad-table-header-sortable {
        cursor: pointer;
        user-select: none;
    }
    #approver-dashboard-app .ad-approver-table .ad-table-header-sortable:hover {
        color: rgba(0, 0, 0, 0.87) !important;
    }
    #approver-dashboard-app .ad-approver-table tbody td,
    #approver-dashboard-app .ad-approver-table .v-data-table__td {
        color: rgba(0, 0, 0, 0.87) !important;
        background: #fff !important;
        vertical-align: middle !important;
    }
    #approver-dashboard-app .ad-approver-table tbody tr:hover td,
    #approver-dashboard-app .ad-approver-table .v-data-table__tr:hover .v-data-table__td {
        background: rgba(17, 154, 72, 0.06) !important;
    }
    #approver-dashboard-app .ad-table-name {
        color: rgba(0, 0, 0, 0.87) !important;
        font-weight: 600;
    }
    #approver-dashboard-app .ad-table-muted {
        color: rgba(0, 0, 0, 0.6) !important;
    }
    #approver-dashboard-app .ad-table-date {
        color: rgba(0, 0, 0, 0.75) !important;
    }
    #approver-dashboard-app .ad-chip-link.v-chip {
        font-weight: 600;
    }
    #approver-dashboard-app .ad-chip-info.v-chip {
        color: #0369a1 !important;
        background: #e0f2fe !important;
        border: 1px solid #7dd3fc !important;
    }
    #approver-dashboard-app .ad-chip-last5.v-chip {
        color: #0d7a3a !important;
        background: #f0fdf4 !important;
        border: 1px solid #86efac !important;
    }
    #approver-dashboard-app .ad-chip-success.v-chip {
        color: #166534 !important;
        background: #dcfce7 !important;
        border: 1px solid #86efac !important;
    }
    #approver-dashboard-app .ad-chip-error.v-chip {
        color: #991b1b !important;
        background: #fee2e2 !important;
        border: 1px solid #fca5a5 !important;
    }
    #approver-dashboard-app .ad-chip-primary.v-chip {
        color: #1e40af !important;
        background: #dbeafe !important;
        border: 1px solid #93c5fd !important;
    }
    #approver-dashboard-app .ad-chip-pending.v-chip {
        color: #92400e !important;
        background: #fffbeb !important;
        border: 1px solid #fcd34d !important;
    }
    #approver-dashboard-app .ad-chip-pending.v-chip:hover {
        background: #fef3c7 !important;
    }
    #approver-dashboard-app .ad-chip-role.v-chip {
        color: #0c4a6e !important;
        background: #e0f2fe !important;
        border: 1px solid #7dd3fc !important;
    }
</style>
@endpush

@section('content')
<div id="approver-dashboard-app" data-apm-vuetify-page="approver-dashboard">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading approver dashboard…</p>
    </div>
</div>
@endsection
