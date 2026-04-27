<style>
:root {
  --primary-color: #119a48;
  --primary-dark: #0d7a3a;
  --primary-light: #1bb85a;
  --secondary-color: #9f2240;
  --secondary-light: #c44569;
  --accent-black: #2c3e50;
  --light-grey: #f8f9fa;
  --medium-grey: #e9ecef;
  --dark-grey: #6c757d;
  --text-dark: #2c3e50;
  --text-muted: #6c757d;
  --border-color: #e9ecef;
  --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
  --transition: all 0.2s ease;
}

  /* Role column: fixed width and wrap */
  .approver-role-cell {
    max-width: 350px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
  }

  /* Approver column: minimum width +15% (14rem baseline → 16.1rem) */
  #approverTable th.approver-dashboard-approver-col,
  #approverTable td.approver-dashboard-approver-col {
    min-width: calc(14rem * 1.15);
  }

  /* Approver column: allow long division / email / names to wrap within column width */
  #approverTable td .approver-dashboard-approver-text {
    min-width: 0;
    max-width: 100%;
    overflow-wrap: anywhere;
    word-wrap: break-word;
    word-break: break-word;
  }
  #approverTable td .approver-division-line {
    white-space: normal;
    line-height: 1.35;
  }

  /* Enhanced Approver Dashboard Styling */
  .filter-card {
    border: 1px solid var(--medium-grey);
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
  }

  .filter-card .card-header {
    background: var(--light-grey);
    color: var(--text-muted);
    border: none;
    padding: 1rem 1.5rem;
  }

  .table-card {
    border: 1px solid var(--medium-grey);
    box-shadow: var(--shadow);
    overflow: hidden;
  }

  .table-card .card-header {
    background: var(--accent-black);
    color: white;
    border: none;
    padding: 1rem 1.5rem;
  }

  .table-card .card-body {
    padding: 1.5rem;
  }

  .btn-modern {
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: var(--transition);
    background: rgba(23, 162, 184, 0.1);
    color: rgba(23, 162, 184, 0.8);
    border: 1px solid rgba(23, 162, 184, 0.2);
  }

  .btn-modern:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
    background: rgba(23, 162, 184, 0.15);
    color: rgba(23, 162, 184, 1);
  }

  /* Quality stats cards (workplan-style: top border, icon box, compact height) */
  .stats-container {
    background: transparent;
    padding: 0;
    margin-bottom: 2rem;
  }

  .stats-container .stat-item {
    background: #fff;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    border-radius: 8px;
    padding: 1rem 0.75rem;
    text-align: center;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    border: 1px solid var(--medium-grey);
    height: 100%;
  }

  .stats-container .stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
  }

  .stats-container .stat-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  }

  .stats-container .stat-item.total {
    --stat-color: #17a2b8;
    --stat-color-light: #20c997;
  }

  .stats-container .stat-item.pending {
    --stat-color: #f0ad4e;
    --stat-color-light: #f5d071;
  }

  .stats-container .stat-item.workflow {
    --stat-color: #0d7a3a;
    --stat-color-light: #1bb85a;
  }

  .stats-container .stat-item.updated {
    --stat-color: #119A48;
    --stat-color-light: #34ce57;
  }

  .stats-container .stat-item.approved {
    --stat-color: #198754;
    --stat-color-light: #20c997;
  }

  .stats-container .stat-item.returned {
    --stat-color: #0dcaf0;
    --stat-color-light: #6ea8fe;
  }

  /* Workflow stats table — approver flow column */
  #workflowStatsTable.table-workflow-stats thead th {
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #5c6f82;
    border-bottom-width: 2px;
  }
  #workflowStatsTable.table-workflow-stats tbody tr:hover .workflow-role-step {
    border-color: rgba(17, 154, 72, 0.45) !important;
    box-shadow: 0 2px 10px rgba(17, 154, 72, 0.12) !important;
  }
  .workflow-pipeline-arrow {
    align-self: center;
    opacity: 0.55;
  }
  .workflow-hint-icon {
    cursor: help;
    font-size: 0.85rem;
  }

  .stats-container .stat-icon-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 10px;
    margin-bottom: 0.5rem;
    background: var(--stat-color);
    background: linear-gradient(135deg, var(--stat-color) 0%, var(--stat-color-light) 100%);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
  }

  .stats-container .stat-icon {
    font-size: 1.35rem;
    color: #fff;
    margin: 0;
    display: block;
  }

  .stats-container .stat-number {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--stat-color);
    display: block;
    margin-bottom: 0.25rem;
    line-height: 1.2;
  }

  .stats-container .stat-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 700;
    margin: 0;
  }

  @keyframes countUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Approver Cards */
  .approver-card {
    background: white;
    box-shadow: var(--shadow);
    padding: 1rem;
    margin-bottom: 0.75rem;
    transition: var(--transition);
    border: 1px solid var(--medium-grey);
  }

  .approver-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
  }

  .approver-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(32, 201, 151, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(23, 162, 184, 0.8);
    font-weight: bold;
    font-size: 1rem;
    border: 1px solid rgba(23, 162, 184, 0.2);
  }

  .approver-number {
    width: 30px;
    height: 30px;
    background: var(--dark-grey);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
    margin: 0 auto;
  }

  .pending-bar {
    height: 8px;
    background: var(--medium-grey);
    overflow: hidden;
    margin-top: 0.5rem;
  }

  .pending-fill {
    height: 100%;
    background: linear-gradient(90deg, rgba(17, 154, 72, 0.6), rgba(52, 206, 87, 0.6));
    transition: width 1s ease-out;
  }

  /* Enhanced Table Styling */
  .table {
    overflow: hidden;
    box-shadow: var(--shadow);
  }

  .table thead th {
    background: #2c3e50 !important;
    color: white !important;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
    padding: 1rem 0.75rem;
    border: none;
    position: relative;
  }

  .table thead th.sorting,
  .table thead th.sorting_asc,
  .table thead th.sorting_desc {
    cursor: pointer;
  }

  .table thead th.sorting:after {
    content: "\f0dc";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    float: right;
    opacity: 0.5;
  }

  .table thead th.sorting_asc:after {
    content: "\f0de";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    float: right;
    color: white;
  }

  .table thead th.sorting_desc:after {
    content: "\f0dd";
    font-family: "Font Awesome 5 Free";
    font-weight: 900;
    float: right;
    color: white;
  }

  .table tbody tr {
    transition: var(--transition);
  }

  .table tbody tr:hover {
    background-color: rgba(23, 162, 184, 0.05);
    transform: scale(1.005);
  }

  .table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-color: var(--medium-grey);
  }


  .status-badge {
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: var(--shadow);
  }

  .loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
  }

  .loading-spinner {
    background: white;
    padding: 2rem;
    text-align: center;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--medium-grey);
  }
