<h4 class="mt-4">B. Review of Performance Objectives</h4>
<p class="text-muted">Fill out the objectives, staff self-appraisal, and appraiser ratings. All objectives must total 100% weight.</p>
<div class="table-responsive">
  <table class="table table-bordered align-middle text-sm objective-table">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>Objective</th>
        <th>Timeline</th>
        <th>Deliverables & KPIs</th>
        <th>Weight (%)</th>
        <th>Staff Self Appraisal</th>
        <th>Appraiser's Rating</th>
      </tr>
    </thead>
    <tbody>
      @php $rowNum = 1; @endphp
      @foreach ($objectives as $i => $val)
        @if (trim((string) ($val['objective'] ?? '')) === '')
          @continue
        @endif
        <tr wire:key="mt-obj-{{ $i }}">
          <td>{{ $rowNum++ }}</td>
          <td><textarea class="form-control" rows="4" readonly disabled>{{ $val['objective'] }}</textarea></td>
          <td><input type="text" class="form-control" value="{{ $val['timeline'] ?? '' }}" readonly disabled></td>
          <td><textarea class="form-control" rows="4" readonly disabled>{{ $val['indicator'] ?? '' }}</textarea></td>
          <td><input type="number" class="form-control" value="{{ $val['weight'] ?? '' }}" readonly disabled></td>
          <td>
            <textarea wire:model="objectives.{{ $i }}.self_appraisal" class="form-control" rows="4" @disabled(str_contains($midreadonly, 'readonly'))></textarea>
          </td>
          <td>
            <select wire:model="objectives.{{ $i }}.appraiser_rating" class="form-select" @disabled(str_contains($midreadonly, 'readonly'))>
              <option value="">-- Select --</option>
              @foreach ([5 => '5 Exceptional', 4 => '4 Exceeds Expectations', 3 => '3 Meets Expectations', 2 => '2 Needs Improvement', 1 => '1 Unsatisfactory'] as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
