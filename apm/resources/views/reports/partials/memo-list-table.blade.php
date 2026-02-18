<p class="small text-muted mb-2 no-print">Showing {{ $memoList->firstItem() ?? 0 }}–{{ $memoList->lastItem() ?? 0 }} of <strong>{{ $memoList->total() }}</strong> memo(s)</p>
<div class="table-responsive">
	<table class="table table-bordered table-hover mb-0 reports-table">
		<thead class="table-success">
			<tr>
				<th>#</th>
				<th>Document #</th>
				<th style="width: 20%;">Title</th>
				<th>Division</th>
				<th>Type</th>
				<th>Year / Quarter</th>
				<th class="text-center">Status</th>
				<th>Date range</th>
				<th>Responsible person</th>
			</tr>
		</thead>
		<tbody>
			@forelse($memoList as $idx => $row)
				@php
					$divisionId = $row->division_id ?? null;
					$divisionName = $divisionId ? ($divisions->firstWhere('id', $divisionId)->division_name ?? 'N/A') : 'N/A';
					$dateFrom = $row->date_from ? \Carbon\Carbon::parse($row->date_from)->format('d M Y') : '—';
					$dateTo = $row->date_to ? \Carbon\Carbon::parse($row->date_to)->format('d M Y') : '—';
					$typeLabel = isset($memoTypeLabels) && isset($memoTypeLabels[$row->document_type]) ? $memoTypeLabels[$row->document_type] : $row->document_type;
					// Build show URL from document type and id
					if ($row->document_type === 'QM' && !empty($row->matrix_id)) {
						$showUrl = route('matrices.activities.show', [$row->matrix_id, $row->id]);
					} elseif ($row->document_type === 'SM') {
						$showUrl = route('activities.single-memos.show', $row->id);
					} elseif ($row->document_type === 'SPM') {
						$showUrl = route('special-memo.show', $row->id);
					} elseif ($row->document_type === 'NT') {
						$showUrl = route('non-travel.show', $row->id);
					} elseif ($row->document_type === 'CR') {
						$showUrl = route('change-requests.show', $row->id);
					} elseif ($row->document_type === 'SR') {
						$showUrl = route('service-requests.show', $row->id);
					} elseif ($row->document_type === 'ARF') {
						$showUrl = route('request-arf.show', $row->id);
					} else {
						$showUrl = '#';
					}
				@endphp
				<tr>
					<td>{{ $memoList->firstItem() + $idx }}</td>
					<td><a href="{{ $showUrl }}" class="text-decoration-none"><code>{{ $row->document_number ?? '—' }}</code></a></td>
					<td class="reports-memo-title" style="width: 20%; max-width: 20%;"><a href="{{ $showUrl }}" class="text-decoration-none">{{ $row->title ?? '—' }}</a></td>
					<td>{{ $divisionName }}</td>
					<td>{{ $typeLabel }}</td>
					<td>{{ $row->year ?? '—' }} {{ $row->quarter ?? '' }}</td>
					<td class="text-center">
						@if(($row->overall_status ?? '') === 'approved')
							<span class="badge bg-success">Approved</span>
						@elseif(($row->overall_status ?? '') === 'pending')
							<span class="badge bg-warning text-dark">Pending</span>
						@elseif(in_array($row->overall_status ?? '', ['returned', 'rejected']))
							<span class="badge bg-danger">Returned</span>
						@else
							<span class="badge bg-secondary">{{ ucfirst($row->overall_status ?? '—') }}</span>
						@endif
					</td>
					<td>{{ $dateFrom }} – {{ $dateTo }}</td>
					<td>{{ $row->responsible_person_name ?? 'N/A' }}</td>
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
