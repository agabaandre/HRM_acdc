<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Business Management System'))</title>

    @include('partials.css')
    <!-- @stack('styles') -->
</head>

<body>
    @include('partials.header')

    <!-- Include the breadcrumbs partial -->
    @include('partials.breadcrumbs')


            <!-- Content Area -->
            @yield('content')
        </div>
    </div>
    <!--end page wrapper -->

    @include('partials.footer')
    @stack('scripts')
</body>

</html>