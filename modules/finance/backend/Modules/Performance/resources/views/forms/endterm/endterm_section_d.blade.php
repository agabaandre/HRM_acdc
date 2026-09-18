<h4 class="mt-4">D. Competencies</h4>
@foreach ($competencyLabels as $catKey => $catLabel)
  @if (! empty($competencyGroups[$catKey]))
    <div class="mt-4">
      <h5 class="fw-bold">{{ $catLabel }}</h5>
      <table class="table table-bordered table-sm">
        <tbody>
          @foreach ($competencyGroups[$catKey] as $item)
            @php $key = 'competency_'.$item->id; @endphp
            <tr>
              <td><strong>{{ $item->id }}. {{ $item->description }}</strong></td>
              @for ($i = 5; $i >= 1; $i--)
                <td class="text-center">
                  <input type="radio" wire:model="endtermCompetency.{{ $key }}" value="{{ $i }}" @disabled(str_contains($endreadonly ?? '', 'readonly'))>
                </td>
              @endfor
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endforeach
