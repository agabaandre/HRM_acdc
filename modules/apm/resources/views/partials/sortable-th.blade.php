@php
  $column = $column ?? '';
  $label = $label ?? '';
  $sortColumn = $sortColumn ?? null;
  $sortDir = $sortDir ?? 'asc';
  $class = $class ?? '';
  $style = $style ?? '';
  $baseStyle = 'cursor: pointer; user-select: none;';
  $thStyle = $style ? $baseStyle . ' ' . $style : $baseStyle;
  $icon = ($sortColumn !== $column)
    ? '<span class="text-muted opacity-50 small">&#8645;</span>'
    : ($sortDir === 'asc' ? ' <span class="small">&#9650;</span>' : ' <span class="small">&#9660;</span>');
@endphp
<th class="sortable-th no-print {{ $class }}" data-sort-column="{{ $column }}" style="{{ $thStyle }}" title="Click to sort">{!! $label !!} {!! $icon !!}</th>
