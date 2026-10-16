@php
    $isCbpHome = request()->routeIs('core.home');
    $segment = request()->segment(1) ?? 'home';
    $pageTitle = $title ?? ucwords(str_replace('-', ' ', $segment));
@endphp
@if (! $isCbpHome)
    <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-2">
        <div class="breadcrumb-title pe-3">
            <a href="{{ url('/'.$segment) }}" style="color:#947645;">{{ ucwords($segment) }}</a>
        </div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
                </ol>
            </nav>
        </div>
    </div>
@endif
