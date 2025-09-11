<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-logged-in" content="{{ Auth::check() ? 'true' : 'false' }}">
    <meta name="api-base-url" content="{{ url('/api') }}">
    <title>@yield('title', config('app.name', 'Business Management System'))</title>

    @include('layouts.partials.css')
    <!-- @stack('styles') -->
</head>

<body>
    @include('layouts.partials.header')

    <!-- Include the breadcrumbs partial -->
    @include('layouts.partials.breadcrumbs')
    @include('layouts.partials.nav')

            <!-- Content Area -->
            @yield('content')
        </div>
    </div>
    <!--end page wrapper -->

    @include('layouts.partials.footer')
    
    <!-- Session Expiry Modals -->
    @include('components.session-expiry-modal')
    
    <!-- Session Monitor Script -->
    <script src="{{ asset('js/session-monitor.js') }}?v={{ time() }}"></script>
    
    @stack('scripts')
</body>

</html>