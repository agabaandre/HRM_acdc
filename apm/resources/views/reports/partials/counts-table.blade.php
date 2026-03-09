<p class="small text-muted mb-2 no-print"><strong>Total: {{ $counts->count() }}</strong> division(s)</p>
<div class="table-responsive">
	<table class="table table-bordered table-hover mb-0">
		<thead class="table-success">
			<tr>
				<th style="width: 32px;">#</th>
				@php $sortColumn = $sortColumn ?? 'division'; $sortDir = $sortDir ?? 'asc'; @endphp
				@include('partials.sortable-th', ['column' => 'division', 'label' => 'Division', 'sortColumn' => $sortColumn, 'sortDir' => $sortDir])
				@include('partials.sortable-th', ['column' => 'approved_count', 'label' => 'Approved', 'sortColumn' => $sortColumn, 'sortDir' => $sortDir, 'class' => 'text-center'])
				@include('partials.sortable-th', ['column' => 'pending_count', 'label' => 'Pending', 'sortColumn' => $sortColumn, 'sortDir' => $sortDir, 'class' => 'text-center'])
				@include('partials.sortable-th', ['column' => 'returned_count', 'label' => 'Returned', 'sortColumn' => $sortColumn, 'sortDir' => $sortDir, 'class' => 'text-center'])
				@include('partials.sortable-th', ['column' => 'draft_count', 'label' => 'Draft', 'sortColumn' => $sortColumn, 'sortDir' => $sortDir, 'class' => 'text-center'])
				@include('partials.sortable-th', ['column' => 'total_count', 'label' => 'Total', 'sortColumn' => $sortColumn, 'sortDir' => $sortDir, 'class' => 'text-center'])
			</tr>
		</thead>
		<tbody>
			@forelse($counts as $idx => $row)
				@php
					$divisionId = $row->division_id ?? $idx ?? null;
					$division = $divisionsForCounts->get($divisionId);
					$name = $division ? $division->division_name : ('Division #' . $divisionId);
					$linkParams = [];
					if ($request->filled('division')) $linkParams['division'] = $request->division;
					if ($request->filled('year')) $linkParams['year'] = $request->year;
					if ($request->filled('quarter')) $linkParams['quarter'] = $request->quarter;
					if ($request->filled('memo_type')) $linkParams['memo_type'] = $request->memo_type;
					$linkParams['division'] = $divisionId;
					$linkUrl = $detailsUrl . '?' . http_build_query($linkParams);
				@endphp
				<tr>
					<td class="text-center">{{ $loop->iteration }}</td>
					<td><a href="{{ $linkUrl }}" class="text-decoration-none fw-semibold">{{ $name }}</a></td>
					<td class="text-center">{{ (int)($row->approved_count ?? 0) }}</td>
					<td class="text-center">{{ (int)($row->pending_count ?? 0) }}</td>
					<td class="text-center">{{ (int)($row->returned_count ?? 0) }}</td>
					<td class="text-center">{{ (int)($row->draft_count ?? 0) }}</td>
					<td class="text-center fw-bold"><a href="{{ $linkUrl }}" class="text-decoration-none">{{ (int)($row->total_count ?? 0) }}</a></td>
				</tr>
			@empty
				<tr><td colspan="7" class="text-muted text-center py-4">No data for the selected filters.</td></tr>
			@endforelse
		</tbody>
	</table>
</div>
