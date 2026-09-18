@props(['steps' => []])

<ol class="list-unstyled performance-workflow-timeline mb-0">
    @foreach ($steps as $step)
        <li class="performance-workflow-step performance-workflow-step--{{ $step['status'] }}">
            <span class="performance-workflow-marker" aria-hidden="true">
                @if ($step['status'] === 'done')
                    <i class="fa-solid fa-check"></i>
                @elseif ($step['status'] === 'current')
                    <i class="fa-solid fa-circle-dot"></i>
                @else
                    <i class="fa-regular fa-circle"></i>
                @endif
            </span>
            <div class="performance-workflow-body">
                <div class="fw-semibold">{{ $step['label'] }}</div>
                <div class="small text-muted">{{ $step['actor'] }} · {{ $step['hint'] }}</div>
            </div>
        </li>
    @endforeach
</ol>

@once
    @push('styles')
    <style>
        .performance-workflow-timeline { position: relative; padding-left: 0.25rem; }
        .performance-workflow-step {
            display: flex;
            gap: 0.75rem;
            padding-bottom: 1rem;
            position: relative;
        }
        .performance-workflow-step:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 0.65rem;
            top: 1.5rem;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .performance-workflow-step--done .performance-workflow-marker { color: #119a48; }
        .performance-workflow-step--current .performance-workflow-marker { color: #0d6efd; }
        .performance-workflow-marker { width: 1.3rem; flex-shrink: 0; text-align: center; margin-top: 0.1rem; }
    </style>
    @endpush
@endonce
