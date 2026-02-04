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
    <button type="button" class="btn btn-success btn-modern" onclick="exportData()">
        <i class="fa fa-download"></i> Export
    </button>
</div>
{{-- @dd(user_session()) --}}
@endsection

@section('content')
<div class="container-fluid">
  <!-- Approver Statistics (quality cards, workplan-style) -->
  <div class="stats-container">
    <h5 class="mb-4 fw-bold text-center">
      <i class="fa fa-chart-bar me-2"></i>Approver Dashboard Overview
    </h5>
    <div class="row g-3">
      <div class="col-md-3">
        <div class="stat-item total">
          <div class="stat-icon-wrap"><i class="fa fa-users stat-icon"></i></div>
          <span class="stat-number" id="totalApprovers">0</span>
          <span class="stat-label">Total Approvers</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item pending">
          <div class="stat-icon-wrap"><i class="fa fa-clock stat-icon"></i></div>
          <span class="stat-number" id="totalPending">0</span>
          <span class="stat-label">Total Pending</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item workflow">
          <div class="stat-icon-wrap"><i class="fa fa-cogs stat-icon"></i></div>
          <span class="stat-number" id="activeWorkflow">-</span>
          <span class="stat-label">Active Workflows</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item updated">
          <div class="stat-icon-wrap"><i class="fa fa-sync-alt stat-icon"></i></div>
          <span class="stat-number" id="lastUpdated">-</span>
          <span class="stat-label">Last Updated</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Average Time to Last Approver by Workflow (approved documents only) -->
  <div class="card filter-card mb-4">
    <div class="card-header">
      <h5 class="mb-0 text-dark">
        <i class="fa fa-chart-bar me-2"></i>Average Time to Last Approver by Workflow
      </h5>
      <p class="mb-0 mt-1 small text-muted">Approved documents only. Time from submission to when the final approver approved.</p>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-lg-5">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="workflowStatsTable">
              <thead>
                <tr>
                  <th>Workflow Name</th>
                  <th class="text-end">Approved Docs</th>
                  <th class="text-end">Avg. Time to Last Approver</th>
                </tr>
              </thead>
              <tbody id="workflowStatsBody">
                <tr><td colspan="3" class="text-center text-muted">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="col-lg-7">
          <div id="workflowAvgTimeChart" style="min-height: 350px;"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Enhanced Filters -->
  <div class="card filter-card">
    <div class="card-header">
      <h5 class="mb-0 text-dark">
        <i class="fa fa-filter me-2"></i>Filter Approvers
      </h5>
    </div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-search me-1"></i>Search Approver
          </label>
          <input type="text" id="searchApprover" class="form-control" placeholder="Search...">
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">
            <i class="fa fa-building me-1"></i>Division
          </label>
          <select id="filterDivision" class="form-select">
            <option value="">All Divisions</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">
            <i class="fa fa-file-alt me-1"></i>Document Type
          </label>
          <select id="filterDocType" class="form-select">
            <option value="">All Types</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label fw-semibold">
            <i class="fa fa-cogs me-1"></i>Approval Level
          </label>
          <select id="filterApprovalLevel" class="form-select">
            <option value="">All Levels</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold">
            <i class="fa fa-calendar-alt me-1"></i>Month
          </label>
          <select id="filterMonth" class="form-select">
            <option value="">All</option>
            <option value="1">January</option>
            <option value="2">February</option>
            <option value="3">March</option>
            <option value="4">April</option>
            <option value="5">May</option>
            <option value="6">June</option>
            <option value="7">July</option>
            <option value="8">August</option>
            <option value="9">September</option>
            <option value="10">October</option>
            <option value="11">November</option>
            <option value="12">December</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold">
            <i class="fa fa-calendar me-1"></i>Year
          </label>
          <select id="filterYear" class="form-select">
            <option value="">All Years</option>
          </select>
        </div>
        <div class="col-md-1">
          <label class="form-label fw-semibold d-block">&nbsp;</label>
          <button type="button" class="btn btn-outline-secondary w-100" id="clearFilters">
            Clear
          </button>
        </div>
      </div>
    </div>
  </div>

    <!-- Approver Dashboard Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bx bx-table me-2 text-primary"></i>Approver Dashboard</h6>
                <button type="button" class="btn btn-success btn-sm" id="exportExcel">
                    <i class="fa fa-file-excel me-1"></i>Export to Excel
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="approverTable">
                        <thead>
                            <tr>
                                <th style="width: 30px;">#</th>
                                <th>Approver</th>
                                <th style="width: 15%;">Role</th>
                                <th>Pending Items</th>
                                <th>Total Pending</th>
                                <th>Total Handled</th>
                                <th>Avg. Time</th>
                            </tr>
                        </thead>
                        <tbody id="approverTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="bx bx-loader-alt bx-spin" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Loading approver data...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let approverTable;
