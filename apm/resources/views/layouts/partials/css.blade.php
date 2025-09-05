<!--favicon-->
<link rel="icon" href="{{ asset('assets/images/au_emblem.png') }}" type="image/png" />
<!--plugins-->
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.4/css/responsive.bootstrap5.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/css/tom-select.min.css"
    integrity="sha512-BrNXB6PRnf32ZqstFiYQT/L7aVZ45FGojXbBx8nybK/NBhxFQPHsr36jH11I2YoUaA5UFqTRF14xt3VVMWfCOg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
<link href="{{ asset('assets/css/icons.css') }}" rel="stylesheet">
<link href="{{ asset('assets/plugins/smart-wizard/css/smart_wizard_all.min.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/bootstrap-extended.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/plugins/notifications/css/lobibox.min.css') }}" />
<link href="{{ asset('assets/plugins/simplebar/css/simplebar.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/perfect-scrollbar/css/perfect-scrollbar.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/metismenu/css/metisMenu.min.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/fullcalendar/css/main.min.css') }}" rel="stylesheet" />
<!-- Theme Style CSS -->
<link rel="stylesheet" href="{{ asset('assets/css/dark-theme.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/semi-dark.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/css/header-colors.css') }}" />
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<!-- loader-->
<link href="{{ asset('assets/plugins/select2/css/select2.min.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/plugins/select2/css/select2-bootstrap4.css') }}" rel="stylesheet" />
<link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
<link href="{{ asset('assets/css/pace.min.css') }}" rel="stylesheet" />
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<!-- jQuery UI CSS -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
      <!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('assets/plugins/notifications/js/lobibox.min.js') }}"></script>
<!-- Core Highcharts -->
<script src="https://code.highcharts.com/highcharts.js"></script>

<!-- Dependencies for Solid Gauge -->
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>

<!-- Optional Modules -->
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" integrity="sha512-nMNlpuaDPrqlEls3IX/Q56H36qvBASwb3ipuo3MxeWbsQB1881ox0cRv7UPTgBlriqoynt35KjEwgGUeUXIPnw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Global: Disable Highcharts Credits -->
<script type="text/javascript">
    $(window).on('load', function () {
        $('#status').delay(900).fadeOut(1000); // will first fade out the loading animation
        $('#preloader').delay(900).fadeOut(1000); // will fade out the white div
    });
</script>

<!-- Global CSS Styles -->
<style>
    .dataTables_filter {
        margin-bottom: 6px !important;
    }

    .select2-container--open {
        z-index: 9999 !important;
    }

    @media print {
        .no-print {
            display: none !important;
        }
    }

    .msg-avatar {
        object-fit: cover;
    }

    .breadcrumb-sm {
        font-size: 0.8rem;
    }

    .goog-te-banner-frame.skiptranslate,
    .goog-logo-link,
    .VIpgJd-ZVi9od-ORHb-OEVmcd,
    .goog-te-gadget-icon,
    div.feedback-form-container,
    div.feedback-prompt {
        display: none !important;
    }

    .modal.modal-bottom .modal-dialog {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        margin: 0 auto;
    }

    /* Alert count styling for pending approval badges */
    .alert-count {
        display: inline-block;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        line-height: 20px;
        text-align: center;
        font-size: 12px;
        font-weight: bold;
        margin-left: auto;
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
    }


    .modal.fade .modal-dialog.modal-bottom {
        transform: translateY(100%);
    }

    .modal.fade.show .modal-dialog.modal-bottom {
        transform: translateY(0);
    }
     .select2-container .select2-selection--single{
    box-sizing: border-box;
    cursor: pointer;
    display: block;
    height: 35px !important;
    user-select: none;
    -webkit-user-select: none;
    border: 1px solid #ced4da !important;
    border-radius: 0.375rem !important;
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        line-height: 33px !important;
        padding-left: 8px !important;
        padding-right: 20px !important;
    }

    .select2-container .select2-selection--single .select2-selection__placeholder {
        line-height: 33px !important;
        color: #6c757d !important;
    }

    .select2-container .select2-selection--single:focus {
        border-color: #198754 !important;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25) !important;
    }

    .select2-container--bootstrap4 .select2-selection--single {
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: 33px !important;
        padding-left: 8px !important;
        padding-right: 20px !important;
    }

    .select2-container--bootstrap4 .select2-selection--single .select2-selection__placeholder {
        line-height: 33px !important;
        color: #6c757d !important;
    }

    .select2-container--bootstrap4 .select2-selection--single:focus {
        border-color: #198754 !important;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25) !important;
    }

    .select2-container--bootstrap5 .select2-selection--single {
        border: 1px solid #ced4da !important;
        border-radius: 0.375rem !important;
    }

    .select2-container--bootstrap5 .select2-selection--single .select2-selection__rendered {
        line-height: 33px !important;
        padding-left: 8px !important;
        padding-right: 20px !important;
    }

    .select2-container--bootstrap5 .select2-selection--single .select2-selection__placeholder {
        line-height: 33px !important;
        color: #6c757d !important;
        width: 100% !important;
    }

    .select2-container--bootstrap5 .select2-selection--single:focus {
        border-color: #198754 !important;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25) !important;
    }

    /* Enhanced Action Buttons Styling */
    .action-btn {
        width: 32px !important;
        height: 32px !important;
        border-radius: 8px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.2s ease-in-out !important;
        border: none !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }

    .action-btn:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    }

    .action-btn.text-info:hover {
        background-color: rgba(13, 202, 240, 0.1) !important;
        color: #0dcaf0 !important;
    }

    .action-btn.text-primary:hover {
        background-color: rgba(13, 110, 253, 0.1) !important;
        color: #0d6efd !important;
    }

    .action-btn.text-success:hover {
        background-color: rgba(25, 135, 84, 0.1) !important;
        color: #198754 !important;
    }
</style>

