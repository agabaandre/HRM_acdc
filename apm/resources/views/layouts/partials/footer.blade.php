</div>
</div>
</div>
</div>
<!--end page wrapper -->
<!--start overlay-->
<div class="overlay toggle-icon"></div>
<!--end overlay-->
<!--Start Back To Top Button--> <a href="javaScript:;" class="back-to-top"><i class='bx bxs-up-arrow-alt'></i></a>
<!--End Back To Top Button-->
<footer class="page-footer">
    <p class="mb-0">Copyright © Africa CDC {{ date('Y') }}. All right reserved.</p>
</footer>
</div>
<!--end wrapper-->
<!--start switcher-->
<div class="switcher-wrapper">
    <div class="switcher-btn"> <i class='bx bx-cog bx-spin'></i>
    </div>
    <div class="switcher-body">
        <div class="d-flex align-items-center">
            <h5 class="mb-0 text-uppercase">Theme Customizer</h5>
            <button type="button" class="btn-close ms-auto close-switcher" aria-label="Close"></button>
        </div>
        <hr />
        <h6 class="mb-0">Theme Styles</h6>
        <hr />
        <div class="d-flex align-items-center justify-content-between">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="lightmode" checked>
                <label class="form-check-label" for="lightmode">Light</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="darkmode">
                <label class="form-check-label" for="darkmode">Dark</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="semidark">
                <label class="form-check-label" for="semidark">Semi Dark</label>
            </div>
        </div>
        <hr />
        <div class="form-check">
            <input class="form-check-input" type="radio" id="minimaltheme" name="flexRadioDefault">
            <label class="form-check-label" for="minimaltheme">Minimal Theme</label>
        </div>
        <hr />
        <h6 class="mb-0">Header Colors</h6>
        <hr />
        <div class="header-colors-indigators">
            <div class="row row-cols-auto g-3">
                <div class="col">
                    <div class="indigator headercolor1" id="headercolor1"></div>
                </div>
                <div class="col">
                    <div class="indigator headercolor2" id="headercolor2"></div>
                </div>
                <div class="col">
                    <div class="indigator headercolor3" id="headercolor3"></div>
                </div>
                <div class="col">
                    <div class="indigator headercolor4" id="headercolor4"></div>
                </div>
                <div class="col">
                    <div class="indigator headercolor5" id="headercolor5"></div>
                </div>
                <div class="col">
                    <div class="indigator headercolor6" id="headercolor6"></div>
                </div>
                <div class="col">
                    <div class="indigator headercolor7" id="headercolor7"></div>
                </div>
                <div class="col">
                    <div class="indigator headercolor8" id="headercolor8"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end switcher-->

<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<!-- jQuery UI Library -->
<!-- Bootstrap 5 with Popper bundled -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-FHgNHNh4rHcmJ8s9jP3J7iYmMOTtMnJ0A2gU2wZSwRPmpZuUMHefPlU+GfNwH3zU" crossorigin="anonymous"></script> -->

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<!-- <script type="text/javascript"
    src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script> -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>

<script src="{{ asset('assets/plugins/notifications/js/notifications.min.js') }}"></script>
<script src="{{ asset('assets/js/pace.min.js') }}"></script>
<script src="{{ asset('assets/plugins/notifications/js/notification-custom-script.js') }}"></script>
<script src="{{ asset('assets/plugins/simplebar/js/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