</style>

@extends('layouts.app')

@section('title', 'Approver Dashboard')

@section('header', 'Approver Dashboard')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-info btn-modern" onclick="refreshDashboard()">
        <i class="fa fa-sync-alt"></i> Refresh
    </button>
</div>
@endsection

@section('content')
@include('pages.approver-dashboard-content', compact('userDivisionId', 'hasPermission88'))
@endsection

@push('scripts')
<script>
(function() {
var approverTable;
var filterOptions = {};
var userDivisionId = {{ $userDivisionId ?? 'null' }};
var hasPermission88 = {{ $hasPermission88 ? 'true' : 'false' }};
var baseUrl = '{{ user_session("base_url") ?? url("/") }}';
var staffPhotoRoute = @json(route('staff-uploads.photo'));
var pendingApprovalsBaseUrl = '{{ route("pending-approvals.index") }}';
var dashboardApiUrl = '{{ route("approver-dashboard.api") }}';
var workflowStatsData = [];
var workflowChartUnit = 'days';

/** Hint keys from ApproverDashboardHelper::getApproverRolesPipelineForWorkflow → FA icon + tooltip */
var WF_ROLE_HINTS = {
    fund_type: { icon: 'fa-coins', cls: 'text-warning', title: 'Fund type: this level may only run for certain fund types (others can skip it).' },
    funder: { icon: 'fa-hand-holding-usd', cls: 'text-info', title: 'Funder filter: limited to configured funders.' },
    division: { icon: 'fa-building', cls: 'text-primary', title: 'Division-specific: actor resolved from division roles.' },
    division_field: { icon: 'fa-id-badge', cls: 'text-primary', title: 'Uses a division field (e.g. head, focal person) to pick the approver.' },
    category: { icon: 'fa-tags', cls: 'text-secondary', title: 'Document category / memo rules apply to this step.' },
    category_gate: { icon: 'fa-code-branch', cls: 'text-success', title: 'Conditional branch: may be skipped when category checks do not apply.' },
    division_scope: { icon: 'fa-map-marker-alt', cls: 'text-danger', title: 'Limited to selected division(s).' }
};

// Table cache: 5 min TTL, cache-first then background refresh
var CACHE_TTL_MS = 5 * 60 * 1000;
var CACHE_KEY_PREFIX = 'approverDashboard_';
var skipCacheNextRequest = false;

function buildTableRequestParams(d) {
    var params = typeof d === 'object' && d !== null ? Object.assign({}, d) : {};
    params.q = $('#searchApprover').val() || null;
    params.division_id = $('#filterDivision').val() || null;
    params.doc_type = $('#filterDocType').val() || null;
    params.approval_level = $('#filterApprovalLevel').val() || null;
    var monthVal = $('#filterMonth').val();
    params.month = monthVal ? parseInt(monthVal, 10) : null;
    var yearVal = $('#filterYear').val();
    params.year = yearVal ? parseInt(yearVal, 10) : null;
    params.page = Math.floor((params.start || 0) / (params.length || 25)) + 1;
    params.per_page = params.length || 25;
    delete params.start;
    delete params.draw;
    return params;
}

function getTableCacheKey(params) {
    var key = { page: params.page, per_page: params.per_page, order: params.order, q: params.q, division_id: params.division_id, doc_type: params.doc_type, approval_level: params.approval_level, month: params.month, year: params.year };
    return CACHE_KEY_PREFIX + JSON.stringify(key);
}

function getCachedTableResponse(cacheKey) {
    try {
        var raw = sessionStorage.getItem(cacheKey);
        if (!raw) return null;
        var parsed = JSON.parse(raw);
        if (!parsed || !parsed.json || typeof parsed.cachedAt !== 'number') return null;
        return parsed;
    } catch (e) { return null; }
}

function setCachedTableResponse(cacheKey, json) {
    try {
        sessionStorage.setItem(cacheKey, JSON.stringify({ json: json, cachedAt: Date.now() }));
    } catch (e) {}
}

function setLastUpdatedDisplay(timestamp) {
    var el = document.getElementById('lastUpdated');
    if (!el) return;
    if (typeof timestamp !== 'number') timestamp = Date.now();
    var d = new Date(timestamp);
    el.textContent = d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function pendingApprovalsFilterParams() {
    const y = $('#filterYear').val();
    const m = $('#filterMonth').val();
    return (y ? '&year=' + encodeURIComponent(y) : '') + (m ? '&month=' + encodeURIComponent(m) : '');
}

function initApproverDashboard() {
    if (!document.getElementById('filterDivision')) return;
    if (approverTable) {
        try { approverTable.destroy(); } catch (e) {}
        approverTable = null;
    }
    loadFilterOptions().then(function() {
        initializeDataTable();
    });
    let filterTimeout;
    $('#searchApprover').off('keyup.approverDashboard').on('keyup.approverDashboard', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            if (approverTable) approverTable.draw();
        }, 500);
    });
    $('#filterDivision, #filterDocType, #filterApprovalLevel, #filterMonth, #filterYear').off('change.approverDashboard').on('change.approverDashboard', function() {
        if (approverTable) approverTable.draw();
        loadWorkflowStats();
    });
    $('#clearFilters').off('click.approverDashboard').on('click.approverDashboard', function() {
        $('#searchApprover').val('');
        $('#filterDivision').val('');
        $('#filterDocType').val('');
        $('#filterApprovalLevel').val('');
        $('#filterMonth').val('');
        $('#filterYear').val('');
        if (!hasPermission88 && userDivisionId) $('#filterDivision').val(userDivisionId);
        $('#filterYear').val(new Date().getFullYear());
        if (approverTable) approverTable.draw();
        loadWorkflowStats();
    });
    $('#wfChartUnitDays, #wfChartUnitHours').off('click.approverDashboard').on('click.approverDashboard', function() {
        workflowChartUnit = this.id === 'wfChartUnitHours' ? 'hours' : 'days';
        $('#wfChartUnitDays').toggleClass('active', workflowChartUnit === 'days');
        $('#wfChartUnitHours').toggleClass('active', workflowChartUnit === 'hours');
        if (workflowStatsData && workflowStatsData.length) {
            renderWorkflowStats(workflowStatsData);
        }
    });
    $('#wfChartUnitDays').toggleClass('active', workflowChartUnit === 'days');
    $('#wfChartUnitHours').toggleClass('active', workflowChartUnit === 'hours');
    $('#exportExcel').off('click.approverDashboard').on('click.approverDashboard', function() {
        if (approverTable) approverTable.button('.buttons-excel').trigger();
    });
    $('#exportPdfTable').off('click.approverDashboard').on('click.approverDashboard', function() {
        exportData('pdf');
    });
}
$(document).ready(initApproverDashboard);
document.addEventListener('livewire:navigated', initApproverDashboard);

