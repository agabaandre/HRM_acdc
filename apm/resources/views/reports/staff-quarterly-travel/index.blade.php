@extends('layouts.app')

@section('title', 'Reports – Staff Quarterly Travel Days')
@section('header', 'Staff Quarterly Travel Days')

@section('content')
<style>
.reports-page .card .card-header { padding: 0.4rem 0.75rem; font-size: 0.9rem; }
.reports-page .card .card-body { padding: 0.5rem 0.75rem; }
#staffBreakdownModal .modal-dialog { max-height: 90vh; min-height: 70vh; max-width: 95vw; }
#staffBreakdownModal .modal-body { max-height: calc(90vh - 120px); overflow-y: auto; }
#staffBreakdownModal .breakdown-activity-title { white-space: normal; word-wrap: break-word; }
</style>
<div class="container-fluid reports-page">
    <div class="d-flex align-items-center gap-2 mb-2">
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Reports</a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light py-2 d-flex align-items-center gap-2">
            <i class="bx bx-filter-alt text-success"></i>
            <strong>Filters</strong>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small">Division</label>
                    <select id="filter_division" class="form-select form-select-sm" style="width: 100%;">
                        <option value="">All divisions</option>
                        @foreach($divisions as $d)
                            <option value="{{ $d->id }}">{{ $d->division_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Staff</label>
                    <select id="filter_staff" class="form-select form-select-sm" style="width: 100%;">
                        <option value="">All staff</option>
                        @foreach($staffList as $s)
                            <option value="{{ $s->staff_id }}">{{ trim($s->fname . ' ' . $s->lname) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Year</label>
                    <select id="filter_year" class="form-select form-select-sm">
                        <option value="">All years</option>
                        @foreach($years as $y)
                            <option value="{{ $y }}" {{ (isset($currentYear) && (int)$y === $currentYear) ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Quarter</label>
                    <select id="filter_quarter" class="form-select form-select-sm">
                        <option value="" selected>All quarters</option>
                        @foreach($quarters as $q)
                            <option value="{{ $q }}">{{ $q }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" id="btn_apply" class="btn btn-success btn-sm">Apply</button>
                    <button type="button" id="btn_reset" class="btn btn-outline-secondary btn-sm">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mb-3 py-2 px-3 small" role="note">
        <strong><i class="bx bx-info-circle me-1"></i> What this report considers:</strong>
        This report shows travel days from activities (matrix and single memos) whose matrix is approved. Only matrices with overall status &quot;Approved&quot; are included. For each activity, if there is an approved change request, the most recent one&apos;s participant list and days are used; otherwise the activity&apos;s internal participants are used. &quot;Approved travel days&quot; is the sum of participant days for each staff. Division is the staff member&apos;s division.
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light py-2 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong class="text-success"><i class="bx bx-trip me-1"></i> Staff Quarterly Travel Days</strong>
            <div class="d-flex gap-2">
                <a href="#" id="export_excel" class="btn btn-success btn-sm"><i class="bx bx-download me-1"></i> Export to Excel</a>
                <a href="#" id="export_pdf" class="btn btn-danger btn-sm"><i class="bx bx-file me-1"></i> Export to PDF</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="table_container">
                <div class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Loading…</p></div>
            </div>
        </div>
    </div>
</div>

<!-- Activity breakdown modal (when clicking staff name) -->
<div class="modal fade" id="staffBreakdownModal" tabindex="-1" aria-labelledby="staffBreakdownModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h5 class="modal-title mb-0" id="staffBreakdownModalLabel"><i class="bx bx-list-ul me-2"></i>Activity breakdown for <span id="breakdownStaffName"></span></h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="breakdownExportExcel" class="btn btn-success btn-sm d-none" title="Export breakdown to Excel"><i class="bx bx-download me-1"></i>Export to Excel</button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div id="breakdownLoading" class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Loading activities…</p></div>
                <div id="breakdownContent" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 3rem;">#</th>
                                    <th class="breakdown-activity-title">Activity title</th>
                                    <th>Year &amp; Quarter</th>
                                    <th class="text-center">Travel days</th>
                                </tr>
                            </thead>
                            <tbody id="breakdownTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="breakdownEmpty" class="text-center py-4 text-muted d-none">No activities found for this staff with the current filters.</div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function() {
    $('#filter_division').select2({ theme: 'bootstrap4', width: '100%', placeholder: 'All divisions', allowClear: true });
    $('#filter_staff').select2({ theme: 'bootstrap4', width: '100%', placeholder: 'All staff', allowClear: true });
});
</script>
<script>
(function() {
    var dataUrl = '{{ route('reports.staff-quarterly-travel.data') }}';
    var excelUrl = '{{ route('reports.staff-quarterly-travel.export.excel') }}';
    var pdfUrl = '{{ route('reports.staff-quarterly-travel.export.pdf') }}';
    var breakdownBaseUrl = '{{ route('reports.staff-quarterly-travel.breakdown', ['staffId' => '__STAFF_ID__']) }}';

    function getQuery() {
        var params = new URLSearchParams();
        var division = document.getElementById('filter_division').value;
        var staff = document.getElementById('filter_staff').value;
        var year = document.getElementById('filter_year').value;
        var quarter = document.getElementById('filter_quarter').value;
        if (division) params.set('division_id', division);
        if (staff) params.set('staff_id', staff);
        if (year) params.set('year', year);
        if (quarter) params.set('quarter', quarter);
        return params.toString();
    }

    function getBreakdownQuery() {
        var params = new URLSearchParams();
        var division = document.getElementById('filter_division').value;
        var year = document.getElementById('filter_year').value;
        var quarter = document.getElementById('filter_quarter').value;
        if (division) params.set('division_id', division);
        if (year) params.set('year', year);
        if (quarter) params.set('quarter', quarter);
        return params.toString();
    }

    function openBreakdownModal(staffId, staffName) {
        var modal = document.getElementById('staffBreakdownModal');
        if (!modal) return;
        document.getElementById('breakdownStaffName').textContent = staffName || 'Staff #' + staffId;
        document.getElementById('breakdownLoading').classList.remove('d-none');
        document.getElementById('breakdownContent').classList.add('d-none');
        document.getElementById('breakdownEmpty').classList.add('d-none');
        var url = breakdownBaseUrl.replace('__STAFF_ID__', staffId);
        var q = getBreakdownQuery();
        if (q) url += '?' + q;
        fetch(url, { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                document.getElementById('breakdownLoading').classList.add('d-none');
                if (!res.success || !res.activities || res.activities.length === 0) {
                    document.getElementById('breakdownEmpty').classList.remove('d-none');
                    window.currentBreakdownData = null;
                    document.getElementById('breakdownExportExcel').classList.add('d-none');
                    return;
                }
                window.currentBreakdownData = { staff_name: res.staff_name, activities: res.activities };
                document.getElementById('breakdownExportExcel').classList.remove('d-none');
                var tbody = document.getElementById('breakdownTableBody');
                tbody.innerHTML = '';
                res.activities.forEach(function(row, idx) {
                    var tr = document.createElement('tr');
                    var titleCell = row.show_url
                        ? '<a href="' + escapeHtml(row.show_url) + '" class="text-primary text-decoration-none breakdown-activity-title" target="_blank">' + escapeHtml(row.activity_title || '') + '</a>'
                        : '<span class="breakdown-activity-title">' + escapeHtml(row.activity_title || '') + '</span>';
                    tr.innerHTML = '<td class="text-center">' + (idx + 1) + '</td><td class="breakdown-activity-title">' + titleCell + '</td><td>' + escapeHtml(row.year_quarter || '') + '</td><td class="text-center">' + (row.travel_days || 0) + '</td>';
                    tbody.appendChild(tr);
                });
                document.getElementById('breakdownContent').classList.remove('d-none');
            })
            .catch(function() {
                document.getElementById('breakdownLoading').classList.add('d-none');
                document.getElementById('breakdownEmpty').classList.remove('d-none');
                document.getElementById('breakdownEmpty').textContent = 'Error loading activity breakdown.';
            });
        (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? new bootstrap.Modal(modal).show() : $(modal).modal('show');
    }

    function loadData() {
        var container = document.getElementById('table_container');
        container.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Loading…</p></div>';
        fetch(dataUrl + (getQuery() ? '?' + getQuery() : ''), {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success || !res.data) {
                container.innerHTML = '<div class="text-center py-4 text-muted">No data.</div>';
                return;
            }
            var rows = res.data;
            if (rows.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted">No data for the selected filters.</div>';
                return;
            }
            var table = '<div class="table-responsive"><table class="table table-hover table-striped mb-0"><thead><tr class="table-success">' +
                '<th class="text-center" style="width: 3rem;">#</th><th>Staff Name</th><th>Division</th><th>Year &amp; Quarter</th><th>Number of QM Activities</th><th>Approved Travel Days</th></tr></thead><tbody>';
            rows.forEach(function(row, idx) {
                var staffNameCell = '<a href="#" class="staff-breakdown-link text-primary text-decoration-none" data-staff-id="' + (row.staff_id || '') + '" data-staff-name="' + escapeHtml(row.staff_name || '') + '">' + escapeHtml(row.staff_name || '') + '</a>';
                table += '<tr><td class="text-center">' + (idx + 1) + '</td><td>' + staffNameCell + '</td><td>' + escapeHtml(row.division_name || '') + '</td><td>' + escapeHtml(row.year_quarter || '') + '</td><td>' + (row.activity_count || 0) + '</td><td>' + (row.approved_travel_days || 0) + '</td></tr>';
            });
            table += '</tbody></table></div>';
            container.innerHTML = table;
            container.querySelectorAll('.staff-breakdown-link').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var staffId = this.getAttribute('data-staff-id');
                    var staffName = this.getAttribute('data-staff-name');
                    if (staffId) openBreakdownModal(staffId, staffName);
                });
            });
        })
        .catch(function() {
            container.innerHTML = '<div class="text-center py-4 text-danger">Error loading data.</div>';
        });
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function downloadBreakdownExcel() {
        var data = window.currentBreakdownData;
        if (!data || !data.activities || data.activities.length === 0) return;
        var BOM = '\uFEFF';
        var header = ['#', 'Activity title', 'Year & Quarter', 'Travel days'];
        var rows = data.activities.map(function(row, idx) {
            return [idx + 1, (row.activity_title || '').replace(/"/g, '""'), row.year_quarter || '', row.travel_days || 0];
        });
        var csv = header.map(function(h) { return '"' + h + '"'; }).join(',') + '\r\n' +
            rows.map(function(r) { return r.map(function(c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(','); }).join('\r\n');
        var blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'activity_breakdown_' + (data.staff_name || 'staff').replace(/\s+/g, '_') + '_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    document.getElementById('breakdownExportExcel').addEventListener('click', function(e) {
        e.preventDefault();
        downloadBreakdownExcel();
    });

    document.getElementById('export_excel').addEventListener('click', function(e) {
        e.preventDefault();
        window.location.href = excelUrl + (getQuery() ? '?' + getQuery() : '');
    });
    document.getElementById('export_pdf').addEventListener('click', function(e) {
        e.preventDefault();
        window.open(pdfUrl + (getQuery() ? '?' + getQuery() : ''), '_blank');
    });
    document.getElementById('btn_apply').addEventListener('click', loadData);
    document.getElementById('btn_reset').addEventListener('click', function() {
        if (typeof $ !== 'undefined') {
            $('#filter_division').val('').trigger('change');
            $('#filter_staff').val('').trigger('change');
        }
        document.getElementById('filter_year').value = '{{ $currentYear ?? date("Y") }}';
        document.getElementById('filter_quarter').value = '';
        loadData();
    });

    loadData();
})();
</script>
@endpush
@endsection
