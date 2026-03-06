@extends('layouts.app')

@section('title', 'Reports – Staff Quarterly Travel Days')
@section('header', 'Staff Quarterly Travel Days')

@section('content')
<style>
.reports-page .card .card-header { padding: 0.4rem 0.75rem; font-size: 0.9rem; }
.reports-page .card .card-body { padding: 0.5rem 0.75rem; }
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
        This report shows travel days from activities (matrix and single memos) whose matrix is approved. Only matrices with overall status &quot;Approved&quot; are included. For each activity, if there is an approved change request, the most recent one&apos;s participant list and days are used; otherwise the activity&apos;s internal participants are used. &quot;Approved travel days&quot; is the sum of participant_days for each staff. Division is the staff member&apos;s division.
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
                table += '<tr><td class="text-center">' + (idx + 1) + '</td><td>' + escapeHtml(row.staff_name || '') + '</td><td>' + escapeHtml(row.division_name || '') + '</td><td>' + escapeHtml(row.year_quarter || '') + '</td><td>' + (row.activity_count || 0) + '</td><td>' + (row.approved_travel_days || 0) + '</td></tr>';
            });
            table += '</tbody></table></div>';
            container.innerHTML = table;
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
