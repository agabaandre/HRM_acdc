<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--favicon-->
    <link rel="icon" href="<?php echo base_url() ?>assets/images/africacdc_2.png" type="image/png" />
    <!--plugins-->
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/css/tom-select.min.css" integrity="sha512-BrNXB6PRnf32ZqstFiYQT/L7aVZ45FGojXbBx8nybK/NBhxFQPHsr36jH11I2YoUaA5UFqTRF14xt3VVMWfCOg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="<?php echo base_url() ?>assets/css/icons.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>assets/plugins/smart-wizard/css/smart_wizard_all.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>assets/css/bootstrap-extended.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/plugins/notifications/css/lobibox.min.css" />
    <link href="<?php echo base_url() ?>assets/plugins/simplebar/css/simplebar.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/metismenu/css/metisMenu.min.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/fullcalendar/css/main.min.css" rel="stylesheet" />
    <!-- Theme Style CSS -->
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/dark-theme.css" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/semi-dark.css" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/css/header-colors.css" />

    <!-- loader-->
    <link href="<?php echo base_url() ?>assets/plugins/select2/css/select2.min.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/select2/css/select2-bootstrap4.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/css/app.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>assets/css/pace.min.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/datetimepicker/css/classic.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/datetimepicker/css/classic.time.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/datetimepicker/css/classic.date.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/plugins/bootstrap-material-datetimepicker/css/bootstrap-material-datetimepicker.min.css">

    <!-- Bootstrap CSS -->
    <!-- Modal -->
    <link rel="stylesheet" href="<?php echo base_url() ?>assets/jquery-ui-1.8.7.custom/development-bundle/themes/base/jquery.ui.all.css">
    <link rel="stylesheet" href="<?php echo base_url() ?>jquery-ui-1.8.7.custom/development-bundle/themes/base/jquery.ui.all.css">
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <script type="text/javascript">
        $(window).on('load', function() {

            $('#status').delay(900).fadeOut(1000); // will first fade out the loading animation
            $('#preloader').delay(900).fadeOut(1000); // will fade out the white div

        });
    </script>
    <style>
    @media print {
        .no-print {
            display: none !important;
        }
    }
    </style>

<body>
    <!--wrapper-->
    <div class="wrapper">