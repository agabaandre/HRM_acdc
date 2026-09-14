@php
    $pageConfig = $pageConfig ?? [];
@endphp

@push('head-meta')
<style>
    #stale-memos-app .sm-vuetify-app { background: transparent !important; }
    #stale-memos-app .v-application__wrap { min-height: 0 !important; }
    #stale-memos-app .sm-kpi-card {
        background: #fff !important;
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
        border-left: 3px solid var(--sm-kpi-accent, #119a48) !important;
        height: 100%;
    }
    #stale-memos-app .sm-kpi-icon-wrap {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #stale-memos-app .sm-kpi-value {
        color: rgba(0, 0, 0, 0.87) !important;
        font-size: 1.375rem;
        font-weight: 700;
        line-height: 1.2;
    }
    #stale-memos-app .sm-kpi-label {
        color: rgba(0, 0, 0, 0.55) !important;
        font-size: 0.75rem;
        font-weight: 500;
        margin-top: 2px;
    }
    #stale-memos-app .sm-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #stale-memos-app .sm-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: top !important;
    }
    #stale-memos-app .sm-title-cell {
        white-space: normal !important;
        word-break: break-word;
        overflow-wrap: anywhere;
        line-height: 1.35;
        max-width: 320px;
    }
    #stale-memos-app .sm-people-cell {
        white-space: pre-line;
        line-height: 1.4;
        min-width: 160px;
    }
</style>
@endpush

<div id="stale-memos-app" data-apm-vuetify-page="stale-memos" class="sys-config-stale-memos">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading stale memos…</p>
    </div>
</div>
