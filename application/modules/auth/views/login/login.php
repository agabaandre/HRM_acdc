<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--favicon-->
    <link rel="icon" href="<?php echo base_url()?><?php echo base_url()?>assets/images/africacdc_2.png" type="image/png" />
    <!--plugins-->
    <link href="<?php echo base_url()?>assets/plugins/simplebar/css/simplebar.css" rel="stylesheet" />
    <link href="<?php echo base_url()?>assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css" rel="stylesheet" />
    <link href="<?php echo base_url()?>assets/plugins/metismenu/css/metisMenu.min.css" rel="stylesheet" />
    <link href="<?php echo base_url()?>assets/plugins/fullcalendar/css/main.min.css" rel="stylesheet" />
    <link href="<?php echo base_url()?>assets/plugins/datatable/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <!-- loader-->
    <link href="<?php echo base_url()?>assets/css/pace.min.css" rel="stylesheet" />
    <script src="<?php echo base_url()?>assets/js/pace.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <!-- Bootstrap CSS -->
    <link href="<?php echo base_url()?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>assets/css/bootstrap-extended.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link href="<?php echo base_url()?>assets/css/app.css" rel="stylesheet">
    <link href="<?php echo base_url()?>assets/css/icons.css" rel="stylesheet">
    <!-- Theme Style CSS -->
    <link rel="stylesheet" href="<?php echo base_url()?>assets/css/dark-theme.css" />
    <link rel="stylesheet" href="<?php echo base_url()?>assets/css/semi-dark.css" />
    <link rel="stylesheet" href="<?php echo base_url()?>assets/css/header-colors.css" />
    <link rel="stylesheet" href="jquery-ui-1.8.7.custom/development-bundle/themes/base/jquery.ui.all.css">
    <script src="jquery-ui-1.8.7.custom/development-bundle/jquery-1.4.4.js"></script>
    <script src="jquery-ui-1.8.7.custom/development-bundle/ui/jquery.ui.core.js"></script>
    <script src="jquery-ui-1.8.7.custom/development-bundle/ui/jquery.ui.widget.js"></script>
    <script src="jquery-ui-1.8.7.custom/development-bundle/ui/jquery.ui.datepicker.js"></script>

    <script type="text/javascript">
        (function ($) {
            //jquery stuff
            $(function () {
                $(".dob1").datepicker({
                    changeMonth: true,
                    changeYear: true,
                    showOn: "both",
                    buttonImage: "images/datepicker_icon.jpg",
                    buttonImageOnly: true
                });

            });
            //end jquery stuff
        })(jQuery);
        //no conflict jquery
        jQuery.noConflict();
    </script>
    <title>Africa CDC</title>
</head>

<body class="bg-login">
  <!--wrapper-->
  <div class="wrapper">
    <div class="section-authentication-signin d-flex align-items-center justify-content-center my-5 my-lg-0">
      <div class="container-fluid">
        <div class="row row-cols-1 row-cols-lg-2 row-cols-xl-3">
          <div class="col mx-auto">
            <div class="card rounded-4">
              <div class="card-body">
                <div class="border p-4 rounded-4">
                  <div class="text-center">
                    <img src="<?php echo base_url()?>assets/images/africacdc_2.png" width="250" alt="" />
                    <p class="mb-4">Please login before enter the page</p>
                  </div>
                  <div class="panel-title text-center">
                    <h2>STAFF TRACKER </h2>
                  </div>

                  <div class="form-body">
                    <?php echo form_open_multipart(base_url('index.php/auth/login'), array('id' => 'login', 'class' => 'login')); ?>


                      <div class="col-12">
                        <input type="text" class="form-control rounded-5" name="username" placeholder="Username"
                          autocomplete="off" focus>
                      </div>
                      <div class="col-12">

                        <input class="form-control rounded-5" type="password" name="password"
                          placeholder="Enter Password">
                      </div>
                      <div class="col-md-6">
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked">
                          <label class="form-check-label" for="flexSwitchCheckChecked">Remember Me</label>
                        </div>
                      </div>
                      <!--	<div class="col-md-6 text-end">
                        <a href="authentication-forgot-password.html">Forgot Password ?</a>
                      </div> -->
                      <div class="col-12">
                        <div class="d-grid">
                          <button class="btn btn-gradient-info rounded-5" type="submit" name="Submit"><i
                              class="bx bxs-lock-open"></i>Sign in</button>
                        </div>
                      </div>



                    </form>


                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!--end row-->
      </div>
    </div>
  </div>
  <!--end wrapper-->
  <!-- Bootstrap JS -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <!--plugins-->
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/plugins/simplebar/js/simplebar.min.js"></script>
  <script src="assets/plugins/metismenu/js/metisMenu.min.js"></script>
  <script src="assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
  <!--Password show & hide js -->
  <script>
    $(document).ready(function () {
      $("#show_hide_password a").on('click', function (event) {
        event.preventDefault();
        if ($('#show_hide_password input').attr("type") == "text") {
          $('#show_hide_password input').attr('type', 'password');
          $('#show_hide_password i').addClass("bx-hide");
          $('#show_hide_password i').removeClass("bx-show");
        } else if ($('#show_hide_password input').attr("type") == "password") {
          $('#show_hide_password input').attr('type', 'text');
          $('#show_hide_password i').removeClass("bx-hide");
          $('#show_hide_password i').addClass("bx-show");
        }
      });
    });
  </script>
  <!--app JS-->
  <script src="assets/js/app.js"></script>
</body>
</html>