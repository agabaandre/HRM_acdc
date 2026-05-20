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
<footer class="page-footer" style="padding-left: 4px;">
    <p class="mb-0">
        <a href="{{ route('faq.index') }}" wire:navigate class="text-decoration-none me-3">FAQs</a>
        <a href="{{ route('help.index') }}" wire:navigate class="text-decoration-none me-3">Help</a>
        <span class="text-muted">Copyright © Africa CDC {{ date('Y') }}. All right reserved.</span>
    </p>
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

<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}" data-navigate-once></script>
<!-- jQuery UI Library -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" data-navigate-once></script>
@if (! View::hasSection('suppress_google_translate'))
<script type="text/javascript"
    src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit" data-navigate-once></script>
@endif
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js" data-navigate-once></script>

<script src="{{ asset('assets/plugins/notifications/js/notifications.min.js') }}" data-navigate-once></script>
<script src="{{ asset('assets/js/pace.min.js') }}" data-navigate-once></script>
<script src="{{ asset('assets/plugins/notifications/js/notification-custom-script.js') }}" data-navigate-once></script>
<script src="{{ asset('assets/plugins/simplebar/js/simplebar.min.js') }}" data-navigate-once></script>
<script src="{{ asset('js/apm-jquery-alias.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}" data-navigate-once></script>
<script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}" data-navigate-once></script>
<script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}" data-navigate-once></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js" data-navigate-once></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-bs5.min.js" data-navigate-once></script>
<script src="{{ asset('assets/plugins/smart-wizard/js/jquery.smartWizard.min.js') }}" data-navigate-once></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr" data-navigate-once></script>
<script src="{{ asset('js/apm-layout-plugins.js') }}"></script>

<script data-navigate-once>
    function show_notification(message, msgtype) {
        if (typeof msgtype === 'undefined') {
            msgtype = 'info';
        }
        if (typeof Lobibox === 'undefined') {
            return;
        }
        Lobibox.notify(msgtype, {
            pauseDelayOnHover: true,
            continueDelayOnInactiveTab: false,
            position: 'center top',
            icon: msgtype === 'success' ? 'bx bx-check-circle' :
                  msgtype === 'error'   ? 'bx bx-error-circle' :
                  msgtype === 'warning' ? 'bx bx-error' :
                                           'bx bx-info-circle',
            sound: false,
            msg: message
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof jQuery === 'undefined') {
            return;
        }
        @if(session('msg') && session('type'))
            show_notification({!! json_encode(session('msg')) !!}, {!! json_encode(session('type')) !!});
        @endif
        @if(session('success'))
            show_notification({!! json_encode(session('success')) !!}, 'success');
        @endif
        @if(session('error'))
            show_notification({!! json_encode(session('error')) !!}, 'error');
        @endif
        @if(isset($errors) && $errors->any())
            @foreach ($errors->all() as $error)
                show_notification({!! json_encode($error) !!}, 'error');
            @endforeach
        @endif
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

@if (! View::hasSection('suppress_google_translate'))
<script type="text/javascript">
    (function() {
        var translateApplied = false;
        function googleTranslateElementInit() {
            try {
                if (typeof google === 'undefined' || !google.translate || !google.translate.TranslateElement) return;
                new google.translate.TranslateElement({
                    pageLanguage: 'en',
                    autoDisplay: false,
                    disableAutoHover: true,
                    showBanner: false
                }, 'google_translate_element');
            } catch (e) {
                if (typeof console !== 'undefined' && console.warn) console.warn('Google Translate init failed:', e);
            }
        }
        window.googleTranslateElementInit = googleTranslateElementInit;

        function GTranslateFireEvent(element, event) {
            try {
                if (!element) return;
                if (document.createEventObject) {
                    var evt = document.createEventObject();
                    element.fireEvent('on' + event, evt);
                } else {
                    var evt = document.createEvent('HTMLEvents');
                    evt.initEvent(event, true, true);
                    element.dispatchEvent(evt);
                }
            } catch (err) { }
        }

        function doGTranslate(lang_code) {
            if (translateApplied) return;
            var lang = lang_code || 'en';
            var attempts = 0;
            var maxAttempts = 24;
            var interval = setInterval(function () {
                attempts++;
                if (attempts > maxAttempts) {
                    clearInterval(interval);
                    return;
                }
                try {
                    var teCombo = document.querySelector('select.goog-te-combo');
                    if (teCombo && teCombo.options && teCombo.options.length > 0) {
                        var langIndex = Array.from(teCombo.options).findIndex(function(option) { return option.value === lang; });
                        if (langIndex !== -1) {
                            teCombo.selectedIndex = langIndex;
                            GTranslateFireEvent(teCombo, 'change');
                            translateApplied = true;
                            clearInterval(interval);
                        }
                    }
                } catch (err) {
                    if (typeof console !== 'undefined' && console.warn) console.warn('Google Translate apply failed:', err);
                    clearInterval(interval);
                }
            }, 500);
        }
        window.doGTranslate = doGTranslate;

        document.addEventListener("DOMContentLoaded", function () {
            @php
                $user = session('user', []);
                $defaultLangCode = $user['langauge'] ?? 'en';
                $langMap = [
                    'en' => 'en',
                    'fr' => 'fr',
                    'sw' => 'sw',
                    'ar' => 'ar',
                    'pt' => 'pt',
                    'es' => 'es'
                ];
                $preferredLang = $langMap[$defaultLangCode] ?? 'en';
            @endphp
            var preferredLang = "{{ $preferredLang }}";
            if (preferredLang && preferredLang !== 'en') {
                setTimeout(function() { doGTranslate(preferredLang); }, 1500);
            }
        });
    })();
</script>
@else
<script type="text/javascript">
    window.googleTranslateElementInit = function () {};
    window.doGTranslate = function () {};
</script>
@endif

{{-- Summernote / layout plugins: public/js/apm-layout-plugins.js (apmSummernoteOptions + livewire:navigated) --}}