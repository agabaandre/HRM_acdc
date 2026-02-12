@extends('layouts.app')

@section('title', 'Locations')

@section('header', 'Locations')

@section('header-actions')
    <a href="{{ route('locations.create') }}" class="btn btn-primary">
        <i class="bx bx-plus"></i> Add Location
    </a>
@endsection

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="card-title mb-0 fw-bold text-dark">
                    <i class="bx bx-map-pin me-2 text-success"></i>
                    Locations
                </h4>
                <small class="text-muted d-block mt-1">View and manage locations</small>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2">
                    <div class="input-group flex-grow-1">
                        <span class="input-group-text bg-white"><i class="bx bx-search text-muted"></i></span>
                        <input type="text" id="locationSearch" class="form-control"
                               placeholder="Search by name..."
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
            <table class="table table-hover align-middle mb-0" id="locationsTable">
                <thead class="bg-light">
                    <tr>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 60px;">#</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Name</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold">Created At</th>
                        <th class="border-0 px-3 py-3 text-muted fw-semibold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="locationsTableBody">
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i>
                            <div class="mt-2">Loading locations...</div>
                            </td>
                        </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-light border-0 py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted me-3">Showing <span id="showingRange">0-0</span> of <span id="totalRecords">0</span> locations</span>
                    <div class="btn-group ms-2" role="group" id="paginationButtons"></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <span class="text-muted"><span id="filteredCount">0</span> matching</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
    var locationSearchEl = document.getElementById('locationSearch');
    var pageSizeSelectEl = document.getElementById('pageSizeSelect');
    var locationsTableBodyEl = document.getElementById('locationsTableBody');

    if (!locationSearchEl || !pageSizeSelectEl || !locationsTableBodyEl) return;

    var currentPage = 1;
    var pageSize = 25;
    var searchTerm = '';
    var totalRecords = 0;
    var totalPages = 0;
    var searchTimeout;

    function loadLocationsData(page) {
        currentPage = page;
        locationsTableBodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4"><i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i><div class="mt-2">Loading locations...</div></td></tr>';

        $.ajax({
            url: '{{ route("locations.ajax") }}',
            type: 'GET',
            data: { search: searchTerm, page: page, pageSize: pageSize },
            dataType: 'json',
            success: function(response) {
                if (response.data && response.data.length > 0) {
                    renderTable(response.data);
                    totalRecords = response.recordsTotal;
                    totalPages = response.totalPages || 0;
                    renderPagination();
                    updateSummary(response.summary);
                } else {
                    locationsTableBodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted"><i class="bx bx-map-pin fs-1"></i><div class="mt-2">No locations found</div><small>Try adjusting your search</small></td></tr>';
                    renderPagination();
                    updateSummary(response.summary || {});
                }
                updateShowingRange();
            },
            error: function(xhr) {
                locationsTableBodyEl.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger"><i class="bx bx-error fs-1"></i><div class="mt-2">Error loading data</div></td></tr>';
            }
        });
    }

    function renderTable(data) {
        var startIndex = (currentPage - 1) * pageSize;
        var html = '';
        data.forEach(function(loc, index) {
            var rowNum = startIndex + index + 1;
            var created = '—';
            if (loc.created_at) {
                if (typeof loc.created_at === 'string') created = loc.created_at.split(' ')[0];
                else if (loc.created_at.date) created = loc.created_at.date.split(' ')[0];
            }
            html += '<tr>' +
                '<td class="px-3 py-3"><span class="badge bg-secondary rounded-pill">' + rowNum + '</span></td>' +
                '<td class="px-3 py-3 fw-semibold">' + escapeHtml(loc.name || '') + '</td>' +
                '<td class="px-3 py-3 text-muted">' + created + '</td>' +
                '<td class="px-3 py-3 text-end">' +
                '<a href="{{ url("locations") }}/' + loc.id + '" class="btn btn-sm btn-outline-info" title="View"><i class="bx bx-show"></i></a> ' +
                '<a href="{{ url("locations") }}/' + loc.id + '/edit" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bx bx-edit"></i></a>' +
                '</td></tr>';
        });
        locationsTableBodyEl.innerHTML = html;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderPagination() {
        var html = '';
        if (totalPages <= 1) {
            document.getElementById('paginationButtons').innerHTML = '';
            return;
        }
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" ' + (currentPage === 1 ? 'disabled' : '') + ' onclick="window.loadLocationsData(' + (currentPage - 1) + ')"><i class="bx bx-chevron-left"></i></button>';
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, currentPage + 2);
        if (startPage > 1) {
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.loadLocationsData(1)">1</button>';
            if (startPage > 2) html += '<span class="btn btn-outline-secondary btn-sm disabled">...</span>';
        }
        for (var i = startPage; i <= endPage; i++) {
            html += '<button type="button" class="btn ' + (i === currentPage ? 'btn-primary' : 'btn-outline-secondary') + ' btn-sm" onclick="window.loadLocationsData(' + i + ')">' + i + '</button>';
        }
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="btn btn-outline-secondary btn-sm disabled">...</span>';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.loadLocationsData(' + totalPages + ')">' + totalPages + '</button>';
        }
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" ' + (currentPage === totalPages ? 'disabled' : '') + ' onclick="window.loadLocationsData(' + (currentPage + 1) + ')"><i class="bx bx-chevron-right"></i></button>';
        document.getElementById('paginationButtons').innerHTML = html;
    }

    function updateSummary(summary) {
        document.getElementById('filteredCount').textContent = summary.filtered_locations !== undefined ? summary.filtered_locations : totalRecords;
    }

    function updateShowingRange() {
        var start = totalRecords > 0 ? (currentPage - 1) * pageSize + 1 : 0;
        var end = Math.min(currentPage * pageSize, totalRecords);
        document.getElementById('showingRange').textContent = start + '-' + end;
        document.getElementById('totalRecords').textContent = totalRecords;
    }

    locationSearchEl.addEventListener('input', function() {
        searchTerm = this.value;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() { loadLocationsData(1); }, 500);
    });

    pageSizeSelectEl.addEventListener('change', function() {
        pageSize = parseInt(this.value, 10);
        loadLocationsData(1);
    });

    window.loadLocationsData = loadLocationsData;
    loadLocationsData(1);
    });
</script>
@endpush
