@props([
    'fname' => '',
    'lname' => '',
    'photo' => null,
    'size' => 'sm',
])

@php
    $sizes = [
        'sm' => ['w' => 40, 'h' => 40, 'fs' => 14],
        'md' => ['w' => 66, 'h' => 88, 'fs' => 24],
        'lg' => ['w' => 96, 'h' => 128, 'fs' => 32],
    ];
    $dim = $sizes[$size] ?? $sizes['sm'];
    $photoUrl = \App\Support\StaffPhoto::url($photo);
    $initials = \App\Support\StaffPhoto::initials($fname, $lname);
    $bg = \App\Support\StaffPhoto::backgroundColor($fname);
@endphp

@if ($photoUrl)
    <img src="{{ $photoUrl }}" alt="{{ trim($fname.' '.$lname) }}"
         class="staff-avatar-img rounded"
         style="width:{{ $dim['w'] }}px;height:{{ $dim['h'] }}px;object-fit:cover;cursor:pointer;border:1px solid #dee2e6;border-radius:0.5rem;"
         onclick="window.dispatchEvent(new CustomEvent('staff-photo-zoom', { detail: { url: '{{ $photoUrl }}' } }))">
@else
    <div class="d-inline-flex align-items-center justify-content-center text-white rounded"
         style="width:{{ $dim['w'] }}px;height:{{ $dim['h'] }}px;background-color:{{ $bg }};font-weight:600;font-size:{{ $dim['fs'] }}px;border-radius:0.5rem;">
        {{ $initials }}
    </div>
@endif