function loadFilterOptions() {
    return $.ajax({
        url: '{{ route("approver-dashboard.filter-options") }}',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                filterOptions = response.data;
                populateFilterOptions();
            }
        },
        error: function() {
            console.error('Failed to load filter options');
        }
    });
}

function populateFilterOptions() {
    // Populate divisions
    const divisionSelect = $('#filterDivision');
    divisionSelect.empty().append('<option value="">All Divisions</option>');
    filterOptions.divisions.forEach(function(division) {
        divisionSelect.append(`<option value="${division.id}">${division.division_name}</option>`);
    });
    
    // Set user's division if no permission 88
    if (!hasPermission88 && userDivisionId) {
        divisionSelect.val(userDivisionId);
    }
    
    // Populate document types
    const docTypeSelect = $('#filterDocType');
    docTypeSelect.empty().append('<option value="">All Types</option>');
    filterOptions.document_types.forEach(function(type) {
        docTypeSelect.append(`<option value="${type.value}">${type.label}</option>`);
    });
    
    // Populate approval levels
    const approvalLevelSelect = $('#filterApprovalLevel');
    approvalLevelSelect.empty().append('<option value="">All Levels</option>');
    filterOptions.approval_levels.forEach(function(level) {
        approvalLevelSelect.append(`<option value="${level.value}">${level.label}</option>`);
    });
    
    // Populate years
    const yearSelect = $('#filterYear');
    const currentYear = new Date().getFullYear();
    yearSelect.empty().append('<option value="">All Years</option>');
    if (filterOptions.years && filterOptions.years.length > 0) {
        filterOptions.years.forEach(function(year) {
            const selected = (year == currentYear) ? 'selected' : '';
            yearSelect.append(`<option value="${year}" ${selected}>${year}</option>`);
        });
    } else {
        // Generate years if not provided
        for (let i = currentYear - 5; i <= currentYear + 2; i++) {
            const selected = (i == currentYear) ? 'selected' : '';
            yearSelect.append(`<option value="${i}" ${selected}>${i}</option>`);
        }
    }

    // Load workflow stats (respects current filters)
    loadWorkflowStats();
}

