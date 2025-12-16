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

  /* Enhanced Stats Styling */
  .stats-container {
    background: white;
    box-shadow: var(--shadow-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid var(--medium-grey);
  }

  .stat-item {
    text-align: center;
    padding: 1rem 0.75rem;
    background: white;
    box-shadow: var(--shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    border: 1px solid var(--medium-grey);
  }

  .stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
  }

  .stat-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
  }

  .stat-item.total {
    --stat-color: rgba(23, 162, 184, 0.7);
    --stat-color-light: rgba(32, 201, 151, 0.4);
  }

  .stat-item.pending {
    --stat-color: rgba(255, 193, 7, 0.7);
    --stat-color-light: rgba(255, 237, 78, 0.4);
  }

  .stat-item.workflow {
    --stat-color: rgba(111, 66, 193, 0.7);
    --stat-color-light: rgba(142, 68, 173, 0.4);
  }

  .stat-item.updated {
    --stat-color: rgba(17, 154, 72, 0.7);
    --stat-color-light: rgba(52, 206, 87, 0.4);
  }

  .stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--stat-color);
    display: block;
    margin-bottom: 0.4rem;
    animation: countUp 1s ease-out;
  }

  .stat-label {
    font-size: 0.85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    margin-bottom: 0.2rem;
  }

  .stat-icon {
    font-size: 1.3rem;
    color: var(--stat-color);
    margin-bottom: 0.4rem;
    display: inline-block;
    padding: 0.5rem;
    border-radius: 8px;
    animation: pulse 2s infinite;
  }

  .stat-item.total .stat-icon {
    background: rgba(23, 162, 184, 0.1);
  }

  .stat-item.pending .stat-icon {
    background: rgba(255, 193, 7, 0.1);
  }

  .stat-item.workflow .stat-icon {
    background: rgba(111, 66, 193, 0.1);
  }

  .stat-item.updated .stat-icon {
    background: rgba(17, 154, 72, 0.1);
  }

  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }

  @keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
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
    background: var(--accent-black);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
    padding: 1rem 0.75rem;
    border: none;
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
  <!-- Approver Statistics -->
  <div class="stats-container">
    <h5 class="mb-4 fw-bold text-center">
      <i class="fa fa-chart-bar me-2"></i>Approver Dashboard Overview
    </h5>
    <div class="row g-3">
      <div class="col-md-3">
        <div class="stat-item total">
          <i class="fa fa-users stat-icon"></i>
          <span class="stat-number" id="totalApprovers">0</span>
          <span class="stat-label">Total Approvers</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item pending">
          <i class="fa fa-clock stat-icon"></i>
          <span class="stat-number" id="totalPending">0</span>
          <span class="stat-label">Total Pending</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item workflow">
          <i class="fa fa-cogs stat-icon"></i>
          <span class="stat-number" id="activeWorkflow">-</span>
          <span class="stat-label">Active Workflows</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item updated">
          <i class="fa fa-clock stat-icon"></i>
          <span class="stat-number" id="lastUpdated">-</span>
          <span class="stat-label">Last Updated</span>
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
        <div class="col-md-4">
          <label class="form-label fw-semibold">
            <i class="fa fa-search me-1"></i>Search Approver
          </label>
          <input type="text" id="searchApprover" class="form-control" placeholder="Search by name, email, or role...">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-building me-1"></i>Division
          </label>
          <select id="filterDivision" class="form-select">
            <option value="">All Divisions</option>
          </select>
        </div>
        <div class="col-md-3">
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
      </div>
    </div>
  </div>

    <!-- Approver Dashboard Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bx bx-table me-2 text-primary"></i>Approver Dashboard</h6>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="perPage" style="width: auto;">
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="approverTable">
                        <thead class="table-light">
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
                
                <!-- Pagination -->
                <div class="card-footer" id="paginationContainer">
                    <!-- Pagination will be inserted here -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let currentFilters = {};
let filterOptions = {};
        let userDivisionId = {{ $userDivisionId ?? 'null' }};
        let hasPermission88 = {{ $hasPermission88 ? 'true' : 'false' }};
        
        // Debug session data
        console.log('User Division ID:', userDivisionId);
        console.log('Has Permission 88:', hasPermission88);

$(document).ready(function() {
    loadFilterOptions();
    loadDashboardData();
    
    // Initialize date pickers
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });
    
    // Set up event listeners
    $('#perPage').on('change', function() {
        currentPage = 1;
        loadDashboardData();
    });
    
    // Auto-submit filters on change
    let filterTimeout;
    $('#searchApprover, #filterDivision, #filterDocType, #filterApprovalLevel').on('change keyup', function() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            currentPage = 1;
            loadDashboardData();
        }, 500);
    });
    
    // Auto-refresh every 5 minutes
    setInterval(function() {
        loadDashboardData();
    }, 300000);
});

