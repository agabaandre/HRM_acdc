<!doctype html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!--favicon-->
    <link rel="icon" href="<?php echo base_url() ?>assets/images/au_emblem.png" type="image/png" />
    <!--plugins-->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <!-- <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.4/css/responsive.bootstrap5.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.bootstrap5.min.css">
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
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
    <!-- loader-->
    <link href="<?php echo base_url() ?>assets/plugins/select2/css/select2.min.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/plugins/select2/css/select2-bootstrap4.css" rel="stylesheet" />
    <link href="<?php echo base_url() ?>assets/css/app.css" rel="stylesheet">
    <link href="<?php echo base_url() ?>assets/css/pace.min.css" rel="stylesheet" />
     <!-- Remove other jQuery versions before adding this -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

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
        .dataTables_filter{
            margin-bottom:6px !important;
        }
        .select2-container--open {
            z-index: 9999 !important;
        }

    @media print {
        .no-print {
            display: none !important;
        }
    }
    </style>

<body>
    <!--wrapper-->
    <div class="wrapper">