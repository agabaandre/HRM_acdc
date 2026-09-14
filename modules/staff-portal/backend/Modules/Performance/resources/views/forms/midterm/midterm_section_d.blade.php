<h4 class="mt-4">D. Competencies</h4>
<p class="text-muted">All staff members shall be rated against AU Values and Core/Functional Competencies. Staff with managerial responsibilities will also be rated on Leadership Competencies.</p>
@foreach ($competencyLabels as $catKey => $catLabel)
  @if (! empty($competencyGroups[$catKey]))
    <div class="mt-4">
      <h5 class="fw-bold">{{ $catLabel }}</h5>
      <table class="table table-bordered table-sm">
        <thead class="table-light text-center">
          <tr>
            <th style="width: 35%;">Competency</th>
            @for ($i = 5; $i >= 1; $i--)
              <th>{{ $i }}</th>
            @endfor
          </tr>
        </thead>
        <tbody>
          @foreach ($competencyGroups[$catKey] as $item)
            @php $key = 'competency_'.$item->id; @endphp
            <tr>
              <td><strong>{{ $item->id }}. {{ $item->description }}</strong><br><small class="text-muted">{{ $item->annotation }}</small></td>
              @for ($i = 5; $i >= 1; $i--)
                <td class="text-center">
                  <input type="radio" class="form-check-input"
                         wire:model="midtermCompetency.{{ $key }}"
                         value="{{ $i }}"
                         @disabled(str_contains($midreadonly, 'readonly'))>
                  <div class="small">{{ $item->{'score_'.$i} }}</div>
                </td>
              @endfor
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endforeach