function loadWorkflowStats() {
    const params = {
        division_id: $('#filterDivision').val() || '',
        doc_type: $('#filterDocType').val() || '',
        month: $('#filterMonth').val() || '',
        year: $('#filterYear').val() || ''
    };
    $.ajax({
        url: '{{ route("approver-dashboard.workflow-stats") }}',
        type: 'GET',
        data: params,
        dataType: 'json',
        success: function(response) {
            if (response && response.success && response.data && Array.isArray(response.data)) {
                renderWorkflowStats(response.data);
            } else {
                $('#workflowStatsBody').html('<tr><td colspan="4" class="text-center text-muted">No data</td></tr>');
                clearWorkflowChart();
            }
        },
        error: function(xhr) {
            $('#workflowStatsBody').html('<tr><td colspan="4" class="text-center text-danger">Error loading workflow stats</td></tr>');
            clearWorkflowChart();
        }
    });
}

function clearWorkflowChart() {
    if (typeof Highcharts !== 'undefined') {
        var chartEl = document.getElementById('workflowAvgTimeChart');
        if (chartEl && chartEl.__chart) {
            try { chartEl.__chart.destroy(); chartEl.__chart = null; } catch (e) {}
        }
    }
}

function renderApproverRolesPipeline(row) {
    var pipeline = row.approver_roles_pipeline;
    if (pipeline && pipeline.length) {
        var parts = [];
        for (var i = 0; i < pipeline.length; i++) {
            var step = pipeline[i];
            var hints = step.hints || [];
            var icons = '';
            for (var h = 0; h < hints.length; h++) {
                var meta = WF_ROLE_HINTS[hints[h]];
                if (!meta) continue;
                icons += '<i class="fa ' + meta.icon + ' ' + (meta.cls || '') + ' workflow-hint-icon ms-1" title="' + escapeHtml(meta.title) + '" aria-hidden="true"></i>';
            }
            parts.push(
                '<span class="workflow-role-step d-inline-flex align-items-center flex-wrap rounded-3 border bg-white px-2 py-1 me-0 mb-1 shadow-sm">' +
                '<span class="text-dark" style="font-size:0.9rem;">' + escapeHtml(step.role || '') + '</span>' + icons + '</span>'
            );
        }
        return '<div class="workflow-pipeline d-flex flex-wrap align-items-center">' + parts.join(
            '<span class="workflow-pipeline-arrow d-inline-flex align-items-center px-1" title="Next step"><i class="fa fa-long-arrow-alt-right" aria-hidden="true"></i></span>'
        ) + '</div>';
    }
    if (row.approver_roles != null && String(row.approver_roles).trim() !== '') {
        return '<span class="text-break small">' + escapeHtml(String(row.approver_roles)) + '</span>';
    }
    return '<span class="text-muted">—</span>';
}

function formatWorkflowAvgCell(row) {
    var unit = workflowChartUnit || 'days';
    if (unit === 'hours') {
        return escapeHtml(row.avg_display || 'No data');
    }
    var d = row.avg_days != null ? Number(row.avg_days) : (Number(row.avg_hours || 0) / 24);
    if (!isFinite(d) || d <= 0) {
        return '<span class="text-muted">No data</span>';
    }
    var rounded = d >= 10 ? Math.round(d * 10) / 10 : Math.round(d * 100) / 100;
    return escapeHtml(String(rounded)) + ' <span class="text-muted small">days</span>';
}

