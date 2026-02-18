@extends('layouts.app')

@section('title', 'Reports – Division memo counts')
@section('header', 'Division memo counts')

@section('content')
<div class="container-fluid">
	<div class="d-flex align-items-center gap-2 mb-3">
		<a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bx bx-arrow-back me-1"></i> Reports</a>
	</div>

	<div class="card shadow-sm mb-4">
		<div class="card-header bg-light py-2">
			<strong>Filters</strong>
		</div>
		<div class="card-body">
			<div class="row g-3 align-items-end">
				<div class="col-md-2">
					<label class="form-label small">Division</label>
					<select id="filter_division" class="form-select form-select-sm">
						<option value="">All divisions</option>
						@foreach($divisions as $d)
							<option value="{{ $d->id }}">{{ $d->division_name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Year</label>
					<select id="filter_year" class="form-select form-select-sm">
						<option value="{{ $currentYear }}">{{ $currentYear }}</option>
						<option value="all">All years</option>
						@foreach($years as $y)
							@if((string)$y !== (string)$currentYear)
								<option value="{{ $y }}">{{ $y }}</option>
							@endif
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Quarter</label>
					<select id="filter_quarter" class="form-select form-select-sm">
						<option value="">All quarters</option>
						@foreach($quarters as $q)
							<option value="{{ $q }}">{{ $q }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Memo type</label>
					<select id="filter_memo_type" class="form-select form-select-sm">
						<option value="">All types</option>
						@foreach($requestTypes as $rt)
							<option value="{{ $rt->id }}">{{ $rt->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<button type="button" id="btn_apply_counts" class="btn btn-success btn-sm">Apply</button>
					<button type="button" id="btn_reset_counts" class="btn btn-outline-secondary btn-sm">Reset</button>
				</div>
			</div>
		</div>
	</div>

	<div class="card shadow-sm">
		<div class="card-header bg-light py-2 border-bottom">
			<strong class="text-success"><i class="bx bx-pie-chart-alt me-1"></i> Division memo counts</strong> <span class="small text-muted">(Approved / Pending / Returned). Click division or total to open memo list.</span>
		</div>
		<div class="card-body p-0">
			<div id="counts_table_container">
				<div class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Loading…</p></div>
			</div>
		</div>
	</div>
</div>

@push('scripts')
<script>
(function() {
	var detailsUrl = '{{ route('reports.memo-list') }}';
	var dataUrl = '{{ route('reports.division-counts.data') }}';

	function getQuery() {
		var division = document.getElementById('filter_division').value;
		var year = document.getElementById('filter_year').value;
		var quarter = document.getElementById('filter_quarter').value;
		var memoType = document.getElementById('filter_memo_type').value;
		var params = new URLSearchParams();
		if (division) params.set('division', division);
		if (year) params.set('year', year);
		if (quarter) params.set('quarter', quarter);
		if (memoType) params.set('memo_type', memoType);
		return params.toString();
	}

	function loadCounts() {
		var container = document.getElementById('counts_table_container');
		container.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border" role="status"></div><p class="mt-2 mb-0">Loading…</p></div>';
		fetch(dataUrl + (getQuery() ? '?' + getQuery() : ''), {
			method: 'GET',
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			container.innerHTML = data.html || '<div class="text-center py-4 text-muted">No data.</div>';
		})
		.catch(function() {
			container.innerHTML = '<div class="text-center py-4 text-danger">Error loading data.</div>';
		});
	}

	document.getElementById('btn_apply_counts').addEventListener('click', loadCounts);
	document.getElementById('btn_reset_counts').addEventListener('click', function() {
		document.getElementById('filter_division').value = '';
		document.getElementById('filter_year').value = '{{ $currentYear }}';
		document.getElementById('filter_quarter').value = '';
		document.getElementById('filter_memo_type').value = '';
		loadCounts();
	});
	loadCounts();
})();
</script>
@endpush
@endsection
