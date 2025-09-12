<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
        <!-- Lobibox CSS -->
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/plugins/notifications/css/lobibox.min.css" />

    <!-- Favicon -->
    <link rel="icon" href="<?php echo base_url()?>assets/images/africacdc_2.png" type="image/png" />
    
    <title>Africa CDC Staff Tracker - Sign In</title>
    
    <style>
        :root {
            --primary-color: #119a48;
            --primary-dark: #0d7a3a;
            --primary-light: #1bb85a;
            --secondary-color: #f8f9fa;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-image: url('<?php echo base_url()?>assets/images/bg_login.jpg');
            background-repeat: no-repeat;
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            min-height: 600px;
            display: flex;
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
            color: white;
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
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
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
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }

        .login-form-container {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }

        .form-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .form-title h2 {
            color: var(--text-dark);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .form-title p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border: 2px solid var(--border-color);
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
            background: white;
        }

        .input-group {
            position: relative;
        }

        .input-group .form-control {
            padding-left: 50px;
        }

        .input-group-text {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            z-index: 3;
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            text-transform: none;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 20px;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(17, 154, 72, 0.3);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            padding: 15px 30px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(17, 154, 72, 0.3);
        }

        .btn-ms {
            background-image: url('<?php echo base_url()?>assets/images/ms-logo.png');
            background-size: 20px 20px;
            background-repeat: no-repeat;
            background-position: 20px center;
            padding-left: 50px;
        }

        .form-check {
            margin-bottom: 25px;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            background: white;
            padding: 0 20px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .footer p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .form-toggle {
            display: none;
            animation: slideDown 0.3s ease;
        }

        .form-toggle.active {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading {
            display: none;
        }

        .loading.show {
            display: inline-block;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 400px;
                min-height: auto;
            }

            .login-left {
                padding: 40px 30px;
            }

            .login-right {
                padding: 40px 30px;
            }

            .welcome-text h1 {
                font-size: 2rem;
            }

            .form-title h2 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .login-left,
            .login-right {
                padding: 30px 20px;
            }

            .welcome-text h1 {
                font-size: 1.8rem;
            }
        }

        /* Animation for form elements */
        .form-group {
            animation: fadeInUp 0.6s ease forwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Custom checkbox styling */
        .custom-checkbox {
            position: relative;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }

        .custom-checkbox input[type="checkbox"] {
            opacity: 0;
            position: absolute;
            width: 0;
            height: 0;
        }

        .checkmark {
            position: relative;
            height: 20px;
            width: 20px;
            background-color: #f8f9fa;
            border: 2px solid var(--border-color);
            margin-right: 12px;
            transition: all 0.3s ease;
        }

        .custom-checkbox input[type="checkbox"]:checked ~ .checkmark {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 6px;
            top: 2px;
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .custom-checkbox input[type="checkbox"]:checked ~ .checkmark:after {
            display: block;
        }

        /* Focus states for form groups */
        .form-group.focused .form-label {
            color: var(--primary-color);
            transform: translateY(-2px);
        }

        .form-group.focused .input-group-text {
            color: var(--primary-color);
        }

        /* Enhanced button animations */
        .btn {
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:active::before {
            width: 300px;
            height: 300px;
        }

        /* Improved Microsoft button styling */
        .btn-ms {
            background-color: #0078d4;
            border-color: #0078d4;
            color: white;
        }

        .btn-ms:hover {
            background-color: #106ebe;
            border-color: #106ebe;
            color: white;
        }

        /* Loading animation improvements */
        .loading {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .loading.show {
            opacity: 1;
        }

        /* Accessibility improvements */
        .form-control:focus,
        .btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .form-control {
                border-width: 3px;
            }
            
            .btn {
                border-width: 3px;
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Branding -->
        <div class="login-left">
            <div class="logo-section">
                <img src="<?php echo base_url(); ?>assets/images/AU_CDC_Logo-800.png" alt="Africa CDC Logo">
            </div>
            <div class="welcome-text">
                <h1>Welcome Back</h1>
                <p>Access your Africa CDC Central Business Platform account to manage staff operations and track activities efficiently.</p>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-form-container">
                <div class="form-title">
                    <h2>Sign In</h2>
                    <p>Choose your preferred sign-in method</p>
                </div>

                <!-- Microsoft SSO Button (Primary) -->
                <a href="<?= base_url('auth/login') ?>" class="btn btn-outline-primary btn-ms">
                    <i class="fab fa-microsoft"></i>
                    Sign in with Staf Email
                </a>

                <?php 
                // Check environment variable for alternative login, default to true
                $allowAlternativeLogin = getenv('ALLOW_ALTERNATIVE_LOGIN');
                $allowAlternativeLogin = $allowAlternativeLogin !== false ? filter_var($allowAlternativeLogin, FILTER_VALIDATE_BOOLEAN) : true;
                ?>
                <?php if ($allowAlternativeLogin): ?>
                <!-- Alternative Login Toggle -->
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="toggleForm">
                    <label class="form-check-label" for="toggleForm">
                        Use alternative sign-in method
                    </label>
         </div>

                <!-- Alternative Login Form -->
                <div id="signinForm" class="form-toggle">
                    <?php echo form_open_multipart(base_url('index.php/auth/cred_login'), array('id' => 'login', 'class' => 'login')); ?>

                    <!-- CSRF Protection -->
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" 
                           value="<?= $this->security->get_csrf_hash(); ?>" />
                    
                    <div class="form-group">
                        <label for="inputEmail" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" 
                                   id="inputEmail" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="Enter your email address" 
                                   required 
                                   autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="inputPassword" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   id="inputPassword" 
                                   name="password" 
                                   class="form-control" 
                                   placeholder="Enter your password" 
                                   required>
                        </div>
            </div>

                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        <span class="btn-text">Sign In</span>
                        <span class="loading">
                            <i class="fas fa-spinner fa-spin me-2"></i>Signing in...
                        </span>
                    </button>
            </form> 
                </div>
                <?php endif; ?>

                <!-- Footer -->
                <div class="footer">
                    <p>&copy; <?php echo date('Y'); ?> Africa CDC. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle alternative login form
            $('#toggleForm').change(function() {
                if (this.checked) {
                    $('#signinForm').addClass('active');
                } else {
                    $('#signinForm').removeClass('active');
                }
            });

            // Form submission with loading state
            $('#login').submit(function(e) {
                const submitBtn = $(this).find('button[type="submit"]');
                const btnText = submitBtn.find('.btn-text');
                const loading = submitBtn.find('.loading');
                
                // Show loading state
                btnText.hide();
                loading.addClass('show');
                submitBtn.prop('disabled', true);
            });

            // Add focus effects to form controls
            $('.form-control').on('focus', function() {
                $(this).closest('.form-group').addClass('focused');
            }).on('blur', function() {
                if ($(this).val() === '') {
                    $(this).closest('.form-group').removeClass('focused');
                }
            });

            // Check if form has values on load
            $('.form-control').each(function() {
                if ($(this).val() !== '') {
                    $(this).closest('.form-group').addClass('focused');
                }
            });
        });
    </script>

    <!-- Lobibox Notifications -->
    <script src="<?php echo base_url() ?>assets/plugins/notifications/js/lobibox.min.js"></script>
    <script src="<?php echo base_url() ?>assets/plugins/notifications/js/notifications.min.js"></script>
                  
    <?php if (!empty($this->session->flashdata('error'))): ?>
                      <script>
                          $(document).ready(function () {
                              Lobibox.notify('error', {
                pauseDelayOnHover: true,
                continueDelayOnInactiveTab: false,
                position: 'top center',
                icon: 'bx bx-error-circle',
                msg: "<?php echo addslashes($this->session->flashdata('error')); ?>"
            });
        });
    </script>
    <?php endif; ?>

    <?php if (!empty($this->session->flashdata('success'))): ?>
    <script>
        $(document).ready(function () {
            Lobibox.notify('success', {
                                pauseDelayOnHover: true,
                                continueDelayOnInactiveTab: false,
                                position: 'top center',
                                icon: 'bx bx-check-circle',
                msg: "<?php echo addslashes($this->session->flashdata('success')); ?>"
                              });
                          });
                      </script>
                  <?php endif; ?>

</body>
</html>