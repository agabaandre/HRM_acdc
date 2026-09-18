@props(['status', 'phaseLabel' => ''])

@if ($status && ! ($status['open'] ?? true))
    <div {{ $attributes->merge(['class' => 'alert alert-warning']) }} role="alert">
        <strong>{{ $phaseLabel ?: 'Submissions' }} closed.</strong>
        {{ $status['message'] ?? '' }}
        <span class="d-block small mt-1">Configured window: {{ $status['label'] ?? '—' }}</span>
    </div>
@elseif ($status && ($status['open'] ?? false) && ! empty($status['label']) && ($status['start'] ?? null) !== null)
    <div {{ $attributes->merge(['class' => 'alert alert-info py-2 small']) }} role="status">
        <i class="fa-solid fa-calendar-day me-1"></i>
        {{ $status['message'] ?? '' }}
    </div>
@endif
