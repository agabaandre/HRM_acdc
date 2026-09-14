@props(['memo', 'copyRoute', 'label' => 'Copy'])

@if (function_exists('can_copy_memo') && can_copy_memo($memo))
    <a href="{{ $copyRoute }}"
       class="btn btn-sm btn-outline-info {{ $attributes->get('class') }}"
       onclick="return confirm('Create a draft copy of this memo? The copy will open for editing.');"
       title="Copy draft memo">
        <i class="bx bx-copy me-1"></i>{{ $label }}
    </a>
@endif