function renderWorkflowStats(stats) {
    workflowStatsData = stats && stats.length ? stats.slice() : [];
    var tbody = $('#workflowStatsBody');
    var overallCountEl = $('#workflowStatsOverallCount');
    var overallAvgEl = $('#workflowStatsOverallAvg');
    var nonWeightedCountEl = $('#workflowStatsNonWeightedCount');
    var nonWeightedAvgEl = $('#workflowStatsNonWeightedAvg');
    var totalTimeEl = $('#workflowStatsTotalTime');
    tbody.empty();
    if (!workflowStatsData.length) {
        tbody.html('<tr><td colspan="4" class="text-center text-muted">No workflow data</td></tr>');
        overallCountEl.text('0');
        overallAvgEl.text('No data');
        nonWeightedCountEl.text('0');
        nonWeightedAvgEl.text('No data');
        totalTimeEl.text('No data');
        clearWorkflowChart();
        return;
    }
    workflowStatsData.forEach(function(row) {
        var docTypes = (row.doc_type_labels && row.doc_type_labels.length)
            ? row.doc_type_labels.join(', ')
            : '';
        var docTypesHtml = docTypes
            ? '<div class="small text-muted mt-1">' + escapeHtml(docTypes) + '</div>'
            : '';
        var rolesCell = renderApproverRolesPipeline(row);
        tbody.append('<tr><td class="align-top"><div class="fw-semibold text-dark">' + escapeHtml(row.workflow_name || '-') + '</div>' + docTypesHtml + '</td><td class="align-top py-2">' + rolesCell + '</td><td class="text-end align-middle fw-semibold">' + (row.memos != null ? row.memos : 0) + '</td><td class="text-end align-middle">' + formatWorkflowAvgCell(row) + '</td></tr>');
    });

    // Weighted overall average across all workflows (weighted by approved count).
    var totalApproved = 0;
    var totalHours = 0;
    var nonWeightedHoursSum = 0;
    var nonWeightedRows = 0;
    workflowStatsData.forEach(function(row) {
        var count = Number(row.memos || 0);
        var avgHours = Number(row.avg_hours || 0);
        if (isFinite(count) && isFinite(avgHours) && count > 0 && avgHours > 0) {
            totalApproved += count;
            totalHours += (count * avgHours);
            nonWeightedHoursSum += avgHours;
            nonWeightedRows += 1;
        }
    });
    overallCountEl.text(String(totalApproved));
    nonWeightedCountEl.text(String(nonWeightedRows));
    var unit = (workflowChartUnit || 'days');

    if (totalApproved > 0 && totalHours > 0) {
        var avgHoursAll = totalHours / totalApproved;
        if (unit === 'hours') {
            overallAvgEl.html(escapeHtml(String(Math.round(avgHoursAll * 10) / 10)) + ' <span class="text-muted small">hrs</span>');
        } else {
            var avgDaysAll = avgHoursAll / 24;
            var roundedDays = avgDaysAll >= 10 ? Math.round(avgDaysAll * 10) / 10 : Math.round(avgDaysAll * 100) / 100;
            overallAvgEl.html(escapeHtml(String(roundedDays)) + ' <span class="text-muted small">days</span>');
        }
    } else {
        overallAvgEl.text('No data');
    }

    if (nonWeightedRows > 0 && nonWeightedHoursSum > 0) {
        var nonWeightedHoursAvg = nonWeightedHoursSum / nonWeightedRows;
        if (unit === 'hours') {
            nonWeightedAvgEl.html(escapeHtml(String(Math.round(nonWeightedHoursAvg * 10) / 10)) + ' <span class="text-muted small">hrs</span>');
        } else {
            var nonWeightedDaysAvg = nonWeightedHoursAvg / 24;
            var nonWeightedRoundedDays = nonWeightedDaysAvg >= 10
                ? Math.round(nonWeightedDaysAvg * 10) / 10
                : Math.round(nonWeightedDaysAvg * 100) / 100;
            nonWeightedAvgEl.html(escapeHtml(String(nonWeightedRoundedDays)) + ' <span class="text-muted small">days</span>');
        }
    } else {
        nonWeightedAvgEl.text('No data');
    }

    if (totalHours > 0) {
        if (unit === 'hours') {
            totalTimeEl.html(escapeHtml(String(Math.round(totalHours * 10) / 10)) + ' <span class="text-muted small">hrs</span>');
        } else {
            var totalDays = totalHours / 24;
            var totalRoundedDays = totalDays >= 10 ? Math.round(totalDays * 10) / 10 : Math.round(totalDays * 100) / 100;
            totalTimeEl.html(escapeHtml(String(totalRoundedDays)) + ' <span class="text-muted small">days</span>');
        }
    } else {
        totalTimeEl.text('No data');
    }

    // Column chart: defer render so container is in DOM (fixes Livewire/navigation timing)
    var categories = workflowStatsData.map(function(s) { return s.workflow_name || 'Unknown'; });
    var seriesData = workflowStatsData.map(function(s) {
        if (unit === 'hours') {
            return Math.round((Number(s.avg_hours) || 0) * 10) / 10;
        }
        var days = s.avg_days != null ? Number(s.avg_days) : (Number(s.avg_hours) || 0) / 24;
        return Math.round(days * 1000) / 1000;
    });
    var maxVal = seriesData.length ? Math.max.apply(null, seriesData) : 0;
    var yMax = maxVal > 0 ? Math.ceil(maxVal * 1.15 * 100) / 100 : (unit === 'hours' ? 10 : 5);
    var yTitle = unit === 'hours' ? 'Time to last approver (hours)' : 'Time to last approver (days)';
    var subText = unit === 'hours'
        ? 'Time from submission to final approval, in hours.'
        : 'Time from submission to final approval, in days (default).';

    function drawChart() {
        var chartEl = document.getElementById('workflowAvgTimeChart');
        if (!chartEl) return false;
        if (typeof Highcharts === 'undefined') return false;
        if (chartEl.__chart) {
            try { chartEl.__chart.destroy(); chartEl.__chart = null; } catch (e) {}
        }
        try {
            var chart = Highcharts.chart('workflowAvgTimeChart', {
                chart: { type: 'column', height: 350 },
                title: { text: 'Average Time to Last Approver (approved documents only)' },
                subtitle: { text: subText },
                xAxis: { categories: categories, title: { text: 'Workflow' }, crosshair: true, labels: { rotation: -45 } },
                yAxis: {
                    min: 0,
                    max: yMax,
                    title: { text: yTitle },
                    allowDecimals: true
                },
                tooltip: {
                    headerFormat: '<b>{point.x}</b><br/>',
                    pointFormat: unit === 'hours'
                        ? 'Avg. time to last approver: {point.y} hrs'
                        : 'Avg. time to last approver: {point.y} days'
                },
                plotOptions: {
                    column: {
                        color: 'var(--primary-color, #119a48)',
                        borderRadius: 4,
                        dataLabels: {
                            enabled: true,
                            format: unit === 'hours' ? '{y} hrs' : '{y} d'
                        }
                    }
                },
                series: [{ name: unit === 'hours' ? 'Avg. time (hours)' : 'Avg. time (days)', data: seriesData }],
                credits: { enabled: false }
            });
            chartEl.__chart = chart;
            return true;
        } catch (err) {
            if (typeof console !== 'undefined' && console.warn) console.warn('Workflow chart render failed:', err);
            return false;
        }
    }
    function tryDrawChart(attempt) {
        attempt = attempt || 0;
        var maxAttempts = 25;
        if (drawChart()) return;
        if (attempt >= maxAttempts) return;
        setTimeout(function() { tryDrawChart(attempt + 1); }, 100);
    }
    setTimeout(function() { tryDrawChart(0); }, 0);
}