let filterOptions = {};
        let userDivisionId = {{ $userDivisionId ?? 'null' }};
        let hasPermission88 = {{ $hasPermission88 ? 'true' : 'false' }};
const baseUrl = '{{ user_session("base_url") ?? url("/") }}';
const pendingApprovalsBaseUrl = '{{ route("pending-approvals.index") }}';

$(document).ready(function() {
    // Load filter options first, then initialize DataTable
    loadFilterOptions().then(function() {
        // Initialize DataTable after filters are populated
        initializeDataTable();
    });
    
    // Auto-submit filters on change
    let filterTimeout;
    $('#searchApprover').on('keyup', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            if (approverTable) {
                approverTable.draw();
            }
        }, 500);
    });
    
    $('#filterDivision, #filterDocType, #filterApprovalLevel, #filterMonth, #filterYear').on('change', function() {
        if (approverTable) {
            approverTable.draw();
        }
        loadWorkflowStats();
    });
    
    // Clear filters button
    $('#clearFilters').on('click', function() {
        $('#searchApprover').val('');
        $('#filterDivision').val('');
        $('#filterDocType').val('');
        $('#filterApprovalLevel').val('');
        $('#filterMonth').val('');
        $('#filterYear').val('');
        
        // Reset to user's division if no permission 88
        if (!hasPermission88 && userDivisionId) {
            $('#filterDivision').val(userDivisionId);
        }
        
        // Reset year to current year
        const currentYear = new Date().getFullYear();
        $('#filterYear').val(currentYear);
        
        if (approverTable) {
            approverTable.draw();
        }
        loadWorkflowStats();
    });
    
    // Export to Excel - trigger DataTables button
    $('#exportExcel').on('click', function() {
        if (approverTable) {
            approverTable.button('.buttons-excel').trigger();
        }
    });
});

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
        success: function(response) {
            if (response.success && response.data && Array.isArray(response.data)) {
                renderWorkflowStats(response.data);
            } else {
                $('#workflowStatsBody').html('<tr><td colspan="3" class="text-center text-muted">No data</td></tr>');
                if (typeof Highcharts !== 'undefined' && Highcharts.charts) {
                    const chartEl = document.getElementById('workflowAvgTimeChart');
                    if (chartEl && chartEl.__chart) {
                        try { chartEl.__chart.destroy(); } catch (e) {}
                    }
                }
            }
        },
        error: function() {
            $('#workflowStatsBody').html('<tr><td colspan="3" class="text-center text-danger">Error loading workflow stats</td></tr>');
        }
    });
}

