<div class="table-responsive">
	<table class="table table-bordered table-hover mb-0">
		<thead class="table-success">
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
					$linkParams = [];
					if ($request->filled('division')) $linkParams['division'] = $request->division;
					if ($request->filled('year')) $linkParams['year'] = $request->year;
					if ($request->filled('quarter')) $linkParams['quarter'] = $request->quarter;
					if ($request->filled('memo_type')) $linkParams['memo_type'] = $request->memo_type;
					$linkParams['division'] = $divisionId;
					$linkUrl = $detailsUrl . '?' . http_build_query($linkParams);
				@endphp
				<tr>
					<td><a href="{{ $linkUrl }}" class="text-decoration-none fw-semibold">{{ $name }}</a></td>
					<td class="text-center">{{ (int)($row->approved_count ?? 0) }}</td>
					<td class="text-center">{{ (int)($row->pending_count ?? 0) }}</td>
					<td class="text-center">{{ (int)($row->returned_count ?? 0) }}</td>
					<td class="text-center fw-bold"><a href="{{ $linkUrl }}" class="text-decoration-none">{{ (int)($row->total_count ?? 0) }}</a></td>
				</tr>
			@empty
				<tr><td colspan="5" class="text-muted text-center py-4">No data for the selected filters.</td></tr>
			@endforelse
		</tbody>
	</table>
</div>
