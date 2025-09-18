<div class="card shadow-sm border-0">
    <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="bx bx-calendar-event me-2 text-primary"></i>
                    Division Schedule - {{ strtoupper($matrix->quarter) }} {{ $matrix->year }}
                </h5>
                <small class="text-muted d-block mt-1">
                    Staff schedule for {{ $matrix->division->division_name ?? 'Division' }} in {{ strtoupper($matrix->quarter) }} {{ $matrix->year }}
                </small>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <div class="input-group flex-grow-1">
                        <span class="input-group-text bg-white">
                            <i class="bx bx-search text-muted"></i>
                        </span>
                        <input type="text" id="staffSearch" class="form-control" 
                               placeholder="Search by name, position, or duty station..." 
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
            <table class="table table-hover align-middle mb-0" id="divisionStaffTable">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 50px;">#</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Staff Name</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Position</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Division Days</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Other Divisions</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Total Days</th>
                    </tr>
                </thead>
                <tbody id="staffTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-4">
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
                        <div class="col-md-4">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-user-check text-success me-2"></i>
                                <div>
                                    <div class="fw-bold text-success" id="totalStaff">0</div>
                                    <small class="text-muted">Total Staff</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-calendar-check text-primary me-2"></i>
                                <div>
                                    <div class="fw-bold text-primary" id="totalDivisionDays">0</div>
                                    <small class="text-muted">Division Days</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-exclamation-triangle text-warning me-2"></i>
                                <div>
                                    <div class="fw-bold text-danger" id="overLimitCount">0</div>
                                    <small class="text-muted">Over Limit</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if required elements exist
    const staffSearchEl = document.getElementById('staffSearch');
    const pageSizeSelectEl = document.getElementById('pageSizeSelect');
    const staffTableBodyEl = document.getElementById('staffTableBody');
    
    if (!staffSearchEl || !pageSizeSelectEl || !staffTableBodyEl) {
        console.error('Required elements not found for division staff table');
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
                <td colspan="6" class="text-center py-4">
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
            url: '{{ route("matrices.division-staff-ajax", $matrix) }}',
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
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bx bx-calendar-x fs-1 text-muted"></i>
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
                        <td colspan="6" class="text-center py-4 text-danger">
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
            const fullName = `${staff.title} ${staff.fname} ${staff.lname}`;
            const rowClass = staff.is_over_limit ? 'table-danger' : '';
            
            html += `
                <tr class="${rowClass}">
                    <td class="px-3 py-3">
                        <span class="badge bg-secondary rounded-pill">${rowNumber}</span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="fw-semibold text-wrap" style="max-width: 200px;">
                            <a href="{{ url('staff') }}/${staff.staff_id}/activities/matrix/{{ $matrix->id }}" 
                               class="text-decoration-none text-primary" target="_blank">
                                ${fullName}
                            </a>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-muted text-wrap" style="max-width: 200px;">${staff.job_name || 'Not specified'}</div>
                        ${staff.duty_station_name ? `<small class="text-muted text-wrap" style="max-width: 200px;">${staff.duty_station_name}</small>` : ''}
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="fw-semibold text-muted">${staff.division_days}</span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="fw-semibold text-muted">${staff.other_days}</span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        ${staff.is_over_limit ? `
                            <span class="fw-bold text-danger">
                                <i class="bx bx-exclamation-triangle me-1"></i>${staff.total_days}
                            </span>
                            <small class="d-block text-danger mt-1">Over limit</small>
                        ` : `
                            <span class="fw-bold text-muted">${staff.total_days}</span>
                        `}
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
        $('#totalDivisionDays').text(summary.total_division_days || 0);
        $('#overLimitCount').text(summary.over_limit_count || 0);
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
</script>