<script src="{{ asset('assets/plugins/smart-wizard/js/jquery.smartWizard.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- FullCalendar & Bootstrap JS Bundle -->
<script>
    $(document).ready(function () {
        $('.datepicker').flatpickr({
            theme: "confetti",
            altInput: true,
            altFormat: "F j, Y",
            dateFormat: "Y-m-d",
            allowInput: true
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const currentYear = new Date().getFullYear();
        const minDate = `${currentYear}-01-01`;
        const maxDate = `${currentYear}-12-31`;

        flatpickr('.current_datepicker', {
            dateFormat: "Y-m-d",
            minDate: minDate,
            maxDate: maxDate,
            disableMobile: true
        });
    });
</script>

<script>
    $(document).ready(function () {
      
        @if(session('msg') && session('type'))
            show_notification(`{!! session('msg') !!}`, "{{ session('type') }}");
        @endif

       
        @if($errors->any())
            @foreach ($errors->all() as $error)
                show_notification(`{!! $error !!}`, "error");
            @endforeach
        @endif
    });

   
    function show_notification(message, msgtype = 'info') {
        Lobibox.notify(msgtype, {
            pauseDelayOnHover: true,
            continueDelayOnInactiveTab: false,
            position: 'top right',
            icon: msgtype === 'success' ? 'bx bx-check-circle' :
                  msgtype === 'error'   ? 'bx bx-error-circle' :
                  msgtype === 'warning' ? 'bx bx-error' :
                                           'bx bx-info-circle',
            sound: false,
            msg: message
        });
    }
</script>

<script>
    $('.select2').select2({
        theme: 'bootstrap4',
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: $(this).data('placeholder'),
        allowClear: Boolean($(this).data('allow-clear')),
    });

    $('.multiple-select').select2({
        theme: 'bootstrap4',
        multiple: true,
        width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
        placeholder: $(this).data('placeholder'),
        allowClear: Boolean($(this).data('allow-clear')),
    });
</script>
<script>
    $(document).ready(function () {
        $(function () {
            var priorities = ["Low", "Medium", "High"];
            $("#edit_priority").autocomplete({
                source: priorities
            });
        });

        $('.mydata').DataTable({
            dom: 'Bfrtip',
            "paging": true,
            "lengthChange": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": true,
            lengthMenu: [
                [25, 50, 100, 150, -1],
                ['25', '50', '100', '150', '200', 'Show all']
            ],
            buttons: [
                'csvHtml5',
                'pdfHtml5',
                'pageLength',
            ]
        });
    });
</script>

<!-- <script>
    $(document).ready(function () {
        function loadMessages() {
            $.ajax({
                url: '{{ session('baseUrl', '') }}dashboard/fetch_messages_ajax',
                method: 'GET',
                dataType: 'json',
                success: function (messages) {
                    $('#ajax-messages').empty();

                    if (messages.length === 0) {
                        $('#ajax-messages').html('<div class="text-center text-muted py-3">No new messages</div>');
                    }

                    $('#message-count').text(messages.length);

                    messages.forEach(function (message) {
                        let html = `
            <a class="dropdown-item" href="javascript:;">
              <div class="d-flex align-items-center mb-2">
                <div class="user-online me-2">
                  <img src="{{ asset('assets/images/user.png') }}" class="msg-avatar rounded-circle" alt="avatar" style="width: 35px; height: 35px;">
                </div>
                <div class="flex-grow-1">
                  <h6 class="msg-name mb-1">${message.trigger}
                    <span class="msg-time float-end small">${message.time_ago}</span>
                  </h6>
                  <p class="msg-info small mb-0">${message.subject}</p>
                </div>
              </div>
            </a>`;
                        $('#ajax-messages').append(html);
                    });
                },
                error: function () {
                    $('#ajax-messages').html('<div class="text-center text-danger py-3">Failed to load messages</div>');
                }
            });
        }

        loadMessages(); // Load on page ready
        setInterval(loadMessages, 30000); // Auto refresh every 30 sec
    });
</script> -->

<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en',
            autoDisplay: false,
            disableAutoHover: true,
            showBanner: false
        }, 'google_translate_element');
    }

    function GTranslateFireEvent(element, event) {
        try {
            if (document.createEventObject) {
                var evt = document.createEventObject();
                element.fireEvent('on' + event, evt);
            } else {
                var evt = document.createEvent('HTMLEvents');
                evt.initEvent(event, true, true);
                element.dispatchEvent(evt);
            }
        } catch (e) { }
    }

    function doGTranslate(lang_code) {
        var lang = lang_code || 'en';
        var interval = setInterval(function () {
            var teCombo = document.querySelector('select.goog-te-combo');
            if (teCombo && teCombo.options.length > 0) {
                var langIndex = Array.from(teCombo.options).findIndex(option => option.value === lang);
                if (langIndex !== -1) {
                    teCombo.selectedIndex = langIndex;
                    GTranslateFireEvent(teCombo, 'change');
                    GTranslateFireEvent(teCombo, 'change');
                    clearInterval(interval); // stop once applied
                }
            }
        }, 500); // retry every 500ms until successful
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Set default language
        setTimeout(() => {
            doGTranslate('en');
        }, 1500); // delay to let Google Translate load
    });
</script>

</body>

</html>