function renderWorkflowStats(stats) {
    const tbody = $('#workflowStatsBody');
    tbody.empty();
    if (!stats || stats.length === 0) {
        tbody.html('<tr><td colspan="3" class="text-center text-muted">No workflow data</td></tr>');
        return;
    }
    stats.forEach(function(row) {
        const docTypes = (row.doc_type_labels && row.doc_type_labels.length)
            ? row.doc_type_labels.join(', ')
            : '';
        const docTypesHtml = docTypes
            ? `<div class="small text-muted mt-1">${escapeHtml(docTypes)}</div>`
            : '';
        tbody.append(`<tr>
            <td><div>${escapeHtml(row.workflow_name || '-')}</div>${docTypesHtml}</td>
            <td class="text-end">${row.memos != null ? row.memos : 0}</td>
            <td class="text-end">${escapeHtml(row.avg_display || 'No data')}</td>
        </tr>`);
    });

    // Column chart: workflow name (x), average time to last approver in hours (y)
    const categories = stats.map(function(s) { return s.workflow_name || 'Unknown'; });
    const seriesData = stats.map(function(s) { return Math.round((s.avg_hours || 0) * 10) / 10; });
    const maxHours = seriesData.length ? Math.max.apply(null, seriesData) : 0;
    const yMax = maxHours > 0 ? Math.ceil(maxHours * 1.15) : 10;

    if (typeof Highcharts !== 'undefined') {
        const chartEl = document.getElementById('workflowAvgTimeChart');
        if (chartEl && chartEl.__chart) {
            try { chartEl.__chart.destroy(); chartEl.__chart = null; } catch (e) {}
        }
        const chart = Highcharts.chart('workflowAvgTimeChart', {
            chart: { type: 'column', height: 350 },
            title: { text: 'Average Time to Last Approver (approved documents only)' },
            subtitle: { text: 'Time from submission to final approval, in hours.' },
            xAxis: { categories: categories, title: { text: 'Workflow' }, crosshair: true, labels: { rotation: -45 } },
            yAxis: {
                min: 0,
                max: yMax,
                title: { text: 'Time to last approver (hours)' },
                allowDecimals: true
            },
            tooltip: {
                headerFormat: '<b>{point.x}</b><br/>',
                pointFormat: 'Avg. time to last approver: {point.y} hrs'
            },
            plotOptions: {
                column: {
                    color: 'var(--primary-color, #119a48)',
                    borderRadius: 4,
                    dataLabels: { enabled: true, format: '{y} hrs' }
                }
            },
            series: [{ name: 'Avg. time to last approver (hrs)', data: seriesData }],
            credits: { enabled: false }
        });
        if (chartEl) chartEl.__chart = chart;
    }
}

