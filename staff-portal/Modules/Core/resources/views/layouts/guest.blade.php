<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Staff Portal' }} — Africa CDC</title>
    <link rel="icon" href="{{ \App\Support\CbpAsset::url('images/au_emblem.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('plugins/simplebar/css/simplebar.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('plugins/metismenu/css/metisMenu.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/bootstrap-extended.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/icons.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\CbpAsset::url('css/app.css') }}">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #f0f7f2 0%, #e8f5e9 100%);
            min-height: 100vh;
        }
        .staff-portal-login-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 8px 32px rgba(17, 154, 72, 0.12);
        }
        .btn-success {
            background-color: #119a48;
            border-color: #119a48;
        }
        .btn-success:hover {
            background-color: #0d7a3a;
            border-color: #0d7a3a;
        }
        .text-success { color: #119a48 !important; }
    </style>
    @livewireStyles
</head>
<body>
    <div class="d-flex align-items-center justify-content-center min-vh-100 py-4">
        {{ $slot }}
    </div>
    <script src="{{ \App\Support\CbpAsset::url('js/jquery.min.js') }}"></script>
    <script src="{{ \App\Support\CbpAsset::url('js/bootstrap.bundle.min.js') }}"></script>
    @livewireScripts
</body>
</html>
