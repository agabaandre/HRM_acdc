<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Staff Portal — Sign In | Africa CDC</title>
    <link rel="icon" href="{{ \App\Support\CbpAsset::url('images/africacdc_2.png') }}" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @livewireStyles
    @stack('styles')
    <style>
        :root {
            --primary-color: #119a48;
            --primary-dark: #0d7a3a;
            --primary-light: #1bb85a;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-image: url('{{ \App\Support\CbpAsset::url('images/bg_login.jpg') }}');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: #fff;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            min-height: 600px;
            display: flex;
            border-radius: 4px;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .login-right {
            flex: 1;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .logo-section {
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
        }

        .logo-section img {
            max-width: 200px;
            height: auto;
            filter: brightness(0) invert(1);
        }

        .welcome-text {
            position: relative;
            z-index: 2;
        }

        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .welcome-text p {
            font-size: 1.05rem;
            opacity: 0.92;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 420px;
                min-height: auto;
            }
            .login-left { padding: 40px 30px; }
            .login-right { padding: 40px 30px; }
            .welcome-text h1 { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left d-none d-md-flex">
            <div class="logo-section">
                <img src="{{ \App\Support\CbpAsset::url('images/AU_CDC_Logo-800.png') }}" alt="Africa CDC">
            </div>
            <div class="welcome-text">
                <h1>Welcome Back</h1>
                <p>Access your Africa CDC Central Business Platform account to manage staff operations and track activities efficiently.</p>
            </div>
        </div>
        <div class="login-right">
            {{ $slot }}
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    @livewireScripts
</body>
</html>
