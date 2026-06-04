<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="base-url" content="{{ config('staff-portal.base_url') }}">
    <title>{{ $title ?? 'Staff Portal' }} — Africa CDC CBP</title>
    <link rel="icon" href="{{ \App\Support\CbpAsset::url('images/au_emblem.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/icons.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/bootstrap-extended.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/semi-dark.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/header-colors.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/app.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/cbp-modules-nav.css') }}">
    <style>
        :root {
            --cbp-primary: #119a48;
            --cbp-topbar-height: 60px;
            --cbp-primary-nav-height: 60px;
            --cbp-header-offset: calc(var(--cbp-topbar-height) + var(--cbp-primary-nav-height));
        }
        .topbar { background: linear-gradient(135deg, #119a48, #1bb85a) !important; }
        /* app.css sets margin-top:120px for fixed topbar+nav; avoid extra padding (was doubling the gap). */
        .page-wrapper {
            margin-top: var(--cbp-header-offset);
            padding-top: 0;
            min-height: 100vh;
        }
        @media screen and (max-width: 1199px) {
            .page-wrapper { margin-top: var(--cbp-topbar-height); }
        }
        .page-content { padding-top: 1rem; }
        .nav-container.primary-menu .menu-title { margin-left: 6px; white-space: nowrap; }
        .nav-container.primary-menu .nav-link { flex-direction: row; gap: 4px; }
    </style>
    @livewireStyles
    @stack('styles')
</head>
<body>
    <div class="wrapper">
        @include('core::partials.header')

        @unless (request()->routeIs('core.home'))
            @include('core::partials.primary-nav')
        @endunless

        <div class="page-wrapper">
            <div class="page-content container-fluid">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}</div>
                @endif

                @unless (request()->routeIs('core.home', 'dashboard.index'))
                    @include('core::partials.breadcrumb')
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            {{ $slot }}
                        </div>
                    </div>
                @else
                    @if (request()->routeIs('dashboard.index'))
                        @include('core::partials.breadcrumb')
                    @endif
                    {{ $slot }}
                @endunless
            </div>
            @include('core::partials.footer')
        </div>
    </div>

    <script src="{{ \App\Support\CbpAsset::url('js/jquery.min.js') }}"></script>
    <script src="{{ \App\Support\CbpAsset::url('js/bootstrap.bundle.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toggle = document.querySelector('.mobile-toggle-menu');
            var wrapper = document.querySelector('.wrapper');
            if (toggle && wrapper) {
                toggle.addEventListener('click', function () {
                    wrapper.classList.toggle('toggled');
                });
            }
        });
    </script>
    @livewireScripts
    @stack('scripts')
</body>
</html>
