<p class="small text-muted mb-2 no-print">Showing {{ $memoList->firstItem() ?? 0 }}–{{ $memoList->lastItem() ?? 0 }} of <strong>{{ $memoList->total() }}</strong> memo(s)</p>
<div class="table-responsive">
	<table class="table table-bordered table-hover mb-0 reports-table">
		<thead class="table-success">
			<tr>
				<th>#</th>
				<th>Document #</th>
				<th style="width: 25%; min-width: 25%;">Title</th>
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
					$memoUrl = $memo->is_single_memo
						? route('activities.single-memos.show', $memo)
						: route('matrices.activities.show', [$memo->matrix, $memo]);
				@endphp
				<tr>
					<td>{{ $memoList->firstItem() + $idx }}</td>
					<td><a href="{{ $memoUrl }}" class="text-decoration-none"><code>{{ $memo->document_number ?? '—' }}</code></a></td>
					<td class="reports-memo-title" style="width: 25%; min-width: 25%;"><a href="{{ $memoUrl }}" class="text-decoration-none">{{ $memo->activity_title ?? '—' }}</a></td>
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
	<div class="card-footer py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
		<small class="text-muted">Showing {{ $memoList->firstItem() ?? 0 }}–{{ $memoList->lastItem() ?? 0 }} of {{ $memoList->total() }}</small>
		{{ $memoList->links() }}
	</div>
@endif
