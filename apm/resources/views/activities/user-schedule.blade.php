@extends('layouts.app')

@section('title', 'My Activity Schedule')
@section('header', 'My Activity Schedule')

@section('header-actions')
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
            <i class="bx bx-arrow-back"></i> Back
        </button>
        <button type="button" class="btn btn-success" onclick="refreshSchedule()">
            <i class="bx bx-refresh"></i> Refresh
        </button>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <!-- Schedule Overview Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-primary mb-1" id="totalActivities">-</h4>
                            <small class="text-muted">Total Activities</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-success mb-1" id="totalDays">-</h4>
                            <small class="text-muted">Total Days</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-info mb-1" id="currentPage">-</h4>
                            <small class="text-muted">Current Page</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div>
                            <h4 class="text-warning mb-1" id="totalPages">-</h4>
                            <small class="text-muted">Total Pages</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Table Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-success fw-bold">
                    <i class="bx bx-calendar me-2"></i> My Activity Schedule
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted small">Showing <span id="showingFrom">-</span> to <span id="showingTo">-</span> of <span id="showingTotal">-</span> activities</span>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Loading State -->
                <div id="loadingState" class="text-center py-5">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading your schedule...</p>
                </div>

                <!-- Schedule Table -->
                <div id="scheduleTable" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-success">
                                <tr>
                                    <th>#</th>
                                    <th>Activity Title</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                    <th>Division</th>
                                    <th>Matrix Period</th>
                                    <th>Travel Type</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="scheduleTableBody">
                                <!-- Schedule data will be populated here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center p-3 border-top">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small">Page <span id="currentPageDisplay">-</span> of <span id="lastPageDisplay">-</span></span>
                        </div>
                        <nav aria-label="Schedule pagination">
                            <ul class="pagination pagination-sm mb-0" id="paginationContainer">
                                <!-- Pagination will be populated here -->
                            </ul>
                        </nav>
                    </div>
                </div>

                <!-- Empty State -->
                <div id="emptyState" class="text-center py-5 d-none">
                    <i class="bx bx-calendar-x fs-1 text-muted opacity-50"></i>
                    <h5 class="text-muted mt-3">No Activities Found</h5>
                    <p class="text-muted mb-0">You don't have any scheduled activities at the moment.</p>
                </div>

                <!-- Error State -->
                <div id="errorState" class="text-center py-5 d-none">
                    <i class="bx bx-error-circle fs-1 text-danger opacity-50"></i>
                    <h5 class="text-danger mt-3">Error Loading Schedule</h5>
                    <p class="text-muted mb-0" id="errorMessage">An error occurred while loading your schedule.</p>
                    <button type="button" class="btn btn-outline-primary mt-3" onclick="loadSchedule()">
                        <i class="bx bx-refresh"></i> Try Again
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.table th {
    font-weight: 600;
    background-color: #f8f9fa;
}

.border-end {
    border-right: 1px solid #dee2e6 !important;
}

.pagination .page-link {
    color: #198754;
    border-color: #dee2e6;
}

.pagination .page-item.active .page-link {
    background-color: #198754;
    border-color: #198754;
}

.pagination .page-link:hover {
    color: #146c43;
    background-color: #e9ecef;
}


</style>
@endpush

@push('scripts')
<script>
let currentPage = 1;
let lastPage = 1;
let totalActivities = 0;
let totalDays = 0;

$(document).ready(function() {
    loadSchedule();
});

