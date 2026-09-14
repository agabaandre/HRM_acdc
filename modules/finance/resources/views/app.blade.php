<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ config('app.name', 'Finance') }}</title>

    @php
        $assetsBase = rtrim((string) config('finance.assets_base_url', ''), '/');
        if ($assetsBase === '') {
            $assetsBase = rtrim((string) env('BASE_URL', 'http://localhost/staff'), '/') . '/apm';
        }
        $staffBase = rtrim((string) env('BASE_URL', 'http://localhost/staff'), '/');
    @endphp

    <link rel="icon" href="{{ $assetsBase }}/assets/images/au_emblem.png" type="image/png" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="{{ $assetsBase }}/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ $assetsBase }}/assets/css/bootstrap-extended.css" rel="stylesheet">
    <link href="{{ $assetsBase }}/assets/css/app.css" rel="stylesheet">
    <link href="{{ $assetsBase }}/assets/css/header-colors.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ $staffBase }}/assets/css/cbp-modules-nav.css">

    @viteReactRefresh
    @vite(['resources/js/app.jsx', 'resources/css/app.css'])
    @inertiaHead
</head>
<body>
    @inertia
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
