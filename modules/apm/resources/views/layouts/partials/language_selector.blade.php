{{-- AU language selector (Staff Portal / Risk Register chrome parity). --}}
@php
    $apmLocaleCatalog = \App\Support\PortalLocale::catalog();
    $apmLanguages = $apmLocaleCatalog['languages'] ?? [];
    $apmCurrentLocale = $apmLocaleCatalog['locale'] ?? 'en';
    $apmCurrentLang = collect($apmLanguages)->firstWhere('code', $apmCurrentLocale) ?? ($apmLanguages[0] ?? null);
@endphp
@if (count($apmLanguages) > 0)
<li class="nav-item cbp-lang-select-wrap" style="border:none !important;">
    <div class="cbp-lang-select" id="cbp-lang-select" data-locale-url="{{ route('locale.apply') }}" data-current-locale="{{ $apmCurrentLocale }}">
        <button
            type="button"
            class="cbp-lang-select__btn notranslate"
            id="cbp-lang-select-btn"
            aria-haspopup="listbox"
            aria-expanded="false"
            title="{{ \App\Support\PortalLocale::t('chrome.language', 'Language') }}"
            aria-label="{{ \App\Support\PortalLocale::t('chrome.languages', 'Languages') }}"
        >
            <span class="cbp-lang-select__flag" aria-hidden="true">{{ $apmCurrentLang['flag'] ?? '' }}</span>
            <span class="cbp-lang-select__code">{{ strtoupper($apmCurrentLocale) }}</span>
            <span class="cbp-lang-select__caret" aria-hidden="true">▼</span>
        </button>
        <ul class="cbp-lang-select__menu notranslate" id="cbp-lang-select-menu" role="listbox" hidden>
            @foreach ($apmLanguages as $lang)
                <li>
                    <button
                        type="button"
                        class="cbp-lang-select__item{{ ($lang['code'] ?? '') === $apmCurrentLocale ? ' is-active' : '' }}"
                        role="option"
                        aria-selected="{{ ($lang['code'] ?? '') === $apmCurrentLocale ? 'true' : 'false' }}"
                        data-locale="{{ $lang['code'] ?? '' }}"
                    >
                        <span class="cbp-lang-select__flag" aria-hidden="true">{{ $lang['flag'] ?? '' }}</span>
                        <span>{{ $lang['name'] ?? '' }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
</li>
<script>
(function () {
    var root = document.getElementById('cbp-lang-select');
    var btn = document.getElementById('cbp-lang-select-btn');
    var menu = document.getElementById('cbp-lang-select-menu');
    if (!root || !btn || !menu || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    var COOKIE_NAME = 'staff_portal_locale';
    var STORAGE_KEY = 'staff_portal_locale';
    var applying = false;

    function close() {
        root.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
    }
    function open() {
        root.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        menu.hidden = false;
    }

    function writeSharedLocale(locale) {
        try { localStorage.setItem(STORAGE_KEY, locale); } catch (e) {}
        var maxAge = 60 * 60 * 24 * 365;
        document.cookie = COOKIE_NAME + '=' + encodeURIComponent(locale)
            + ';path=/;max-age=' + maxAge + ';SameSite=Lax';
    }

    function readSharedLocale() {
        try {
            var fromStorage = (localStorage.getItem(STORAGE_KEY) || '').toLowerCase().trim();
            if (fromStorage) return fromStorage;
        } catch (e) {}
        try {
            var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_NAME + '=([^;]*)'));
            if (match && match[1]) return decodeURIComponent(match[1]).toLowerCase().trim();
        } catch (e) {}
        return '';
    }

    function hardReload() {
        var url = window.location.pathname + window.location.search;
        window.location.replace(url);
    }

    function applyLocale(locale, opts) {
        opts = opts || {};
        if (applying) return;
        var current = root.getAttribute('data-current-locale') || '';
        if (!locale || locale === current) {
            if (!opts.silent) close();
            return;
        }
        applying = true;
        btn.disabled = true;
        writeSharedLocale(locale);
        if (typeof window.doGTranslate === 'function' && locale !== 'en') {
            try { window.doGTranslate(locale); } catch (err) {}
        }
        var url = root.getAttribute('data-locale-url');
        var token = document.querySelector('meta[name="csrf-token"]');
        var csrf = token ? (token.getAttribute('content') || '') : '';
        var body = new FormData();
        body.append('locale', locale);
        if (csrf) body.append('_token', csrf);
        fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf
            },
            body: body,
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (res) {
            if (!res.ok) throw new Error('locale failed');
            return res.json().catch(function () { return {}; });
        }).then(function () {
            hardReload();
        }).catch(function () {
            if (opts.silent) {
                applying = false;
                btn.disabled = false;
                return;
            }
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = url;
            form.style.display = 'none';
            [['locale', locale], ['_token', csrf]].forEach(function (pair) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = pair[0];
                input.value = pair[1] || '';
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        });
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (root.classList.contains('is-open')) close(); else open();
    });
    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    menu.querySelectorAll('[data-locale]').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            applyLocale(item.getAttribute('data-locale'));
        });
    });

    // Adopt Staff Portal selection (localStorage/cookie) when APM session is stale.
    var shared = readSharedLocale();
    var server = (root.getAttribute('data-current-locale') || '').toLowerCase();
    if (shared && shared !== server) {
        applyLocale(shared, { silent: true });
    }
})();
</script>
@endif