function loadSchedule(page = 1) {
    currentPage = page;
    
    // Show loading state
    $('#loadingState').removeClass('d-none');
    $('#scheduleTable').addClass('d-none');
    $('#emptyState').addClass('d-none');
    $('#errorState').addClass('d-none');

    // Make AJAX request
    $.ajax({
        url: '{{ route("activities.user-schedule.data") }}',
        method: 'GET',
        data: { page: page },
        success: function(response) {
            if (response.success) {
                displaySchedule(response.data, response.pagination);
            } else {
                showError('Failed to load schedule data');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading schedule:', error);
            showError('Failed to load schedule. Please try again.');
        }
    });
}

function displaySchedule(data, pagination) {
    // Hide loading state
    $('#loadingState').addClass('d-none');
    
    if (!data || data.length === 0) {
        $('#emptyState').removeClass('d-none');
        return;
    }

    // Update overview statistics
    updateOverviewStats(data, pagination);
    
    // Populate table
    populateScheduleTable(data);
    
    // Update pagination
    updatePagination(pagination);
    
    // Show table
    $('#scheduleTable').removeClass('d-none');
}

function updateOverviewStats(data, pagination) {
    totalActivities = pagination.total;
    totalDays = data.reduce((sum, item) => sum + (parseInt(item.days) || 0), 0);
    
    $('#totalActivities').text(totalActivities);
    $('#totalDays').text(totalDays);
    $('#currentPage').text(pagination.current_page);
    $('#totalPages').text(pagination.last_page);
    
    $('#showingFrom').text(pagination.from || 0);
    $('#showingTo').text(pagination.to || 0);
    $('#showingTotal').text(pagination.total || 0);
    
    $('#currentPageDisplay').text(pagination.current_page);
    $('#lastPageDisplay').text(pagination.last_page);
}

function populateScheduleTable(data) {
    const tbody = $('#scheduleTableBody');
    tbody.empty();
    
    data.forEach((item, index) => {
        const row = `
            <tr>
                <td>${item.id}</td>
                <td>
                    <strong class="text-primary">${item.title}</strong>
                </td>
                <td>
                    <span class="badge bg-light text-dark">
                        <i class="bx bx-calendar me-1"></i>
                        ${formatDate(item.start)}
                    </span>
                </td>
                <td>
                    <span class="badge bg-light text-dark">
                        <i class="bx bx-calendar me-1"></i>
                        ${formatDate(item.end)}
                    </span>
                </td>
                <td>
                    <span class="badge bg-info">
                        <i class="bx bx-time me-1"></i>
                        ${item.days} day(s)
                    </span>
                </td>
                <td>
                    <span class="badge bg-secondary">
                        <i class="bx bx-building me-1"></i>
                        ${item.division}
                    </span>
                </td>
                <td>
                    <span class="badge bg-warning text-dark">
                        <i class="bx bx-grid-alt me-1"></i>
                        ${item.matrix}
                    </span>
                </td>
                                                                <td>
                                                    <span class="text-${item.international_travel ? 'warning' : 'info'} fw-bold">
                                                        <i class="bx bx-plane me-1"></i>
                                                        ${item.international_travel ? 'International' : 'Domestic'}
                                                    </span>
                                                </td>
                                                                <td>
                                                    <a href="{{ url('/')}}/matrices/${item.matrix_id}/activities/${item.id}" class="btn btn-outline-info btn-sm" title="View Details">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

function updatePagination(pagination) {
    const container = $('#paginationContainer');
    container.empty();
    
    // Previous button
    const prevDisabled = pagination.current_page === 1 ? 'disabled' : '';
    const prevButton = `
        <li class="page-item ${prevDisabled}">
            <a class="page-link" href="#" onclick="loadSchedule(${pagination.current_page - 1})" ${prevDisabled}>
                <i class="bx bx-chevron-left"></i>
            </a>
        </li>
    `;
    container.append(prevButton);
    
    // Page numbers
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const active = i === pagination.current_page ? 'active' : '';
        const pageButton = `
            <li class="page-item ${active}">
                <a class="page-link" href="#" onclick="loadSchedule(${i})">${i}</a>
            </li>
        `;
        container.append(pageButton);
    }
    
    // Next button
    const nextDisabled = pagination.current_page === pagination.last_page ? 'disabled' : '';
    const nextButton = `
        <li class="page-item ${nextDisabled}">
            <a class="page-link" href="#" onclick="loadSchedule(${pagination.current_page + 1})" ${nextDisabled}>
                <i class="bx bx-chevron-right"></i>
            </a>
        </li>
    `;
    container.append(nextButton);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

function showError(message) {
    $('#loadingState').addClass('d-none');
    $('#scheduleTable').addClass('d-none');
    $('#emptyState').addClass('d-none');
    $('#errorState').removeClass('d-none');
    $('#errorMessage').text(message);
}

function refreshSchedule() {
    loadSchedule(currentPage);
}


</script>
@endpush