function loadFilterOptions() {
    $.ajax({
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
}

function loadDashboardData() {
    const params = {
        page: currentPage,
        per_page: $('#perPage').val(),
        q: $('#searchApprover').val(),
        division_id: $('#filterDivision').val(),
        doc_type: $('#filterDocType').val(),
        approval_level: $('#filterApprovalLevel').val(),
        ...currentFilters
    };
    
    $.ajax({
        url: '{{ route("approver-dashboard.api") }}',
        type: 'GET',
        data: params,
        success: function(response) {
            console.log('API Response:', response);
            if (response.success) {
                console.log('Data received:', response.data);
                // Debug: Check first approver's photo data
                if (response.data && response.data.length > 0) {
                    console.log('First approver photo:', response.data[0].photo);
                    console.log('First approver data:', response.data[0]);
                }
                updateTable(response.data, response.pagination);
                updatePagination(response.pagination);
                updateSummaryStats(response);
            } else {
                showError('Failed to load dashboard data: ' + response.message);
            }
        },
        error: function() {
            showError('Failed to load dashboard data');
        }
    });
}

function updateTable(data, pagination) {
    console.log('updateTable called with data:', data);
    const tbody = $('#approverTableBody');
    tbody.empty();
    
    if (data.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="bx bx-info-circle text-muted" style="font-size: 2rem;"></i>
                    <p class="mt-2 text-muted">No approvers found</p>
                </td>
            </tr>
        `);
        return;
    }
    
    const baseUrl = '{{ user_session("base_url") ?? url("/") }}';
    // Get the base URL for pending approvals using route helper
    const pendingApprovalsBaseUrl = '{{ route("pending-approvals.index") }}';
    const currentPage = pagination ? pagination.current_page : 1;
    const perPage = pagination ? pagination.per_page : 25;
    
    data.forEach(function(approver, index) {
        // Calculate row number based on current page and per page
        const rowNumber = (currentPage - 1) * perPage + index + 1;
        
        // Get photo URL or generate avatar
        let avatarHtml = '';
        // Generate initials for fallback (used in both cases)
        const firstName = approver.fname || approver.approver_name.split(' ')[0] || 'U';
        const lastName = approver.lname || approver.approver_name.split(' ')[1] || '';
        const initials = (firstName[0] + (lastName ? lastName[0] : '')).toUpperCase();
        const colors = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];
        const colorIndex = (firstName.charCodeAt(0) - 65) % colors.length;
        const bgColor = colors[colorIndex >= 0 ? colorIndex : 0];
        
        // Check if photo exists and is not empty/null
        const hasPhoto = approver.photo && approver.photo !== null && approver.photo !== '' && approver.photo.trim() !== '';
        
        if (hasPhoto) {
            // Ensure baseUrl doesn't have trailing slash and photo path is correct
            const cleanBaseUrl = baseUrl.replace(/\/$/, '');
            const photoUrl = cleanBaseUrl + '/uploads/staff/' + approver.photo;
            
            avatarHtml = `<div style="position: relative; width: 40px; height: 40px;">
                <img src="${photoUrl}" 
                    class="rounded-circle" 
                    style="width: 40px; height: 40px; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 1;" 
                    alt="${approver.approver_name}"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'; this.nextElementSibling.style.zIndex='1';"
                    onload="this.nextElementSibling.style.display='none';">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white" 
                    style="display: none; width: 40px; height: 40px; background-color: ${bgColor}; font-weight: 600; font-size: 14px; position: absolute; top: 0; left: 0; z-index: 0;">
                    ${initials}
                </div>
            </div>`;
        } else {
            // Generate initials avatar (no photo available)
            avatarHtml = `<div class="rounded-circle d-flex align-items-center justify-content-center text-white" 
                style="width: 40px; height: 40px; background-color: ${bgColor}; font-weight: 600; font-size: 14px;">
                ${initials}
            </div>`;
        }
        
        const row = `
            <tr>
                <td class="text-center">${rowNumber}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="me-2">
                            ${avatarHtml}
                        </div>
                        <div>
                            <div class="fw-semibold">${approver.approver_name}</div>
                            <small class="text-muted">${approver.approver_email}</small>
                            <div class="mt-1">
                                <small class="text-muted">${approver.division_name || 'N/A'}</small>
                            </div>
                        </div>
                    </div>
                </td>
                <td style="width: 20%;">
                    <div>
                        ${approver.roles && approver.roles.length > 0 ? 
                            approver.roles.map(role => `<span class="badge bg-info me-1 mb-1 d-inline-block">${role}</span>`).join('') :
                            `<span class="badge bg-info">${approver.role || 'N/A'}</span>`
                        }
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        ${approver.pending_counts.matrix > 0 ? 
                            `<a href="${pendingApprovalsBaseUrl}/pending-approvals?category=Matrix" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Matrix">Matrix: ${approver.pending_counts.matrix}</a>` : 
                            ''
                        }
                        ${approver.pending_counts.non_travel > 0 ? 
                            `<a href="${pendingApprovalsBaseUrl}/pending-approvals?category=Non-Travel Memo" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Non-Travel Memos">Non-Travel: ${approver.pending_counts.non_travel}</a>` : 
                            ''
                        }
                        ${approver.pending_counts.single_memos > 0 ? 
                            `<a href="${pendingApprovalsBaseUrl}/pending-approvals?category=Single Memo" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Single Memos">Single: ${approver.pending_counts.single_memos}</a>` : 
                            ''
                        }
                        ${approver.pending_counts.special > 0 ? 
                            `<a href="${pendingApprovalsBaseUrl}/pending-approvals?category=Special Memo" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Special Memos">Special: ${approver.pending_counts.special}</a>` : 
                            ''
                        }
                        ${approver.pending_counts.arf > 0 ? 
                            `<a href="${pendingApprovalsBaseUrl}/pending-approvals?category=ARF" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="ARF Requests">ARF: ${approver.pending_counts.arf}</a>` : 
                            ''
                        }
                        ${approver.pending_counts.requests_for_service > 0 ? 
                            `<a href="${pendingApprovalsBaseUrl}/pending-approvals?category=Service Request" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Service Requests">Requests: ${approver.pending_counts.requests_for_service}</a>` : 
                            ''
                        }
                        ${(approver.pending_counts.change_requests || 0) > 0 ? 
                            `<a href="${pendingApprovalsBaseUrl}/pending-approvals?category=Change Request" class="badge bg-warning text-decoration-none" style="cursor: pointer;" title="Change Requests">Change: ${approver.pending_counts.change_requests || 0}</a>` : 
                            ''
                        }
                        ${approver.total_pending === 0 ? 
                            `<span class="badge bg-light text-dark">No pending items</span>` : 
                            ''
                        }
                    </div>
                </td>
                <td><span class="badge ${approver.total_pending > 0 ? 'bg-danger' : 'bg-success'}">${approver.total_pending}</span></td>
                <td><span class="badge bg-primary">${approver.total_handled || 0}</span></td>
                <td><span class="badge bg-info">${approver.avg_approval_time_display || 'No data'}</span></td>
            </tr>
        `;
        tbody.append(row);
    });
}

function updatePagination(pagination) {
    const container = $('#paginationContainer');
    container.empty();
    
    if (pagination.last_page <= 1) return;
    
    let paginationHtml = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';
    
    // Previous button
    if (pagination.current_page > 1) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); changePage(${pagination.current_page - 1}); return false;">Previous</a></li>`;
    }
    
    // Show page numbers with ellipsis for large page counts
    const maxVisiblePages = 10;
    let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(pagination.last_page, startPage + maxVisiblePages - 1);
    
    // Adjust start if we're near the end
    if (endPage - startPage < maxVisiblePages - 1) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    // First page
    if (startPage > 1) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); changePage(1); return false;">1</a></li>`;
        if (startPage > 2) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Page numbers
    for (let i = startPage; i <= endPage; i++) {
        if (i === pagination.current_page) {
            paginationHtml += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); changePage(${i}); return false;">${i}</a></li>`;
        }
    }
    
    // Last page
    if (endPage < pagination.last_page) {
        if (endPage < pagination.last_page - 1) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); changePage(${pagination.last_page}); return false;">${pagination.last_page}</a></li>`;
    }
    
    // Next button
    if (pagination.current_page < pagination.last_page) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="event.preventDefault(); changePage(${pagination.current_page + 1}); return false;">Next</a></li>`;
    }
    
    paginationHtml += '</ul></nav>';
    container.html(paginationHtml);
}

function updateSummaryStats(response) {
    // Update summary statistics
    $('#totalApprovers').text(response.pagination.total);
    $('#totalPending').text(response.data.reduce((sum, approver) => sum + approver.total_pending, 0));
    $('#activeWorkflow').text(response.total_workflows || 0);
    $('#lastUpdated').text(new Date().toLocaleTimeString());
}

function changePage(page) {
    currentPage = page;
    loadDashboardData();
}

function applyFilters() {
    currentPage = 1;
    loadDashboardData();
}

function clearFilters() {
    $('#searchApprover').val('');
    $('#filterDivision').val('');
    $('#filterDocType').val('');
    $('#filterApprovalLevel').val('');
    
    // Reset to user's division if no permission 88
    if (!hasPermission88 && userDivisionId) {
        $('#filterDivision').val(userDivisionId);
    }
    
    currentPage = 1;
    loadDashboardData();
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