function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function initializeDataTable() {
    approverTable = $('#approverTable').DataTable({
        processing: true,
        serverSide: true,
        searching: false, // Disable DataTables search box
        ordering: true,
        order: [[6, 'desc']], // Sort by Avg. Time (highest days) descending by default
        ajax: {
        url: '{{ route("approver-dashboard.api") }}',
        type: 'GET',
            data: function(d) {
                // Add custom filters
                d.q = $('#searchApprover').val();
                d.division_id = $('#filterDivision').val() || null;
                d.doc_type = $('#filterDocType').val() || null;
                d.approval_level = $('#filterApprovalLevel').val() || null;
                const monthValue = $('#filterMonth').val();
                d.month = monthValue ? parseInt(monthValue) : null;
                const yearValue = $('#filterYear').val();
                d.year = yearValue ? parseInt(yearValue) : null;
                // Convert DataTables parameters to our API format
                d.page = Math.floor(d.start / d.length) + 1;
                d.per_page = d.length;
                // Keep order parameter for server-side sorting
                // Remove DataTables default params we don't need
                delete d.start;
                delete d.draw;
            },
            dataSrc: function(json) {
                // Update summary stats
                if (json.success && json.data) {
                    updateSummaryStats(json);
                    return json.data;
                }
                return [];
            }
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
                    const firstName = row.fname || row.approver_name.split(' ')[0] || 'U';
                    const lastName = row.lname || row.approver_name.split(' ')[1] || '';
        const initials = (firstName[0] + (lastName ? lastName[0] : '')).toUpperCase();
        const colors = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];
        const colorIndex = (firstName.charCodeAt(0) - 65) % colors.length;
        const bgColor = colors[colorIndex >= 0 ? colorIndex : 0];
        
                    let avatarHtml = '';
                    const hasPhoto = row.photo && row.photo !== null && row.photo !== '' && row.photo.trim() !== '';
        
        if (hasPhoto) {
            const cleanBaseUrl = baseUrl.replace(/\/$/, '');
                        const photoUrl = cleanBaseUrl + '/uploads/staff/' + row.photo;
            avatarHtml = `<div style="position: relative; width: 40px; height: 40px;">
                            <img src="${photoUrl}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 1;" alt="${row.approver_name}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'; this.nextElementSibling.style.zIndex='1';" onload="this.nextElementSibling.style.display='none';">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="display: none; width: 40px; height: 40px; background-color: ${bgColor}; font-weight: 600; font-size: 14px; position: absolute; top: 0; left: 0; z-index: 0;">${initials}</div>
            </div>`;
        } else {
                        avatarHtml = `<div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 40px; height: 40px; background-color: ${bgColor}; font-weight: 600; font-size: 14px;">${initials}</div>`;
                    }
                    
                    return `<div class="d-flex align-items-center">
                        <div class="me-2">${avatarHtml}</div>
                        <div>
                            <div class="fw-semibold">${row.approver_name}</div>
                            <small class="text-muted">${row.approver_email}</small>
                            <div class="mt-1"><small class="text-muted">${row.division_name || 'N/A'}</small></div>
                            </div>
                    </div>`;
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
                    if (row.pending_counts.matrix > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Matrix&staff_id=${row.staff_id}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Matrix">Matrix: ${row.pending_counts.matrix}</a>`;
                    }
                    if (row.pending_counts.non_travel > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Non-Travel Memo&staff_id=${row.staff_id}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Non-Travel Memos">Non-Travel: ${row.pending_counts.non_travel}</a>`;
                    }
                    if (row.pending_counts.single_memos > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Single Memo&staff_id=${row.staff_id}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Single Memos">Single: ${row.pending_counts.single_memos}</a>`;
                    }
                    if (row.pending_counts.special > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Special Memo&staff_id=${row.staff_id}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Special Memos">Special: ${row.pending_counts.special}</a>`;
                    }
                    if (row.pending_counts.arf > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=ARF&staff_id=${row.staff_id}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="ARF Requests">ARF: ${row.pending_counts.arf}</a>`;
                    }
                    if (row.pending_counts.requests_for_service > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Service Request&staff_id=${row.staff_id}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Service Requests">Requests: ${row.pending_counts.requests_for_service}</a>`;
                    }
                    if ((row.pending_counts.change_requests || 0) > 0) {
                        html += `<a href="${pendingApprovalsBaseUrl}?category=Change Request&staff_id=${row.staff_id}" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Change Requests">Change: ${row.pending_counts.change_requests || 0}</a>`;
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
        pageLength: 25,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        order: [[6, 'desc']], // Sort by Avg. Time (highest days) descending by default
        language: {
            processing: '<i class="bx bx-loader-alt bx-spin" style="font-size: 2rem;"></i> Loading...'
        },
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fa fa-file-excel me-1"></i>Export to Excel',
                className: 'btn btn-success btn-sm',
                title: 'Approver Dashboard Export',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6], // Export all columns
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
                    // For server-side processing, we need to fetch all data
                    // Get current filters and sort order
                    const filters = {
                        q: $('#searchApprover').val(),
                        division_id: $('#filterDivision').val(),
                        doc_type: $('#filterDocType').val(),
                        approval_level: $('#filterApprovalLevel').val(),
                        month: $('#filterMonth').val(),
                        year: $('#filterYear').val(),
                        export: 1,
                        per_page: 10000, // Get all records
                        page: 1
                    };
                    
                    // Get current sort order
                    const order = dt.order();
                    if (order.length > 0) {
                        filters.order = JSON.stringify(order);
                    }
                    
                    // Build URL with filters
                    const params = new URLSearchParams();
                    Object.keys(filters).forEach(key => {
                        if (filters[key] !== '' && filters[key] !== null && filters[key] !== undefined) {
                            params.append(key, filters[key]);
                        }
                    });
                    
                    // Open export URL in new window
                    window.open('{{ route("approver-dashboard.api") }}?' + params.toString(), '_blank');
                }
            }
        ]
    });
    
    // Hide the default buttons container (we're using our custom button)
    approverTable.buttons().container().hide();
}


function updateSummaryStats(response) {
    // Update summary statistics
    if (response.pagination) {
    $('#totalApprovers').text(response.pagination.total);
    }
    if (response.data && response.data.length > 0) {
        $('#totalPending').text(response.data.reduce((sum, approver) => sum + (approver.total_pending || 0), 0));
    }
    if (response.total_workflows !== undefined) {
    $('#activeWorkflow').text(response.total_workflows || 0);
    }
    $('#lastUpdated').text(new Date().toLocaleTimeString());
}

function refreshDashboard() {
    loadDashboardData();
    showSuccess('Dashboard refreshed successfully');
}

function exportData() {
    const params = new URLSearchParams(currentFilters);
    window.open(`{{ route('approver-dashboard.api') }}?export=1&${params.toString()}`, '_blank');
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
</script>
@endpush