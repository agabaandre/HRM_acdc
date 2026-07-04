@php
    $tablesList = isset($tables)
        ? ($tables instanceof \Illuminate\Support\Collection ? $tables->values()->all() : (array) $tables)
        : [];

    $pageConfig = [
        'routes' => [
            'data' => route('audit-logs.data'),
            'cleanupModal' => route('audit-logs.cleanup-modal'),
            'cleanup' => route('audit-logs.cleanup'),
            'reverse' => route('audit-logs.reverse'),
            'export' => route('system-configs.index'),
        ],
        'csrf' => csrf_token(),
        'canReverse' => in_array(91, user_session('permissions', [])),
        'actions' => $actions ?? [],
        'tables' => $tablesList,
        'stats' => $stats ?? [],
        'filters' => [
            'search' => request('search', ''),
            'action' => request('action', ''),
            'table' => request('table', ''),
            'date_from' => request('date_from', ''),
            'date_to' => request('date_to', ''),
            'suspicious' => request('suspicious', ''),
        ],
    ];
@endphp

@push('head-meta')
<style>
    #audit-logs-app .al-vuetify-app { background: transparent !important; }
    #audit-logs-app .v-application__wrap { min-height: 0 !important; }
    #audit-logs-app .al-stat-card {
        border-left: 4px solid #119a48;
        height: 100%;
    }
    #audit-logs-app .al-stat-value {
        word-break: break-word;
        line-height: 1.2;
    }
    #audit-logs-app .al-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 700 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #audit-logs-app .al-detail-list {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 8px;
    }
    #audit-logs-app .al-json {
        margin: 0;
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 4px;
        font-size: 0.75rem;
        overflow: auto;
        max-height: 240px;
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>
@endpush

<div id="audit-logs-app" data-apm-vuetify-page="audit-logs" class="sys-config-audit">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading audit logs…</p>
    </div>
</div>