function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/** Basename for staff-uploads/photo (handles DB paths like uploads/staff/file.jpg). */
function staffPortraitBasename(photo) {
    if (photo == null || typeof photo !== 'string') return '';
    const t = photo.trim().replace(/\\/g, '/');
    if (!t) return '';
    const i = t.lastIndexOf('/');
    return i >= 0 ? t.slice(i + 1) : t;
}

function initializeDataTable() {
    var $table = $('#approverTable');
    if ($table.length && typeof $.fn.DataTable !== 'undefined' && $.fn.DataTable.isDataTable($table[0])) {
        try {
            $table.DataTable().destroy();
        } catch (e) {}
    }
    approverTable = null;
    approverTable = $table.DataTable({
        processing: true,
        serverSide: true,
        searching: false, // Disable DataTables search box
        ordering: true,
        order: [[7, 'desc']], // Sort by Avg. Time (highest days) descending by default
        ajax: function(data, callback, settings) {
            var params = buildTableRequestParams(data);
            var cacheKey = getTableCacheKey(params);
            var cached = getCachedTableResponse(cacheKey);
            if (skipCacheNextRequest) {
                skipCacheNextRequest = false;
                cached = null;
            }
            var now = Date.now();
            if (cached && (now - cached.cachedAt) < CACHE_TTL_MS) {
                setLastUpdatedDisplay(cached.cachedAt);
                if (cached.json.success && cached.json.data) {
                    updateSummaryStats(cached.json);
                }
                callback(cached.json);
                // Background refresh: update cache and redraw so table shows fresh data within 5 min
                setTimeout(function() {
                    $.get(dashboardApiUrl, params).done(function(json) {
                        if (json && json.success) {
                            setCachedTableResponse(cacheKey, json);
                            setLastUpdatedDisplay(Date.now());
                            if (approverTable) approverTable.draw(false);
                        }
                    });
                }, 0);
                return;
            }
            $.ajax({
                url: dashboardApiUrl,
                type: 'GET',
                data: params,
                dataType: 'json'
            }).done(function(json) {
                if (json && json.success) {
                    setCachedTableResponse(cacheKey, json);
                    setLastUpdatedDisplay(Date.now());
                    updateSummaryStats(json);
                }
                callback(json || { success: false, data: [] });
            }).fail(function(xhr) {
                callback({ success: false, data: [], recordsTotal: 0, recordsFiltered: 0 });
            });
        },
        dataSrc: function(json) {
            if (json && json.success && json.data) {
                return json.data;
            }
            return [];
        },
        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row, meta) {
                    return meta.row + meta.settings._iDisplayStart + 1;
                }
            },
            {
                data: 'approver_name',
                orderable: false,
                searchable: true,
                render: function(data, type, row) {
                    if (type === 'export' || type === 'print' || type === 'pdf') {
                        var dn = row.division_name || 'N/A';
                        return (row.approver_name || '') + ' | ' + (row.approver_email || '') + ' | ' + dn;
                    }
                    const firstName = row.fname || (row.approver_name || '').split(' ')[0] || 'U';
                    const lastName = row.lname || (row.approver_name || '').split(' ')[1] || '';
        const initials = (firstName[0] + (lastName ? lastName[0] : '')).toUpperCase();
        const colors = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];
        const colorIndex = (firstName.charCodeAt(0) - 65) % colors.length;
        const bgColor = colors[colorIndex >= 0 ? colorIndex : 0];
        
                    let avatarHtml = '';
                    const photoFile = staffPortraitBasename(row.photo);
                    const hasPhoto = photoFile !== '';
                    const nameEsc = escapeHtml(row.approver_name || '');
                    const emailEsc = escapeHtml(row.approver_email || '');
                    const divEsc = escapeHtml(row.division_name || 'N/A');
        
        if (hasPhoto) {
                        const photoUrl = staffPhotoRoute + '?f=' + encodeURIComponent(photoFile);
            avatarHtml = `<div style="position: relative; width: 40px; height: 40px;">
                            <img src="${photoUrl}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 1;" alt="${nameEsc}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'; this.nextElementSibling.style.zIndex='1';" onload="this.nextElementSibling.style.display='none';">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="display: none; width: 40px; height: 40px; background-color: ${bgColor}; font-weight: 600; font-size: 14px; position: absolute; top: 0; left: 0; z-index: 0;">${initials}</div>
            </div>`;
        } else {
                        avatarHtml = `<div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; background-color: ${bgColor}; font-weight: 600; font-size: 14px;">${initials}</div>`;
                    }
                    
                    return `<div class="d-flex align-items-start">
                        <div class="me-2 flex-shrink-0">${avatarHtml}</div>
                        <div class="approver-dashboard-approver-text flex-grow-1 min-w-0">
                            <div class="fw-semibold text-break">${nameEsc}</div>
                            <small class="text-muted text-break d-block">${emailEsc}</small>
                            <div class="mt-1 small text-muted approver-division-line text-break">${divEsc}</div>
                            </div>
                    </div>`;
                }
            },
            {
                data: 'last_approval_date_display',
                orderable: true,
                searchable: false,
                render: function(data, type, row) {
                    if (type === 'sort' || type === 'type') {
                        return row.last_approval_date || '';
                    }
                    return row.last_approval_date_display ? `<span class="text-nowrap">${row.last_approval_date_display}</span>` : '<span class="text-muted">—</span>';
                }
            },
            {
                data: 'roles',
                orderable: false,
                searchable: true,
                render: function(data, type, row) {
                    if (row.roles && row.roles.length > 0) {
                        return row.roles.map(role => `<span class="badge bg-info me-1 mb-1 d-inline-block">${role}</span>`).join('');
                    }
                    return `<span class="badge bg-info">${row.role || 'N/A'}</span>`;
                }
            },
            {
                data: 'pending_counts',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let html = '<div class="d-flex flex-wrap gap-1">';
                    const filterParams = pendingApprovalsFilterParams();
                    if (row.pending_counts.matrix > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Matrix&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Matrix">Matrix: ${row.pending_counts.matrix}</a>`;
                    }
                    if (row.pending_counts.non_travel > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Non-Travel Memo&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Non-Travel Memos">Non-Travel: ${row.pending_counts.non_travel}</a>`;
                    }
                    if (row.pending_counts.single_memos > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Single Memo&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Single Memos">Single: ${row.pending_counts.single_memos}</a>`;
                    }
                    if (row.pending_counts.special > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Special Memo&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Special Memos">Special: ${row.pending_counts.special}</a>`;
                    }
                    if ((row.pending_counts.other_memo || 0) > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Other Memo&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Other Memos">Other memo: ${row.pending_counts.other_memo || 0}</a>`;
                    }
                    if (row.pending_counts.arf > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=ARF&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="ARF Requests">ARF: ${row.pending_counts.arf}</a>`;
                    }
                    if (row.pending_counts.requests_for_service > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Service Request&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Service Requests">Requests: ${row.pending_counts.requests_for_service}</a>`;
                    }
                    if ((row.pending_counts.change_requests || 0) > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Change Request&staff_id=${row.staff_id}${filterParams}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Change Requests">Change: ${row.pending_counts.change_requests || 0}</a>`;
                    }
                    if (row.total_pending === 0) {
                        html += `<span class="badge bg-light text-dark">No pending items</span>`;
                    }
                    html += '</div>';
                    return html;
                }
            },
            {
                data: 'total_pending',
                orderable: true,
                searchable: false,
                render: function(data, type, row) {
                    // Return numeric value for sorting
                    if (type === 'sort' || type === 'type') {
                        return row.total_pending || 0;
                    }
                    return `<span class="badge ${row.total_pending > 0 ? 'bg-danger' : 'bg-success'}">${row.total_pending}</span>`;
                }
            },
            {
                data: 'total_handled',
                orderable: true,
                searchable: false,
                render: function(data, type, row) {
                    // Return numeric value for sorting
                    if (type === 'sort' || type === 'type') {
                        return row.total_handled || 0;
                    }
                    return `<span class="badge bg-primary">${row.total_handled || 0}</span>`;
                }
            },
            {
                data: 'avg_approval_time_display',
                orderable: true,
                searchable: false,
                render: function(data, type, row) {
                    // Return numeric value for sorting (use hours for proper numeric sorting)
                    if (type === 'sort' || type === 'type') {
                        return row.avg_approval_time_hours || 0;
                    }
                    return `<span class="badge bg-info">${row.avg_approval_time_display || 'No data'}</span>`;
                }
            }
        ],
        columnDefs: [
            { targets: 1, className: 'approver-dashboard-approver-col' },
            { targets: 3, className: 'approver-role-cell' }
        ],
        pageLength: 25,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        order: [[7, 'desc']], // Sort by Avg. Time (highest days) descending by default
        language: {
            processing: '<i class="bx bx-loader-alt bx-spin" style="font-size: 2rem;"></i> Loading...'
        },
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fa fa-file-excel me-1"></i>Export to Excel',
                className: 'btn btn-success btn-sm',
                title: 'Approver Report Export',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7], // Export all columns including Last approval date
                    format: {
                        body: function(data, row, column, node) {
                            // Clean HTML from cells for export
                            if (typeof data === 'string') {
                                // Remove HTML tags and get text content
                                var tmp = document.createElement('DIV');
                                tmp.innerHTML = data;
                                return tmp.textContent || tmp.innerText || '';
                            }
                            return data;
                        }
                    }
                },
                action: function(e, dt, button, config) {
                    // Server-side export with current filters and sort
                    const filters = {
                        q: $('#searchApprover').val(),
                        division_id: $('#filterDivision').val(),
                        doc_type: $('#filterDocType').val(),
                        approval_level: $('#filterApprovalLevel').val(),
                        month: $('#filterMonth').val(),
                        year: $('#filterYear').val(),
                        export: 1,
                        format: 'csv',
                        per_page: 10000,
                        page: 1
                    };
                    const order = dt.order();
                    if (order.length > 0) filters.order = JSON.stringify(order);
                    const params = new URLSearchParams();
                    Object.keys(filters).forEach(key => {
                        if (filters[key] !== '' && filters[key] !== null && filters[key] !== undefined) {
                            params.append(key, filters[key]);
                        }
                    });
                    window.open('{{ route("approver-dashboard.api") }}?' + params.toString(), '_blank');
                }
            }
        ]
    });
    
    // Hide the default buttons container (we're using our custom button)
    approverTable.buttons().container().hide();
}


