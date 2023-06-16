<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--favicon-->
    <link rel="icon" href="<?php echo base_url()?>assets/images/africacdc_2.png" type="image/png" />
    <!--plugins-->
    <link href="<?php echo base_url() ?>assets/plugins/simplebar/css/simplebar.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/metismenu/css/metisMenu.min.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/fullcalendar/css/main.min.css" rel="stylesheet" />
    <!-- <link href="<?php echo base_url() ?>assets/plugins/datatable/css/dataTables.bootstrap5.min.css" rel="stylesheet" /> -->
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <!-- loader-->
    <link href="<?php echo base_url() ?>assets/css/pace.min.css" rel="stylesheet" />
    <script src="<?php echo base_url() ?>assets/js/pace.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <!-- Bootstrap CSS -->
    <link href="<?php echo base_url() ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>assets/css/bootstrap-extended.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link href="<?php echo base_url() ?>assets/css/app.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>assets/css/icons.css" rel="stylesheet">
    <!-- Theme Style CSS -->
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/dark-theme.css" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/semi-dark.css" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/header-colors.css" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/jquery-ui-1.8.7.custom/development-bundle/themes/base/jquery.ui.all.css">
    <script src="<?php echo base_url() ?>assets/jquery-ui-1.8.7.custom/development-bundle/jquery-1.4.4.js"></script>
    <script src="<?php echo base_url() ?>assets/jquery-ui-1.8.7.custom/development-bundle/ui/jquery.ui.core.js"></script>
    <script src="<?php echo base_url() ?>assets/jquery-ui-1.8.7.custom/development-bundle/ui/jquery.ui.widget.js"></script>
    <script src="<?php echo base_url() ?>assets/jquery-ui-1.8.7.custom/development-bundle/ui/jquery.ui.datepicker.js"></script>

    <script type="text/javascript">
        (function($) {
            //jquery stuff
            $(function() {
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