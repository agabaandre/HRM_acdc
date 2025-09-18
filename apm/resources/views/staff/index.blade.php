@extends('layouts.app')

@section('title', 'Staff Management')

@section('header', 'Staff Management')

@section('header-actions')
@php $isFocal = isfocal_person(); @endphp
@endsection

@section('styles')
<style>
    .staff-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 14px;
    }
    
    .table th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
    }
    
    .status-badge {
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.375rem 0.75rem;
        border-radius: 50px;
    }
    
    .status-active {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .status-inactive {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .action-btn {
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        margin: 0 0.125rem;
        transition: all 0.2s ease;
    }
    
    .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .search-highlight {
        background-color: #fff3cd;
        padding: 0.125rem 0.25rem;
        border-radius: 0.25rem;
    }
</style>
@endsection

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-0 fw-bold text-dark">
                    <i class="bx bx-user me-2 text-success"></i>
                    Staff Directory
                </h4>
                <small class="text-muted d-block mt-1">
                    View and manage staff information
                </small>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <div class="input-group flex-grow-1">
                        <span class="input-group-text bg-white">
                            <i class="bx bx-search text-muted"></i>
                        </span>
                        <input type="text" id="staffSearch" class="form-control" 
                               placeholder="Search by name, position, or division..." 
                               autocomplete="off">
                    </div>
                    <select id="pageSizeSelect" class="form-select" style="width: 120px;">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="staffTable">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 50px;">#</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Name</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 200px;">Division</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Duty Station</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Job Title</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Contact</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Status</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i>
                            <div class="mt-2">Loading staff data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination and Summary -->
        <div class="card-footer bg-light border-0 py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3">Showing <span id="showingRange">0-0</span> of <span id="totalRecords">0</span> staff</span>
                        <div class="btn-group" role="group" id="paginationButtons">
                            <!-- Pagination buttons will be inserted here -->
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row text-center" id="summaryStats">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-user-check text-success me-2"></i>
                                <div>
                                    <div class="fw-bold text-success" id="totalStaff">0</div>
                                    <small class="text-muted">Total Staff</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-user text-primary me-2"></i>
                                <div>
                                    <div class="fw-bold text-primary" id="activeStaff">0</div>
                                    <small class="text-muted">Active</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-user-x text-warning me-2"></i>
                                <div>
                                    <div class="fw-bold text-warning" id="inactiveStaff">0</div>
                                    <small class="text-muted">Inactive</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-search text-info me-2"></i>
                                <div>
                                    <div class="fw-bold text-info" id="filteredStaff">0</div>
                                    <small class="text-muted">Filtered</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Staff Record
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this staff record?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-warning me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All related records will also be affected.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if required elements exist
    const staffSearchEl = document.getElementById('staffSearch');
    const pageSizeSelectEl = document.getElementById('pageSizeSelect');
    const staffTableBodyEl = document.getElementById('staffTableBody');
    
    if (!staffSearchEl || !pageSizeSelectEl || !staffTableBodyEl) {
        console.error('Required elements not found for staff table');
        return;
    }

    let currentPage = 1;
    let pageSize = 25;
    let searchTerm = '';
    let totalRecords = 0;
    let totalPages = 0;
    let searchTimeout;

    // Load staff data function
    function loadStaffData(page = 1) {
        currentPage = page;
        
        // Show loading
        staffTableBodyEl.innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i>
                    <div class="mt-2">Loading staff data...</div>
                </td>
            </tr>
        `;

        // Prepare data
        const data = {
            search: searchTerm,
            page: page,
            pageSize: pageSize
        };

        $.ajax({
            url: '{{ route("staff.ajax") }}',
            type: 'GET',
            data: data,
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Response:', response);
                
                if (response.data && response.data.length > 0) {
                    renderStaffTable(response.data);
                    totalRecords = response.recordsTotal;
                    totalPages = response.totalPages;
                    renderPagination();
                    updateSummary(response.summary);
                    updateShowingRange();
                } else {
                    staffTableBodyEl.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bx bx-user-x fs-1 text-muted"></i>
                                <div class="mt-2">No staff found</div>
                                <small>Try adjusting your search criteria</small>
                            </td>
                        </tr>
                    `;
                    renderPagination();
                    updateSummary(response.summary || {});
                    updateShowingRange();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error, xhr.responseText);
                staffTableBodyEl.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-danger">
                            <i class="bx bx-error fs-1"></i>
                            <div class="mt-2">Error loading staff data</div>
                            <small>Status: ${xhr.status} - ${error}</small>
                        </td>
                    </tr>
                `;
            }
        });
    }

    // Render staff table
    function renderStaffTable(staffData) {
        let html = '';
        const startIndex = (currentPage - 1) * pageSize;
        
        staffData.forEach((staff, index) => {
            const rowNumber = startIndex + index + 1;
            const fullName = [staff.fname, staff.lname, staff.oname].filter(Boolean).join(' ');
            const displayName = staff.title ? `${staff.title} ${fullName}` : fullName;
            const statusClass = staff.active == 1 ? 'status-active' : 'status-inactive';
            const statusText = staff.active == 1 ? 'Active' : 'Inactive';
            
            html += `
                <tr>
                    <td class="px-3 py-3">
                        <span class="badge bg-secondary rounded-pill">${rowNumber}</span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="fw-semibold text-wrap" style="max-width: 200px;">
                            ${displayName}
                        </div>
                        ${staff.work_email ? `<small class="text-muted">${staff.work_email}</small>` : ''}
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-muted text-wrap" style="max-width: 200px;">${staff.division ? staff.division.division_name : 'N/A'}</div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-muted text-wrap" style="max-width: 150px;">${staff.duty_station_name || 'N/A'}</div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-muted text-wrap" style="max-width: 150px;">${staff.job_name || 'N/A'}</div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-muted">
                            <div><i class="bx bx-phone me-1"></i>${staff.tel_1 || 'N/A'}</div>
                            ${staff.work_email ? `<div><i class="bx bx-envelope me-1"></i>${staff.work_email}</div>` : ''}
                        </div>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </td>
                </tr>
            `;
        });
        
        $('#staffTableBody').html(html);
    }

    // Render pagination
    function renderPagination() {
        let html = '';
        
        if (totalPages <= 1) {
            $('#paginationButtons').html('');
            return;
        }

        // Previous button
        html += `
            <button type="button" class="btn btn-outline-secondary btn-sm" 
                    ${currentPage === 1 ? 'disabled' : ''} 
                    onclick="loadStaffData(${currentPage - 1})">
                <i class="bx bx-chevron-left"></i>
            </button>
        `;

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            html += `<button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadStaffData(1)">1</button>`;
            if (startPage > 2) {
                html += `<span class="btn btn-outline-secondary btn-sm disabled">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button type="button" class="btn ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'} btn-sm" 
                        onclick="loadStaffData(${i})">
                    ${i}
                </button>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<span class="btn btn-outline-secondary btn-sm disabled">...</span>`;
            }
            html += `<button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadStaffData(${totalPages})">${totalPages}</button>`;
        }

        // Next button
        html += `
            <button type="button" class="btn btn-outline-secondary btn-sm" 
                    ${currentPage === totalPages ? 'disabled' : ''} 
                    onclick="loadStaffData(${currentPage + 1})">
                <i class="bx bx-chevron-right"></i>
            </button>
        `;

        $('#paginationButtons').html(html);
    }

    // Update summary statistics
    function updateSummary(summary) {
        $('#totalStaff').text(summary.total_staff || 0);
        $('#activeStaff').text(summary.active_staff || 0);
        $('#inactiveStaff').text(summary.inactive_staff || 0);
        $('#filteredStaff').text(summary.filtered_staff || 0);
    }

    // Update showing range
    function updateShowingRange() {
        const start = totalRecords > 0 ? (currentPage - 1) * pageSize + 1 : 0;
        const end = Math.min(currentPage * pageSize, totalRecords);
        $('#showingRange').text(`${start}-${end}`);
        $('#totalRecords').text(totalRecords);
    }

    // Search functionality
    staffSearchEl.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTerm = this.value;
        searchTimeout = setTimeout(() => {
            loadStaffData(1);
        }, 500);
    });

    // Page size change
    pageSizeSelectEl.addEventListener('change', function() {
        pageSize = parseInt(this.value);
        loadStaffData(1);
    });

    // Make loadStaffData globally available
    window.loadStaffData = loadStaffData;

    // Initial load
    loadStaffData(1);
});

// Delete confirmation function
function confirmDelete(staffId) {
    $('#deleteForm').attr('action', `/staff/${staffId}`);
    $('#deleteModal').modal('show');
}
</script>
@endpush
