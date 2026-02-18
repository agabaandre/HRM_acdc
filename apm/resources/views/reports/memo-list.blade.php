@extends('layouts.app')

@section('title', 'Reports – Memo list')
@section('header', 'Memo list (details)')

@push('styles')
<style>
.reports-memo-title { word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word; }
.reports-table th, .reports-table td { padding: 0.5rem 0.4rem; vertical-align: middle; }
.reports-table th { font-size: 0.8rem; white-space: nowrap; }
.reports-page .card .card-header { padding: 0.4rem 0.75rem; font-size: 0.9rem; }
.reports-page .card .card-body { padding: 0.5rem 0.75rem; }
@media print {
	.no-print { display: none !important; }
	body * { visibility: hidden; }
	#memo_list_container, #memo_list_container * { visibility: visible; }
	#memo_list_container { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>
@endpush

@section('content')
<div class="container-fluid reports-page">
	<div class="d-flex align-items-center gap-2 mb-2 no-print">
		<a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Reports</a>
	</div>

	<div class="card shadow-sm mb-3 no-print">
		<div class="card-header bg-light py-2 d-flex align-items-center gap-2">
			<i class="bx bx-chart-bar text-success"></i>
			<strong>Filters</strong>
		</div>
		<div class="card-body">
			<div class="row g-3 align-items-end">
				<div class="col-md-2">
					<label class="form-label small">Division</label>
					<select id="filter_division" class="form-select form-select-sm report-division-select" style="width: 100%;">
						<option value="">All divisions</option>
						@foreach($divisions as $d)
							<option value="{{ $d->id }}" {{ request('division') == $d->id ? 'selected' : '' }}>{{ $d->division_name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Year</label>
					<select id="filter_year" class="form-select form-select-sm">
						<option value="all" {{ ($filterYear ?? '') === 'all' ? 'selected' : '' }}>All years</option>
						<option value="{{ $currentYear }}" {{ (string)($filterYear ?? '') === (string)$currentYear ? 'selected' : '' }}>{{ $currentYear }}</option>
						@foreach($years as $y)
							@if((string)$y !== (string)$currentYear)
								<option value="{{ $y }}" {{ (string)($filterYear ?? '') === (string)$y ? 'selected' : '' }}>{{ $y }}</option>
							@endif
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Quarter</label>
					<select id="filter_quarter" class="form-select form-select-sm">
						<option value="">All quarters</option>
						@foreach($quarters as $q)
							<option value="{{ $q }}" {{ request('quarter') == $q ? 'selected' : '' }}>{{ $q }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Memo type</label>
					<select id="filter_memo_type" class="form-select form-select-sm">
						<option value="">All types</option>
						@foreach($memoTypes as $code => $label)
							<option value="{{ $code }}" {{ request('memo_type') == $code ? 'selected' : '' }}>{{ $label }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Status</label>
					<select id="filter_status" class="form-select form-select-sm">
						<option value="">All statuses</option>
						<option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
						<option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
						<option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
						<option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
						<option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
					</select>
				</div>
				<div class="col-md-2">
					<button type="button" id="btn_apply_list" class="btn btn-success btn-sm">Apply</button>
					<button type="button" id="btn_reset_list" class="btn btn-outline-secondary btn-sm">Reset</button>
				</div>
			</div>
		</div>
	</div>

	<div class="card shadow-sm">
		<div class="card-header bg-light py-2 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2 no-print">
			<strong class="text-success"><i class="bx bx-chart-bar me-1"></i> List of memos</strong>
			<div class="d-flex gap-2">
				<a href="#" id="memo_list_export_excel" class="btn btn-success btn-sm"><i class="bx bx-download me-1"></i> Export to Excel</a>
				<button type="button" id="memo_list_print" class="btn btn-outline-success btn-sm"><i class="bx bx-printer me-1"></i> Print / PDF</button>
			</div>
		</div>
		<div class="card-body p-0">
			<div id="memo_list_container">
				<div class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Loading…</p></div>
			</div>
		</div>
	</div>
</div>

@push('scripts')
<script>
$(function() {
	$('#filter_division').select2({ theme: 'bootstrap4', width: '100%', placeholder: 'All divisions', allowClear: true });
});
</script>
<script>
(function() {
	var dataUrl = '{{ route('reports.memo-list.data') }}';
	var exportUrl = '{{ route('reports.memo-list.export.excel') }}';
	var currentYear = '{{ $currentYear }}';
	var usedInitialUrl = false;

	function getQuery(page) {
		page = page || 1;
		var params = new URLSearchParams();
		params.set('page', page);
		var division = ($('#filter_division').length && $('#filter_division').val()) ? $('#filter_division').val() : '';
		var year = document.getElementById('filter_year') ? document.getElementById('filter_year').value : '';
		var quarter = document.getElementById('filter_quarter') ? document.getElementById('filter_quarter').value : '';
		var memoType = document.getElementById('filter_memo_type') ? document.getElementById('filter_memo_type').value : '';
		var status = document.getElementById('filter_status') ? document.getElementById('filter_status').value : '';
		if (division) params.set('division', division);
		if (year) params.set('year', year);
		if (quarter) params.set('quarter', quarter);
		if (memoType) params.set('memo_type', memoType);
		if (status) params.set('status', status);
		return params.toString();
	}
	function getQueryFromPageUrl(page) {
		var urlParams = new URLSearchParams(window.location.search);
		var params = new URLSearchParams();
		params.set('page', String(page || 1));
		['division','year','quarter','memo_type','status'].forEach(function(k) {
			var v = urlParams.get(k);
			if (v) params.set(k, v);
		});
		return params.toString();
	}
	function getQueryForExport() {
		var p = getQuery(1);
		return p.replace(/^page=1&?/, '').replace(/&?page=1$/, '');
	}

	function loadList(page) {
		page = page || 1;
		var container = document.getElementById('memo_list_container');
		container.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Loading…</p></div>';
		var query;
		if (!usedInitialUrl && window.location.search) {
			query = getQueryFromPageUrl(page);
			usedInitialUrl = true;
		} else {
			query = getQuery(page);
		}
		if (!query) query = 'page=1';
		else if (query === 'page=' + page && page === 1) query += '&year=all';
		fetch(dataUrl + '?' + query, {
			method: 'GET',
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
		})
		.then(function(r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		})
		.then(function(data) {
			if (data && data.debug) console.log('Memo list server debug:', data.debug);
			container.innerHTML = (data && data.html !== undefined) ? data.html : '<div class="text-center py-4 text-muted">No memos found.</div>';
			attachPaginationHandlers();
		})
		.catch(function(err) {
			container.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Check console.</div>';
			console.error('Memo list fetch error:', err);
		});
	}

	function attachPaginationHandlers() {
		var container = document.getElementById('memo_list_container');
		if (!container) return;
		container.querySelectorAll('.pagination a').forEach(function(link) {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				var href = this.getAttribute('href');
				if (!href) return;
				var match = href.match(/page=(\d+)/);
				var page = match ? match[1] : 1;
				loadList(page);
			});
		});
	}

	document.getElementById('memo_list_export_excel').addEventListener('click', function(e) {
		e.preventDefault();
		window.location.href = exportUrl + (getQueryForExport() ? '?' + getQueryForExport() : '');
	});
	document.getElementById('memo_list_print').addEventListener('click', function() { window.print(); });

	document.getElementById('btn_apply_list').addEventListener('click', function() { loadList(1); });
	document.getElementById('btn_reset_list').addEventListener('click', function() {
		$('#filter_division').val('').trigger('change');
		document.getElementById('filter_year').value = 'all';
		document.getElementById('filter_quarter').value = '';
		document.getElementById('filter_memo_type').value = '';
		document.getElementById('filter_status').value = '';
		loadList(1);
	});
	loadList(1);
})();
</script>
@endpush
@endsection
