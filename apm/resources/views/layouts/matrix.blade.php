<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - @yield('title', 'Quarterly Travel Matrix')</title>

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        .matrix-header {
            background: #f8f9fa;
            padding: 1rem;
            margin-bottom: 2rem;
            border-bottom: 3px solid #119A48;
        }

        .card {
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            border: none;
        }

        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 1rem;
        }

        .btn-success {
            background-color: #119A48;
            border-color: #119A48;
        }

        .btn-success:hover {
            background-color: #0d7537;
            border-color: #0d7537;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .form-label {
            font-weight: 600;
            color: #495057;
        }

        .key-result-area {
            background: #f8f9fa;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .remove-area {
            color: #dc3545;
            cursor: pointer;
        }

        .add-area {
            color: #119A48;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-light">
    <div class="matrix-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">@yield('header', 'Quarterly Travel Matrix')</h1>
                @yield('header-actions')
            </div>
        </div>
    </div>

    <div class="container mb-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        $(document).ready(function() {
            // Initialize Select2 Elements
            $('.select2').select2({
                theme: 'bootstrap-5'
            });

            // Initialize Flatpickr
            $('.datepicker').flatpickr({
                dateFormat: "Y-m-d",
                allowInput: true
            });

            // Initialize tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))
        });
    </script>

    @stack('scripts')
</body>
</html>
