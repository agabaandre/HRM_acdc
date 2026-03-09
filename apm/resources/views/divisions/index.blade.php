@extends('layouts.app')

@section('title', 'Divisions')

@section('header', 'Divisions')

@section('header-actions')
<div class="text-muted">
    <small><i class="bx bx-info-circle me-1"></i>Divisions are managed in the main system</small>
</div>
@endsection

@section('content')
<style>
    #divisions-toolbar .divisions-search-input { min-width: 180px; font-size: 0.9rem; }
    #divisions-toolbar .btn-divisions-search,
    #divisions-toolbar .btn-divisions-export { min-width: 140px; font-size: 0.9rem; padding: 0.45rem 0.75rem; }
</style>
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="bx bx-building-house me-2 text-primary"></i>All Divisions</h5>
            </div>
            <div class="col-md-6">
                <div class="d-flex gap-2 align-items-center flex-wrap" id="divisions-toolbar">
                    <form id="divisions-search-form" class="d-flex gap-2 align-items-center" role="search">
                        <input type="text" name="search" id="divisions-search-input" class="form-control divisions-search-input" placeholder="Search divisions..." value="{{ $initialSearch ?? '' }}" autocomplete="off">
                        <button type="submit" class="btn btn-outline-primary btn-divisions-search">
                            <i class="bx bx-search me-1"></i>Search
                        </button>
                        <button type="button" id="divisions-clear-btn" class="btn btn-outline-secondary btn-divisions-search d-none" title="Clear search">
                            <i class="bx bx-x"></i> Clear
                        </button>
                    </form>
                    <a id="divisions-export-excel" href="{{ route('divisions.export.excel', ['search' => $initialSearch ?? '', 'sort_by' => $initialSortBy ?? 'division_name', 'sort_direction' => $initialSortDirection ?? 'asc']) }}" class="btn btn-outline-success btn-divisions-export" title="Export to Excel">
                        <i class="bx bx-download me-1"></i> Export to Excel
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger m-3">
                {{ session('error') }}
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;" class="divisions-sort" data-sort="id">
                            # <i class="bx bx-sort"></i>
                        </th>
                        <th style="width: 150px;" class="divisions-sort" data-sort="division_name">
                            Division Name <i class="bx bx-sort"></i>
                        </th>
                        <th style="width: 120px;" class="divisions-sort" data-sort="division_short_name">
                            Short Name <i class="bx bx-sort"></i>
                        </th>
                        <th style="width: 100px;" class="divisions-sort" data-sort="category">
                            Category <i class="bx bx-sort"></i>
                        </th>
                        <th style="width: 200px;">Division Head</th>
                        <th style="width: 200px;">Focal Person</th>
                        <th style="width: 200px;">Admin Assistant</th>
                        <th style="width: 200px;">Finance Officer</th>
                        <th style="width: 100px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="divisions-table-body">
                    <tr>
                        <td colspan="9" class="text-center py-4">
                            <i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i>
                            <div class="mt-2">Loading divisions...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0 text-muted small">
                    Showing <span id="divisions-showing-range">0-0</span> of <span id="divisions-total-records">0</span> divisions
                </p>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap">
                    <label for="divisions-per-page" class="form-label mb-0 small">Per page:</label>
                    <select id="divisions-per-page" class="form-select form-select-sm" style="width: auto;">
                        <option value="5" {{ ($initialPageSize ?? 15) == 5 ? 'selected' : '' }}>5</option>
                        <option value="10" {{ ($initialPageSize ?? 15) == 10 ? 'selected' : '' }}>10</option>
                        <option value="15" {{ ($initialPageSize ?? 15) == 15 ? 'selected' : '' }}>15</option>
                        <option value="25" {{ ($initialPageSize ?? 15) == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ ($initialPageSize ?? 15) == 50 ? 'selected' : '' }}>50</option>
                    </select>
                    <div class="btn-group ms-2" role="group" id="divisions-pagination-buttons"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    var searchInput = document.getElementById('divisions-search-input');
    var searchForm = document.getElementById('divisions-search-form');
    var tableBody = document.getElementById('divisions-table-body');
    var perPageEl = document.getElementById('divisions-per-page');
    var exportLink = document.getElementById('divisions-export-excel');
    var clearBtn = document.getElementById('divisions-clear-btn');

    var state = {
        page: {{ $initialPage ?? 1 }},
        pageSize: {{ $initialPageSize ?? 15 }},
        sortBy: '{{ $initialSortBy ?? "division_name" }}',
        sortDirection: '{{ $initialSortDirection ?? "asc" }}',
        search: '{{ $initialSearch ?? "" }}',
        totalRecords: 0,
        totalPages: 0
    };

    function updateExportLink() {
        if (!exportLink) return;
        var params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        params.set('sort_by', state.sortBy);
        params.set('sort_direction', state.sortDirection);
        exportLink.href = '{{ route("divisions.export.excel") }}?' + params.toString();
    }

    function loadDivisions() {
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><i class="bx bx-loader-alt bx-spin fs-1 text-primary"></i><div class="mt-2">Loading divisions...</div></td></tr>';

        var params = {
            page: state.page,
            pageSize: state.pageSize,
            sort_by: state.sortBy,
            sort_direction: state.sortDirection,
            search: state.search
        };

        $.ajax({
            url: '{{ route("divisions.ajax") }}',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(res) {
                state.totalRecords = res.recordsTotal || 0;
                state.totalPages = res.totalPages || 0;
                if (res.data && res.data.length > 0) {
                    renderRows(res.data);
                } else {
                    tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bx bx-folder-open fs-1"></i><p class="mt-2 mb-0">No divisions found</p><small>Try adjusting your search</small></td></tr>';
                }
                updateShowingRange();
                renderPagination();
                updateExportLink();
            },
            error: function() {
                tableBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger"><i class="bx bx-error fs-1"></i><div class="mt-2">Error loading data</div></td></tr>';
            }
        });
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderRows(data) {
        var start = (state.page - 1) * state.pageSize;
        var baseUrl = '{{ rtrim(route("divisions.index"), "/") }}';
        var html = '';
        data.forEach(function(d, i) {
            var rowNum = start + i + 1;
            var headRel = d.division_head || d.divisionHead;
            var head = headRel ? (escapeHtml(headRel.fname || '') + ' ' + escapeHtml(headRel.lname || '')) : 'N/A';
            var headSub = headRel && headRel.position ? '<small class="text-muted">' + escapeHtml(headRel.position) + '</small>' : '<small class="text-muted">Staff</small>';
            var focalRel = d.focal_person || d.focalPerson;
            var focal = focalRel ? (escapeHtml(focalRel.fname || '') + ' ' + escapeHtml(focalRel.lname || '')) : 'N/A';
            var focalSub = focalRel && focalRel.position ? '<small class="text-muted">' + escapeHtml(focalRel.position) + '</small>' : '<small class="text-muted">Staff</small>';
            var adminRel = d.admin_assistant || d.adminAssistant;
            var admin = adminRel ? (escapeHtml(adminRel.fname || '') + ' ' + escapeHtml(adminRel.lname || '')) : 'N/A';
            var adminSub = adminRel && adminRel.position ? '<small class="text-muted">' + escapeHtml(adminRel.position) + '</small>' : '<small class="text-muted">Staff</small>';
            var financeRel = d.finance_officer || d.financeOfficer;
            var finance = financeRel ? (escapeHtml(financeRel.fname || '') + ' ' + escapeHtml(financeRel.lname || '')) : 'N/A';
            var financeSub = financeRel && financeRel.position ? '<small class="text-muted">' + escapeHtml(financeRel.position) + '</small>' : '<small class="text-muted">Staff</small>';
            html += '<tr>' +
                '<td class="fw-bold">' + (d.id || rowNum) + '</td>' +
                '<td style="max-width:150px; word-wrap:break-word; vertical-align:middle;"><span class="fw-semibold">' + escapeHtml(d.division_name || '') + '</span></td>' +
                '<td>' + (d.division_short_name ? '<span class="badge bg-primary">' + escapeHtml(d.division_short_name) + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
                '<td>' + (d.category ? '<span class="badge bg-secondary">' + escapeHtml(d.category) + '</span>' : '<span class="text-muted">-</span>') + '</td>' +
                '<td><div class="d-flex flex-column"><span>' + head + '</span>' + headSub + '</div></td>' +
                '<td><div class="d-flex flex-column"><span>' + focal + '</span>' + focalSub + '</div></td>' +
                '<td><div class="d-flex flex-column"><span>' + admin + '</span>' + adminSub + '</div></td>' +
                '<td><div class="d-flex flex-column"><span>' + finance + '</span>' + financeSub + '</div></td>' +
                '<td class="text-end"><a href="' + baseUrl + '/' + d.id + '" class="btn btn-sm btn-outline-info" wire:navigate><i class="bx bx-show me-1"></i>View</a></td>' +
                '</tr>';
        });
        tableBody.innerHTML = html;
    }

    function updateShowingRange() {
        var start = state.totalRecords > 0 ? (state.page - 1) * state.pageSize + 1 : 0;
        var end = Math.min(state.page * state.pageSize, state.totalRecords);
        var rangeEl = document.getElementById('divisions-showing-range');
        var totalEl = document.getElementById('divisions-total-records');
        if (rangeEl) rangeEl.textContent = start + '-' + end;
        if (totalEl) totalEl.textContent = state.totalRecords;
    }

    function renderPagination() {
        var container = document.getElementById('divisions-pagination-buttons');
        if (!container) return;
        var html = '';
        if (state.totalPages <= 1) { container.innerHTML = ''; return; }
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" ' + (state.page === 1 ? 'disabled' : '') + ' data-page="' + (state.page - 1) + '"><i class="bx bx-chevron-left"></i></button>';
        var startPage = Math.max(1, state.page - 2);
        var endPage = Math.min(state.totalPages, state.page + 2);
        if (startPage > 1) {
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" data-page="1">1</button>';
            if (startPage > 2) html += '<span class="btn btn-outline-secondary btn-sm disabled">...</span>';
        }
        for (var i = startPage; i <= endPage; i++) {
            html += '<button type="button" class="btn ' + (i === state.page ? 'btn-primary' : 'btn-outline-secondary') + ' btn-sm" data-page="' + i + '">' + i + '</button>';
        }
        if (endPage < state.totalPages) {
            if (endPage < state.totalPages - 1) html += '<span class="btn btn-outline-secondary btn-sm disabled">...</span>';
            html += '<button type="button" class="btn btn-outline-secondary btn-sm" data-page="' + state.totalPages + '">' + state.totalPages + '</button>';
        }
        html += '<button type="button" class="btn btn-outline-secondary btn-sm" ' + (state.page === state.totalPages ? 'disabled' : '') + ' data-page="' + (state.page + 1) + '"><i class="bx bx-chevron-right"></i></button>';
        container.innerHTML = html;
        container.querySelectorAll('[data-page]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var p = parseInt(this.getAttribute('data-page'), 10);
                if (p >= 1 && p <= state.totalPages) { state.page = p; loadDivisions(); }
            });
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            state.search = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
            state.page = 1;
            clearBtn.classList.toggle('d-none', !state.search);
            loadDivisions();
        });
    }
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            state.search = '';
            state.page = 1;
            clearBtn.classList.add('d-none');
            loadDivisions();
        });
    }
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearBtn.classList.toggle('d-none', !this.value.trim());
        });
        if (state.search) clearBtn.classList.remove('d-none');
    }
    if (perPageEl) {
        perPageEl.addEventListener('change', function() {
            state.pageSize = parseInt(this.value, 10);
            state.page = 1;
            loadDivisions();
        });
    }
    document.querySelectorAll('.divisions-sort').forEach(function(th) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            var col = this.getAttribute('data-sort');
            if (!col) return;
            state.sortDirection = (state.sortBy === col && state.sortDirection === 'asc') ? 'desc' : 'asc';
            state.sortBy = col;
            state.page = 1;
            loadDivisions();
        });
    });

    window.loadDivisions = loadDivisions;
    loadDivisions();
})();
</script>
@endpush
