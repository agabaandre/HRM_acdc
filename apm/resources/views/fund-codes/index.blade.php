@extends('layouts.app')

@section('title', 'Fund Codes')

@section('header', 'Fund Codes')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-success shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="bx bx-upload me-1"></i> Upload CSV
    </button>
    <a href="{{ route('fund-codes.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Add Fund Code
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header bg-white border-bottom">
        <h5 class="mb-0 text-dark">
            <i class="bx bx-barcode me-2"></i> Fund Code Management
        </h5>
    </div>
    <div class="card-body py-3 px-4 bg-light">

        <div class="row g-3 align-items-end" id="fundCodeFilters" autocomplete="off">
            <div class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="fundCodeSearch" class="form-label fw-semibold mb-1"><i class="bx bx-search me-1 text-success"></i> Search</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-search"></i></span>
                        <input type="text" id="fundCodeSearch" class="form-control" placeholder="Search fund codes..." value="{{ $initialSearch }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="filterFundTypeId" class="form-label fw-semibold mb-1"><i class="bx bx-category me-1 text-success"></i> Fund Type</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-category"></i></span>
                        <select id="filterFundTypeId" class="form-select">
                            <option value="">All Fund Types</option>
                            @foreach($fundTypes as $fundType)
                                <option value="{{ $fundType->id }}" {{ $initialFundTypeId == $fundType->id ? 'selected' : '' }}>{{ $fundType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="filterDivisionId" class="form-label fw-semibold mb-1"><i class="bx bx-building me-1 text-success"></i> Division</label>
                    <div class="input-group w-100">
                     
                        <select id="filterDivisionId" class="form-select select2-fundcode-division" style="width: 100%;">
                            <option value="" {{ ($initialDivisionId === '' || $initialDivisionId === null) ? 'selected' : '' }}>All Divisions</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}" {{ $initialDivisionId == $division->id ? 'selected' : '' }}>{{ $division->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="filterYear" class="form-label fw-semibold mb-1"><i class="bx bx-calendar me-1 text-success"></i> Year</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-calendar"></i></span>
                        <select id="filterYear" class="form-select">
                            @for($yearOption = date('Y'); $yearOption >= date('Y') - 5; $yearOption--)
                                <option value="{{ $yearOption }}" {{ $initialYear == $yearOption ? 'selected' : '' }}>{{ $yearOption }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="filterStatus" class="form-label fw-semibold mb-1"><i class="bx bx-info-circle me-1 text-success"></i> Status</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-info-circle"></i></span>
                        <select id="filterStatus" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" {{ $initialStatus == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $initialStatus == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary w-100 fw-bold" id="resetFilters">
                        <i class="bx bx-reset me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light border-0 py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0 text-success fw-bold">
                    <i class="bx bx-barcode me-2"></i> Fund Codes List
                </h6>
                <small class="text-muted">All funding codes and activities</small>
            </div>
            <div>
                <select id="fundCodePageSize" class="form-select form-select-sm" style="width: 130px;">
                    <option value="10">10 per page</option>
                    <option value="25" selected>25 per page</option>
                    <option value="50">50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-success">
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Year</th>
                        <th>Funder</th>
                        <th>Fund Type</th>
                        <th>Division</th>
                        <th>Partner</th>
                        <th>Activity</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="fundCodesTableBody">
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i>
                            <div class="mt-2">Loading fund codes...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light border-0 py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted me-3">Showing <span id="fundCodeShowingRange">0-0</span> of <span id="fundCodeTotalRecords">0</span> fund codes</span>
                    <div class="btn-group ms-2" role="group" id="fundCodePaginationButtons"></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted"><span id="fundCodeFilteredCount">0</span> matching</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.fundCodeInitialFilters = {
        search: @json($initialSearch),
        fundTypeId: @json($initialFundTypeId),
        divisionId: @json($initialDivisionId),
        year: @json($initialYear),
        status: @json($initialStatus)
    };
    window.fundCodeInitialPage = @json($initialPage);
    window.fundCodeCurrentYear = @json(date('Y'));
</script>
@endsection

<!-- Upload CSV Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">
                    <i class="bx bx-upload me-2"></i> Upload Fund Codes via CSV
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('fund-codes.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Instructions:</strong> Upload a CSV file with fund code data. Download the template below for the correct format.
                    </div>
                    
                    <div class="mb-3">
                        <label for="csv_file" class="form-label fw-semibold">
                            <i class="bx bx-file me-1 text-success"></i> CSV File <span class="text-danger">*</span>
                        </label>
                        <input type="file" class="form-control @error('csv_file') is-invalid @enderror" 
                               id="csv_file" name="csv_file" accept=".csv" required>
                        <small class="text-muted">Only CSV files are allowed. Maximum file size: 5MB</small>
                        @error('csv_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked>
                            <label class="form-check-label" for="skip_duplicates">
                                Skip duplicate fund codes (based on code and year)
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="validate_only" name="validate_only" value="1">
                            <label class="form-check-label" for="validate_only">
                                Validate only (don't import data)
                            </label>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('fund-codes.download-template') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-download me-1"></i> Download Template
                        </a>
                        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#csvFormat">
                            <i class="bx bx-help-circle me-1"></i> View Format
                        </button>
                    </div>

                    <div class="collapse mt-3" id="csvFormat">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="fw-bold">CSV Format Requirements:</h6>
                                <p class="mb-2"><strong>Required columns:</strong> funder_id, year, code, fund_type_id</p>
                                <p class="mb-2"><strong>Optional columns:</strong> activity, division_id, cost_centre, amert_code, fund, budget_balance, approved_budget, uploaded_budget, is_active</p>
                                <p class="mb-0"><strong>Note:</strong> Division is required for intramural fund types only.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="uploadBtn">
                        <i class="bx bx-upload me-1"></i> Upload & Process
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
    // Initialize Select2 for division filter (so value is read correctly and UI is searchable)
    if ($.fn.select2 && $('#filterDivisionId').length) {
        $('#filterDivisionId').select2({ width: '100%', placeholder: 'All Divisions', allowClear: true });
    }

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle file upload form
    $('#uploadForm').on('submit', function(e) {
        var fileInput = $('#csv_file')[0];
        var uploadBtn = $('#uploadBtn');
        if (fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please select a CSV file to upload.');
            return;
        }
        uploadBtn.prop('disabled', true);
        uploadBtn.html('<i class="bx bx-loader-alt bx-spin me-1"></i> Processing...');
    });

    $('#csv_file').on('change', function() {
        var file = this.files[0];
        var maxSize = 5 * 1024 * 1024;
        if (file && file.size > maxSize) {
            alert('File size must be less than 5MB.');
            this.value = '';
        }
    });

    // --- Fund codes DataTable (AJAX) ---
    var fundCodeSearchEl = document.getElementById('fundCodeSearch');
    var fundCodePageSizeEl = document.getElementById('fundCodePageSize');
    var fundCodesTableBodyEl = document.getElementById('fundCodesTableBody');
    var currentPage = 1;
    var pageSize = 25;
    var totalRecords = 0;
    var totalPages = 0;
    var searchTimeout;

    function getFundCodeParams(page) {
        var yearEl = document.getElementById('filterYear');
        var yearVal = (yearEl && yearEl.value) ? String(yearEl.value).trim() : '';
        if (yearVal === '') yearVal = (window.fundCodeCurrentYear || new Date().getFullYear()).toString();
        var divisionVal = '';
        if (typeof $ !== 'undefined' && $('#filterDivisionId').length) {
            divisionVal = $('#filterDivisionId').val();
            if (divisionVal == null) divisionVal = '';
            else divisionVal = String(divisionVal).trim();
        } else {
            var divEl = document.getElementById('filterDivisionId');
            divisionVal = (divEl && divEl.value) ? divEl.value : '';
        }
        return {
            search: (document.getElementById('fundCodeSearch') && document.getElementById('fundCodeSearch').value) || '',
            page: page,
            pageSize: pageSize,
            year: yearVal,
            fund_type_id: (document.getElementById('filterFundTypeId') && document.getElementById('filterFundTypeId').value) || '',
            division_id: divisionVal,
            status: (document.getElementById('filterStatus') && document.getElementById('filterStatus').value) || ''
        };
    }

    function loadFundCodesData(page) {
        if (!fundCodesTableBodyEl) return;
        currentPage = page;
        var params = getFundCodeParams(page);
        fundCodesTableBodyEl.innerHTML = '<tr><td colspan="10" class="text-center py-4"><i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i><div class="mt-2">Loading fund codes...</div></td></tr>';

        $.ajax({
            url: '{{ route("fund-codes.ajax") }}',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(response) {
                if (response.data && response.data.length > 0) {
                    renderFundCodesTable(response.data);
                    totalRecords = response.recordsTotal;
                    totalPages = response.totalPages || 0;
                    renderFundCodePagination();
                } else {
                    fundCodesTableBodyEl.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted"><i class="bx bx-barcode fs-1"></i><div class="mt-2">No fund codes found</div><small>Try adjusting your filters</small></td></tr>';
                    totalRecords = 0;
                    totalPages = 0;
                    renderFundCodePagination();
                }
                document.getElementById('fundCodeFilteredCount').textContent = totalRecords;
                updateFundCodeShowingRange();
            },
            error: function() {
                fundCodesTableBodyEl.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-danger"><i class="bx bx-error fs-1"></i><div class="mt-2">Error loading data</div></td></tr>';
            }
        });
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderFundCodesTable(data) {
        var startIndex = (currentPage - 1) * pageSize;
        var baseUrl = '{{ rtrim(route("fund-codes.index"), "/") }}';
        var html = '';
        data.forEach(function(fc, index) {
            var rowNum = startIndex + index + 1;
            var codeCell = '<div class="fw-bold text-primary">' + escapeHtml(fc.code || '') + '</div>';
            if (fc.cost_centre) codeCell += '<small class="text-muted">CC: ' + escapeHtml(fc.cost_centre) + '</small>';
            var funderName = (fc.funder && fc.funder.name) ? escapeHtml(fc.funder.name) : 'N/A';
            var fundTypeName = (fc.fund_type && fc.fund_type.name) ? escapeHtml(fc.fund_type.name) : ((fc.fundType && fc.fundType.name) ? escapeHtml(fc.fundType.name) : 'N/A');
            var fundTypeNameLower = (fc.fund_type && fc.fund_type.name) ? String(fc.fund_type.name).toLowerCase() : ((fc.fundType && fc.fundType.name) ? String(fc.fundType.name).toLowerCase() : '');
            var divisionName = (fc.division && fc.division.division_name) ? escapeHtml(fc.division.division_name) : 'N/A';
            var partnerName = (fc.partner && fc.partner.name) ? escapeHtml(fc.partner.name) : '—';
            if (fundTypeNameLower !== 'extramural') partnerName = '—';
            var activityRaw = fc.activity || '';
            var activity = activityRaw ? (activityRaw.length > 50 ? escapeHtml(activityRaw.substring(0, 50)) + '…' : escapeHtml(activityRaw)) : 'N/A';
            var isActive = fc.is_active;
            var statusClass = isActive ? 'bg-success' : 'bg-danger';
            var statusText = isActive ? 'Active' : 'Inactive';
            var statusIcon = isActive ? 'check-circle' : 'x-circle';
            html += '<tr>' +
                '<td><span class="badge bg-secondary rounded-pill">' + rowNum + '</span></td>' +
                '<td>' + codeCell + '</td>' +
                '<td><span class="badge bg-info text-dark"><i class="bx bx-calendar me-1"></i>' + escapeHtml(fc.year || '') + '</span></td>' +
                '<td>' + funderName + '</td>' +
                '<td><span class="badge bg-secondary text-white"><i class="bx bx-category me-1"></i>' + fundTypeName + '</span></td>' +
                '<td>' + divisionName + '</td>' +
                '<td>' + partnerName + '</td>' +
                '<td><div class="text-truncate" style="max-width:200px" title="' + (activityRaw ? escapeHtml(activityRaw) : '') + '">' + activity + '</div></td>' +
                '<td><span class="badge ' + statusClass + ' text-white"><i class="bx bx-' + statusIcon + ' me-1"></i>' + statusText + '</span></td>' +
                '<td class="text-center"><div class="d-flex gap-2 justify-content-center">' +
                '<a href="' + baseUrl + '/' + fc.id + '" class="btn btn-sm btn-light text-info" title="View Details"><i class="bx bx-show fs-6"></i></a> ' +
                '<a href="' + baseUrl + '/' + fc.id + '/edit" class="btn btn-sm btn-light text-primary" title="Edit Fund Code"><i class="bx bx-edit fs-6"></i></a> ' +
                '<a href="' + baseUrl + '/' + fc.id + '/transactions" class="btn btn-sm btn-light text-success" title="View Transactions"><i class="bx bx-history fs-6"></i></a>' +
                '</div></td></tr>';
        });
        fundCodesTableBodyEl.innerHTML = html;
    }

    function renderFundCodePagination() {
        var container = document.getElementById('fundCodePaginationButtons');
        if (!container) return;
        var html = '';
        if (totalPages <= 1) { container.innerHTML = ''; return; }
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" ' + (currentPage === 1 ? 'disabled' : '') + ' onclick="window.loadFundCodesData(' + (currentPage - 1) + ')"><i class="bx bx-chevron-left"></i></button>';
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, currentPage + 2);
        if (startPage > 1) {
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.loadFundCodesData(1)">1</button>';
            if (startPage > 2) html += '<span class="btn btn-outline-secondary btn-sm disabled">...</span>';
        }
        for (var i = startPage; i <= endPage; i++) {
            html += '<button type="button" class="btn ' + (i === currentPage ? 'btn-primary' : 'btn-outline-secondary') + ' btn-sm" onclick="window.loadFundCodesData(' + i + ')">' + i + '</button>';
        }
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="btn btn-outline-secondary btn-sm disabled">...</span>';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.loadFundCodesData(' + totalPages + ')">' + totalPages + '</button>';
        }
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" ' + (currentPage === totalPages ? 'disabled' : '') + ' onclick="window.loadFundCodesData(' + (currentPage + 1) + ')"><i class="bx bx-chevron-right"></i></button>';
        container.innerHTML = html;
    }

    function updateFundCodeShowingRange() {
        var start = totalRecords > 0 ? (currentPage - 1) * pageSize + 1 : 0;
        var end = Math.min(currentPage * pageSize, totalRecords);
        document.getElementById('fundCodeShowingRange').textContent = start + '-' + end;
        document.getElementById('fundCodeTotalRecords').textContent = totalRecords;
    }

    if (fundCodeSearchEl) {
        fundCodeSearchEl.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() { loadFundCodesData(1); }, 500);
        });
    }
    if (fundCodePageSizeEl) {
        fundCodePageSizeEl.addEventListener('change', function() {
            pageSize = parseInt(this.value, 10);
            loadFundCodesData(1);
        });
    }
    document.getElementById('applyFilters').addEventListener('click', function() { loadFundCodesData(1); });
    document.getElementById('resetFilters').addEventListener('click', function() {
        var init = window.fundCodeInitialFilters || {};
        if (document.getElementById('fundCodeSearch')) document.getElementById('fundCodeSearch').value = init.search || '';
        if (document.getElementById('filterFundTypeId')) document.getElementById('filterFundTypeId').value = init.fundTypeId || '';
        if (document.getElementById('filterYear')) document.getElementById('filterYear').value = init.year || window.fundCodeCurrentYear || '';
        if (document.getElementById('filterStatus')) document.getElementById('filterStatus').value = init.status || '';
        var divVal = (init.divisionId != null && init.divisionId !== '') ? String(init.divisionId) : '';
        if (typeof $ !== 'undefined' && $('#filterDivisionId').length) {
            $('#filterDivisionId').val(divVal).trigger('change');
        } else if (document.getElementById('filterDivisionId')) {
            document.getElementById('filterDivisionId').value = divVal;
        }
        loadFundCodesData(1);
    });
    ['filterYear', 'filterStatus', 'filterFundTypeId', 'filterDivisionId'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', function() { loadFundCodesData(1); });
    });

    window.loadFundCodesData = loadFundCodesData;
    pageSize = parseInt(fundCodePageSizeEl && fundCodePageSizeEl.value ? fundCodePageSizeEl.value : 25, 10);
    loadFundCodesData(window.fundCodeInitialPage || 1);
});
</script>
@endpush
