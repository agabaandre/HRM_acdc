<tr data-wb-viewer-row="{{ $idx }}">
    <td class="text-muted small text-center wb-viewer-row-num">{{ $rowNum ?? '' }}</td>
    <td>
        <select name="report_viewers[{{ $idx }}][staff_id]" class="form-select form-select-sm wb-viewer-staff-select select2 w-100">
            <option value="">— Staff —</option>
            @foreach($staffList as $s)
                <option value="{{ $s->staff_id }}" @selected((int)$viewerStaffId === (int)$s->staff_id)>
                    {{ trim((string) ($s->name ?? '')) }} @if($s->job_name) — {{ $s->job_name }} @endif
                </option>
            @endforeach
        </select>
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger wb-remove-viewer-row" title="Remove">&times;</button>
    </td>
</tr>
