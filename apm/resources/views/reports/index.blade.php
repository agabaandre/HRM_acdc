@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Reports')

@section('content')
<div class="container-fluid">
	<h5 class="mb-4">Division memo counts and list of memos</h5>

	{{-- Filters --}}
	<form method="get" action="{{ route('reports.index') }}" class="card shadow-sm mb-4">
		<div class="card-header bg-light py-2">
			<strong>Filters</strong>
		</div>
		<div class="card-body">
			<div class="row g-3">
				<div class="col-md-2">
					<label class="form-label small">Division</label>
					<select name="division" class="form-select form-select-sm">
						<option value="">All divisions</option>
						@foreach($divisions as $d)
							<option value="{{ $d->id }}" {{ (int)($filterDivision ?? 0) === (int)$d->id ? 'selected' : '' }}>{{ $d->division_name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Year</label>
					<select name="year" class="form-select form-select-sm">
						<option value="">All years</option>
						@foreach($years as $y)
							<option value="{{ $y }}" {{ (int)($filterYear ?? 0) === (int)$y ? 'selected' : '' }}>{{ $y }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Quarter</label>
					<select name="quarter" class="form-select form-select-sm">
						<option value="">All quarters</option>
						@foreach($quarters as $q)
							<option value="{{ $q }}" {{ ($filterQuarter ?? '') === $q ? 'selected' : '' }}>{{ $q }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Memo type</label>
					<select name="memo_type" class="form-select form-select-sm">
						<option value="">All types</option>
						@foreach($requestTypes as $rt)
							<option value="{{ $rt->id }}" {{ (int)($filterMemoType ?? 0) === (int)$rt->id ? 'selected' : '' }}>{{ $rt->name }}</option>
						@endforeach
					</select>
				</div>
				<div class="col-md-2">
					<label class="form-label small">Status (list only)</label>
					<select name="status" class="form-select form-select-sm">
						<option value="">All statuses</option>
						<option value="approved" {{ ($filterStatus ?? '') === 'approved' ? 'selected' : '' }}>Approved</option>
						<option value="pending" {{ ($filterStatus ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
						<option value="returned" {{ ($filterStatus ?? '') === 'returned' ? 'selected' : '' }}>Returned</option>
						<option value="rejected" {{ ($filterStatus ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
						<option value="draft" {{ ($filterStatus ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
					</select>
				</div>
				<div class="col-md-2 d-flex align-items-end gap-2">
					<button type="submit" class="btn btn-primary btn-sm">Apply</button>
					<a href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
				</div>
			</div>
		</div>
	</form>

	{{-- Report 1: Division memo counts (breakdown by status) --}}
	<div class="card shadow-sm mb-4">
		<div class="card-header bg-primary text-white py-2">
			<strong>Division memo counts</strong> <span class="small opacity-90">(Approved / Pending / Returned)</span>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-bordered table-hover mb-0">
					<thead class="table-light">
						<tr>
							<th>Division</th>
							<th class="text-center">Approved</th>
							<th class="text-center">Pending</th>
							<th class="text-center">Returned</th>
							<th class="text-center">Total</th>
						</tr>
					</thead>
					<tbody>
						@forelse($counts as $divisionId => $row)
							@php
								$division = $divisionsForCounts->get($divisionId);
								$name = $division ? $division->division_name : ('Division #' . $divisionId);
							@endphp
							<tr>
								<td>{{ $name }}</td>
								<td class="text-center">{{ (int)($row->approved_count ?? 0) }}</td>
								<td class="text-center">{{ (int)($row->pending_count ?? 0) }}</td>
								<td class="text-center">{{ (int)($row->returned_count ?? 0) }}</td>
								<td class="text-center fw-bold">{{ (int)($row->total_count ?? 0) }}</td>
							</tr>
						@empty
							<tr><td colspan="5" class="text-muted text-center py-4">No data for the selected filters.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
		</div>
	</div>

	{{-- Report 2: List of memos --}}
	<div class="card shadow-sm">
		<div class="card-header bg-info text-white py-2">
			<strong>List of memos</strong>
		</div>
		<div class="card-body p-0">
			<div class="table-responsive">
				<table class="table table-bordered table-hover mb-0">
					<thead class="table-light">
						<tr>
							<th>#</th>
							<th>Document #</th>
							<th>Title</th>
							<th>Division</th>
							<th>Type</th>
							<th>Year / Quarter</th>
							<th class="text-center">Status</th>
							<th>Date range</th>
							<th>Responsible person</th>
						</tr>
					</thead>
					<tbody>
						@forelse($memoList as $idx => $memo)
							@php
								$divisionId = $memo->division_id ?? $memo->matrix->division_id ?? null;
								$divisionName = $divisionId ? ($divisions->firstWhere('id', $divisionId)->division_name ?? 'N/A') : 'N/A';
								$resp = $memo->responsiblePerson;
								$respName = $resp ? trim(($resp->fname ?? '') . ' ' . ($resp->lname ?? '')) : 'N/A';
								$dateFrom = $memo->date_from ? \Carbon\Carbon::parse($memo->date_from)->format('d M Y') : '—';
								$dateTo = $memo->date_to ? \Carbon\Carbon::parse($memo->date_to)->format('d M Y') : '—';
							@endphp
							@php
								$memoUrl = $memo->is_single_memo
									? route('activities.single-memos.show', $memo)
									: route('matrices.activities.show', [$memo->matrix, $memo]);
							@endphp
							<tr>
								<td>{{ $memoList->firstItem() + $idx }}</td>
								<td><a href="{{ $memoUrl }}" class="text-decoration-none"><code>{{ $memo->document_number ?? '—' }}</code></a></td>
								<td><a href="{{ $memoUrl }}" class="text-decoration-none">{{ $memo->activity_title ?? '—' }}</a></td>
								<td>{{ $divisionName }}</td>
								<td>{{ $memo->requestType->name ?? '—' }} @if($memo->is_single_memo)<span class="badge bg-secondary">Single</span>@endif</td>
								<td>{{ $memo->matrix_year ?? '—' }} {{ $memo->matrix_quarter ?? '' }}</td>
								<td class="text-center">
									@if(($memo->overall_status ?? '') === 'approved')
										<span class="badge bg-success">Approved</span>
									@elseif(($memo->overall_status ?? '') === 'pending')
										<span class="badge bg-warning text-dark">Pending</span>
									@elseif(in_array($memo->overall_status ?? '', ['returned', 'rejected']))
										<span class="badge bg-danger">Returned</span>
									@else
										<span class="badge bg-secondary">{{ ucfirst($memo->overall_status ?? '—') }}</span>
									@endif
								</td>
								<td>{{ $dateFrom }} – {{ $dateTo }}</td>
								<td>{{ $respName }}</td>
							</tr>
						@empty
							<tr><td colspan="9" class="text-muted text-center py-4">No memos for the selected filters.</td></tr>
						@endforelse
					</tbody>
				</table>
			</div>
			@if($memoList->hasPages())
				<div class="card-footer py-2">
					{{ $memoList->links() }}
				</div>
			@endif
		</div>
	</div>
</div>
@endsection
