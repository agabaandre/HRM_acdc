<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="//maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
        <!-- jQuery (Load First) -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

        <!-- Bootstrap JS -->
        <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

    <link rel="icon" href="<?php echo base_url()?>assets/images/africacdc_2.png" type="image/png" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.3.1/css/all.css" integrity="sha384-mzrmE5qonljUremFsqc01SB46JvROS7bZs3IO2EmfFsd15uHvIt+Y8vEf7N7fWAU" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo base_url()?>assets/css/login-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <!-- Lobibox CSS -->
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/plugins/notifications/css/lobibox.min.css" />

    <title>Africa CDC Staff Tracker</title>
    <style>
        /* Custom Styles */
        .btn-ms {
            background-image: url('<?php echo base_url()?>assets/images/ms-logo.png');
            background-size: 20px 20px;
            background-repeat: no-repeat;
            background-position: 10px center;
            padding-left: 40px;
        }

        .form-control {
            margin-bottom: 15px; /* Add space between form fields */
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .checkbox-label input {
            margin-right: 10px;
        }

        .form-toggle {
            display: none; /* Hide the form initially */
        }

        .form-toggle.active {
            display: block; /* Show the form when active */
        }
    </style>
</head>
<body>

    <div id="logreg-forms">
    
         
         <div class="logo col-md-12" style="text-align:center;">
         <img src="<?php echo base_url(); ?>assets/images/AU_CDC_Logo-800.png" width="200">
         </div>
           <h1 class="h3 mb-3 font-weight-normal" style="text-align: center">Africa CDC Central Business Platform Sign in</h1>
        

            <?php 

                if (settings()->allow_form_login==1){

                     echo form_open_multipart(base_url('index.php/auth/cred_login'), array('id' => 'login', 'class' => 'login')); ?>

                    <!-- CSRF Protection -->
                    <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" 
                           value="<?= $this->security->get_csrf_hash(); ?>" />
            <!-- Checkbox to toggle the form -->
            <label class="checkbox-label">
           
                <input type="checkbox" id="toggleForm"> Sign in with other options
            </label>

            <!-- Email and Password Fields -->
            <div id="signinForm" class="form-toggle">
                <input type="email" id="inputEmail" name="email" class="form-control mb-2" placeholder="Email address" required autofocus>
                <input type="password" id="inputPassword" name="password" class="form-control" placeholder="Password" required>
                <button class="btn btn-success btn-block" type="submit"><i class="fas fa-sign-in-alt"></i> Sign in</button>
            </div>
            </form> 
            <?php } ?>

            <div class="social-login mt-2">
                <a href="<?= base_url('auth/login') ?>" class="btn btn-success social-btn btn-ms">Staff Mail Sign In</a>
            </div>

            <hr>
       

        
       
    </div>

    <p style="text-align:center">
        <a href="#" style="color:black">Copyright Africa CDC <?php echo date('Y')?></a>
    </p>
    </body>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <script>
        // Toggle the sign-in form visibility
        $(document).ready(function () {
            $('#toggleForm').change(function () {
                if (this.checked) {
                    $('#signinForm').addClass('active');
                } else {
                    $('#signinForm').removeClass('active');
                }
            });
        });
    </script>

    <?php if (!empty($this->session->flashdata('error'))):
                  
                              
                  ?>
                      <script>
                          $(document).ready(function () {
                              Lobibox.notify('error', {
                                pauseDelayOnHover: true,
                                continueDelayOnInactiveTab: false,
                                position: 'top center',
                                icon: 'bx bx-check-circle',
                                  msg: "<?php echo $this->session->flashdata('error'); ?>"
                              });
                          });
                      </script>
                  <?php endif; ?>


<script src="<?php echo base_url() ?>assets/plugins/notifications/js/lobibox.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/notifications.min.js"></script>
</html>