function updateSummaryStats(response) {
    var sc = response && response.summary_cards ? response.summary_cards : null;
    var tr = sc && sc.total_approval_requests != null ? sc.total_approval_requests : 0;
    var tp = sc && sc.total_pending != null ? sc.total_pending : 0;
    var ta = sc && sc.total_approved != null ? sc.total_approved : 0;
    var tret = sc && sc.total_returned != null ? sc.total_returned : 0;
    $('#totalApprovalRequests').text(tr);
    $('#totalPending').text(tp);
    $('#totalApproved').text(ta);
    $('#totalReturned').text(tret);
}

function refreshDashboard() {
    skipCacheNextRequest = true;
    if (approverTable) {
        approverTable.draw(false);
    }
    loadWorkflowStats();
    showSuccess('Dashboard refreshed successfully');
}

function exportData(format) {
    format = format || 'pdf';
    const params = new URLSearchParams();
    params.set('export', '1');
    if (format === 'csv') {
        params.set('format', 'csv');
        params.set('per_page', '10000');
    }
    // Apply current dashboard filters
    const q = $('#searchApprover').val();
    if (q) params.set('q', q);
    const divisionId = $('#filterDivision').val();
    if (divisionId) params.set('division_id', divisionId);
    const docType = $('#filterDocType').val();
    if (docType) params.set('doc_type', docType);
    const approvalLevel = $('#filterApprovalLevel').val();
    if (approvalLevel) params.set('approval_level', approvalLevel);
    const month = $('#filterMonth').val();
    if (month) params.set('month', month);
    const year = $('#filterYear').val();
    if (year) params.set('year', year);
    window.open(`{{ route('approver-dashboard.api') }}?${params.toString()}`, '_blank');
}

function viewApproverDetails(approverId) {
    // Implement approver details view
    showInfo('Approver details view - Coming soon');
}

function showError(message) {
    // Show error message
    console.error(message);
}

function showSuccess(message) {
    // Show success message
    console.log(message);
}

function showInfo(message) {
    // Show info message
    console.log(message);
}
})();
</script>
@endpush