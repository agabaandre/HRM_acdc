{{-- Must load before translate.google.com/element.js so init + English restore exist first. --}}
<script type="text/javascript">
    (function() {
        var appliedLang = null;

        function cookieDomains() {
            var host = (location.hostname || '').toLowerCase();
            var domains = [''];
            if (!host || host === 'localhost' || /^\d+\.\d+\.\d+\.\d+$/.test(host)) {
                return domains;
            }
            domains.push(host);
            var parts = host.split('.');
            if (parts.length >= 2) domains.push('.' + parts.slice(-2).join('.'));
            if (parts.length >= 3) domains.push('.' + parts.slice(-3).join('.'));
            return domains;
        }

        function cookiePaths() {
            var paths = ['/', '/staff', '/staff/', '/staff/apm', '/staff/apm/'];
            var path = location.pathname || '/';
            if (paths.indexOf(path) === -1) paths.push(path);
            return paths;
        }

        function expireCookie(name, path, domain) {
            var base = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;max-age=0;path=' + path + ';SameSite=Lax';
            try {
                document.cookie = domain ? (base + ';domain=' + domain) : base;
            } catch (e) {}
        }

        function clearGoogleTranslateCookies() {
            cookieDomains().forEach(function (domain) {
                cookiePaths().forEach(function (path) {
                    expireCookie('googtrans', path, domain);
                });
            });
        }
        window.clearGoogleTranslateCookies = clearGoogleTranslateCookies;

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

        function findTranslateOptionIndex(teCombo, lang) {
            var options = Array.from(teCombo.options || []);
            if (lang === 'en') {
                // Original page language is usually an empty value, not "en".
                var original = options.findIndex(function (option) {
                    return option.value === '' || option.value === 'en' || option.value === '/en/en';
                });
                if (original !== -1) return original;
            }
            return options.findIndex(function (option) { return option.value === lang; });
        }

        function doGTranslate(lang_code) {
            var lang = (lang_code || 'en').toLowerCase();
            if (appliedLang === lang) return;
            if (lang === 'en') {
                clearGoogleTranslateCookies();
            }
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
                        var langIndex = findTranslateOptionIndex(teCombo, lang);
                        if (langIndex !== -1) {
                            teCombo.selectedIndex = langIndex;
                            GTranslateFireEvent(teCombo, 'change');
                            appliedLang = lang;
                            if (lang === 'en') {
                                clearGoogleTranslateCookies();
                            }
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

        (function earlyClearIfEnglish() {
            try {
                var fromStorage = (localStorage.getItem('staff_portal_locale') || '').toLowerCase().trim();
                var match = document.cookie.match(/(?:^|; )staff_portal_locale=([^;]*)/);
                var fromCookie = match && match[1] ? decodeURIComponent(match[1]).toLowerCase().trim() : '';
                var preferred = fromStorage || fromCookie || @json(\App\Support\PortalLocale::normalize(app()->getLocale()));
                if (!preferred || preferred === 'en') {
                    clearGoogleTranslateCookies();
                }
            } catch (e) {
                clearGoogleTranslateCookies();
            }
        })();

        document.addEventListener('DOMContentLoaded', function () {
            @php
                $preferredLang = \App\Support\PortalLocale::normalize(app()->getLocale());
            @endphp
            function readSharedPortalLocale() {
                try {
                    var fromStorage = (localStorage.getItem('staff_portal_locale') || '').toLowerCase().trim();
                    if (fromStorage) return fromStorage;
                } catch (e) {}
                try {
                    var match = document.cookie.match(/(?:^|; )staff_portal_locale=([^;]*)/);
                    if (match && match[1]) return decodeURIComponent(match[1]).toLowerCase().trim();
                } catch (e) {}
                return '';
            }
            var preferredLang = readSharedPortalLocale() || @json($preferredLang);
            var allowed = { en: 1, fr: 1, sw: 1, ar: 1, pt: 1, es: 1 };
            if (!allowed[preferredLang]) preferredLang = 'en';
            try {
                localStorage.setItem('staff_portal_locale', preferredLang);
                document.cookie = 'staff_portal_locale=' + encodeURIComponent(preferredLang)
                    + ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
            } catch (e) {}
            if (preferredLang && preferredLang !== 'en') {
                setTimeout(function () { doGTranslate(preferredLang); }, 1500);
            } else {
                clearGoogleTranslateCookies();
                setTimeout(function () { doGTranslate('en'); }, 800);
            }
        });
    })();
</script>
