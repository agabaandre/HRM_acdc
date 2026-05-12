<tr data-wb-row="{{ $idx }}">
    <td>
        <select name="contributors[{{ $idx }}][staff_id]" class="form-select form-select-sm">
            <option value="">— Staff —</option>
            @foreach($staffList as $s)
                <option value="{{ $s->staff_id }}" @selected((int)$staffId === (int)$s->staff_id)>
                    {{ trim(($s->fname ?? '').' '.($s->lname ?? '')) }} @if($s->job_name) — {{ $s->job_name }} @endif
                </option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="contributors[{{ $idx }}][apm_division_id]" class="form-select form-select-sm">
            <option value="">— APM division —</option>
            @foreach($divisions as $d)
                <option value="{{ $d->id }}" @selected((int)$apmDiv === (int)$d->id)>{{ $d->division_name }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="contributors[{{ $idx }}][contribution_kind]" class="form-select form-select-sm wb-kind">
            <option value="division" @selected($kind === 'division')>Division</option>
            <option value="directorate" @selected($kind === 'directorate')>Directorate</option>
        </select>
    </td>
    <td class="wb-col-div">
        <select name="contributors[{{ $idx }}][contribution_division_id]" class="form-select form-select-sm">
            <option value="">— Division —</option>
            @foreach($divisions as $d)
                <option value="{{ $d->id }}" @selected((int)$contribDiv === (int)$d->id)>{{ $d->division_name }}</option>
            @endforeach
        </select>
    </td>
    <td class="wb-col-dir">
        <select name="contributors[{{ $idx }}][contribution_directorate_id]" class="form-select form-select-sm">
            <option value="">— Directorate —</option>
            @foreach($directorates as $dir)
                <option value="{{ $dir->id }}" @selected((int)$contribDir === (int)$dir->id)>{{ $dir->name }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="text" name="contributors[{{ $idx }}][display_name]" class="form-control form-control-sm" value="{{ $displayName ?? '' }}" maxlength="255" placeholder="As on PDF if different from system name">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger wb-remove-row" title="Remove">&times;</button>
    </td>
</tr>
