<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-logged-in" content="{{ !empty(session('user')) ? 'true' : 'false' }}">
    <meta name="api-base-url" content="{{ url('/api') }}">
    <title>@yield('title', config('app.name', 'Business Management System'))</title>

    @include('layouts.partials.css')
    @if(env('SHOW_QUOTES', true))
    <style>
    /* Quote Button Styles */
    .quote-button {
        background: linear-gradient(135deg, #119a48, #1bb85a) !important;
        color: white !important;
        border: none !important;
        border-radius: 20px !important;
        padding: 6px 12px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(17, 154, 72, 0.3) !important;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 4px;
        animation: pulse 2s infinite;
        white-space: nowrap;
    }

    .quote-button:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(17, 154, 72, 0.4) !important;
        background: linear-gradient(135deg, #0d7a3a, #119a48) !important;
    }

    .quote-button i {
        font-size: 0.9rem;
    }

    .quote-button-text {
        white-space: nowrap;
    }

    @keyframes pulse {
        0% { box-shadow: 0 2px 8px rgba(17, 154, 72, 0.3); }
        50% { box-shadow: 0 2px 10px rgba(17, 154, 72, 0.5); }
        100% { box-shadow: 0 2px 8px rgba(17, 154, 72, 0.3); }
    }

    /* Quote Modal Styles */
    .quote-modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease;
    }

    .quote-modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .quote-modal-content {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
        animation: slideIn 0.4s ease;
        position: relative;
    }

    .quote-modal-content::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #119a48, #1bb85a);
    }

    .quote-modal-header {
        background: linear-gradient(135deg, rgba(17, 154, 72, 0.1), rgba(27, 184, 90, 0.05));
        padding: 20px;
        border-bottom: 1px solid rgba(17, 154, 72, 0.2);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .quote-modal-header h5 {
        margin: 0;
        color: #119a48;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .quote-close {
        background: none;
        border: none;
        font-size: 24px;
        color: #6c757d;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s ease;
    }

    .quote-close:hover {
        background-color: rgba(17, 154, 72, 0.1);
        color: #119a48;
    }

    .quote-modal-body {
        padding: 30px;
        text-align: center;
        position: relative;
    }

    .quote-progress-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background-color: rgba(17, 154, 72, 0.2);
        overflow: hidden;
    }

    .quote-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #119a48, #1bb85a);
        width: 100%;
        animation: progressCountdown 4s linear forwards;
    }

    .quote-text {
        font-size: 1.2rem;
        font-style: italic;
        color: #333;
        margin: 0;
        line-height: 1.6;
        font-weight: 400;
        position: relative;
    }

    .quote-text::before {
        content: '"';
        font-size: 4rem;
        color: #119a48;
        position: absolute;
        top: -20px;
        left: -30px;
        opacity: 0.3;
        font-family: serif;
    }

    .quote-text::after {
        content: '"';
        font-size: 4rem;
        color: #119a48;
        position: absolute;
        bottom: -30px;
        right: -20px;
        opacity: 0.3;
        font-family: serif;
    }

    /* Animations */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideIn {
        from {
            transform: translateY(-50px) scale(0.9);
            opacity: 0;
        }
        to {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateY(0) scale(1);
            opacity: 1;
        }
        to {
            transform: translateY(-50px) scale(0.9);
            opacity: 0;
        }
    }

    @keyframes progressCountdown {
        from { width: 100%; }
        to { width: 0%; }
    }


    /* Responsive adjustments */
    @media (max-width: 768px) {
        .quote-button {
            padding: 4px 8px !important;
            font-size: 0.7rem !important;
        }
        
        .quote-button-text {
            display: none;
        }
        
        .quote-button i {
            font-size: 0.8rem;
        }
        
        .quote-modal-content {
            width: 95%;
            margin: 20px;
        }
        
        .quote-modal-body {
            padding: 20px;
        }
        
        .quote-text {
            font-size: 1rem;
        }
        
        .quote-text::before,
        .quote-text::after {
            font-size: 3rem;
        }
        
        .quote-text::before {
            top: -15px;
            left: -20px;
        }
        
        .quote-text::after {
            bottom: -25px;
            right: -15px;
        }
    }
    </style>
    @endif
    <!-- @stack('styles') -->
</head>

<body class="{{ !empty(session('user')) ? 'logged-in' : '' }}">
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
    
    @if(env('SHOW_QUOTES', true))
    <script>
    let quoteModalTimeout;

    function showQuoteModal() {
        const modal = document.getElementById('quoteModal');
        const progressFill = modal.querySelector('.quote-progress-fill');
        
        modal.classList.add('show');
        modal.style.display = 'flex';
        
        // Reset progress bar animation
        if (progressFill) {
            progressFill.style.animation = 'none';
            progressFill.offsetHeight; // Trigger reflow
            progressFill.style.animation = 'progressCountdown 4s linear forwards';
        }
        
        // Clear any existing timeout
        if (quoteModalTimeout) {
            clearTimeout(quoteModalTimeout);
        }
        
        // Auto-hide after 4 seconds
        quoteModalTimeout = setTimeout(() => {
            hideQuoteModal();
        }, 4000);
    }

    function hideQuoteModal() {
        const modal = document.getElementById('quoteModal');
        modal.classList.remove('show');
        
        // Add slide out animation
        modal.style.animation = 'slideOut 0.3s ease';
        
        setTimeout(() => {
            modal.style.display = 'none';
            modal.style.animation = '';
        }, 300);
        
        // Clear timeout if modal is manually closed
        if (quoteModalTimeout) {
            clearTimeout(quoteModalTimeout);
        }
    }


    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('quoteModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    hideQuoteModal();
                }
            });
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideQuoteModal();
        }
    });
    </script>
    @endif
    
    @stack('scripts')
</body>
