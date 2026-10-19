{{-- Top-bar CBP Modules menu (shared pattern with Staff portal + Helpdesk). Wired via AppServiceProvider → CbpModulesNavService. --}}
@php
    $cbpNav = $cbpModulesNav ?? ['home' => ['label' => 'CBP Home', 'href' => rtrim((string) ($staffWebBaseUrl ?? ''), '/') . '/', 'is_active' => false], 'modules' => []];
    $cbpHome = $cbpNav['home'] ?? [];
    $cbpModules = $cbpNav['modules'] ?? [];
    $cbpHomeHref = (string) ($cbpHome['href'] ?? (rtrim((string) ($staffWebBaseUrl ?? ''), '/') . '/'));
    $cbpHomeLabel = (string) ($cbpHome['label'] ?? 'CBP Home');
    $cbpHomeActive = !empty($cbpHome['is_active']);
    $cbpToggleActive = $cbpHomeActive;
    foreach ($cbpModules as $_m) {
        if (!empty($_m['is_active'])) {
            $cbpToggleActive = true;
            break;
        }
    }
    unset($_m);
    $cbpCssBase = rtrim((string) ($staffWebBaseUrl ?? ''), '/');
    $cbpProgramGroups = [];
    $cbpProgramDir = base_path();
    for ($cbpI = 0; $cbpI < 6; $cbpI++) {
        $cbpCandidate = $cbpProgramDir.'/shared/cbp-program-modules.php';
        if (is_file($cbpCandidate)) {
            $cbpProgramGroups = require $cbpCandidate;
            break;
        }
        $cbpParent = dirname($cbpProgramDir);
        if ($cbpParent === $cbpProgramDir) {
            break;
        }
        $cbpProgramDir = $cbpParent;
    }
    $cbpProgramCount = 0;
    foreach ($cbpProgramGroups as $cbpGroup) {
        $cbpProgramCount += count($cbpGroup['items'] ?? []);
    }
    $cbpOperationsCount = count($cbpModules);
@endphp
<link rel="stylesheet" href="{{ asset('assets/css/cbp-modules-nav.css') }}">
@if ($cbpCssBase !== '')
    <link rel="stylesheet" href="{{ $cbpCssBase }}/assets/css/cbp-modules-nav.css">
@endif

<li class="nav-item cbp-modules-dd" id="cbp-modules-dd">
    <button
        type="button"
        class="cbp-modules-dd-toggle nav-link border-0{{ $cbpToggleActive ? ' is-active' : '' }}"
        id="cbp-modules-dd-btn"
        aria-haspopup="true"
        aria-expanded="false"
        aria-controls="cbp-modules-dd-panel"
        title="CBP Modules"
    >
        <i class="bx bx-category" style="color: #fff; font-size: 1.1rem;" aria-hidden="true"></i>
        <span class="cbp-modules-dd-label ms-2 d-none d-md-inline" style="color: #fff; font-size: 0.875rem;">CBP Modules</span>
        <span class="cbp-modules-dd-caret d-none d-md-inline" aria-hidden="true">▼</span>
    </button>
    <div class="cbp-modules-dd-panel" id="cbp-modules-dd-panel" role="menu" aria-labelledby="cbp-modules-dd-btn">
        <a href="{{ $cbpHomeHref }}" class="cbp-modules-dd-home" role="menuitem">CBP HOME</a>
        <div class="cbp-modules-dd-scope" role="tablist">
            <button type="button" class="is-active" data-cbp-scope="operations">
                CBP Operations <span class="cbp-modules-dd-count">{{ $cbpOperationsCount }}</span>
            </button>
            <button type="button" data-cbp-scope="programs">
                CBP Programs <span class="cbp-modules-dd-count">{{ $cbpProgramCount }}</span>
            </button>
        </div>
        <div data-cbp-pane="operations">
        @if (count($cbpModules) > 0)
            @foreach ($cbpModules as $mod)
                @php
                    $href = (string) ($mod['href'] ?? '#');
                    $label = (string) ($mod['label'] ?? 'Module');
                    $icon = trim((string) ($mod['icon'] ?? 'fa-th'));
                    if (! preg_match('/\b(fa-solid|fa-regular|fa-brands|fas|far|fab)\b/i', $icon)) {
                        $iconName = preg_replace('/^(?:fa\s+)+/i', '', $icon);
                        $iconName = preg_replace('/^fa-/', '', (string) $iconName);
                        $icon = 'fa-solid fa-'.$iconName;
                    }
                    $absolute = !empty($mod['opens_in_new_tab']);
                    $active = !empty($mod['is_active']);
                    $ssoLaunch = !empty($mod['sso_launch']);
                    $moduleKey = (string) ($mod['module_key'] ?? $mod['id'] ?? '');
                @endphp
                @if ($ssoLaunch && $moduleKey !== '')
                <button
                    type="button"
                    class="cbp-modules-dd-item{{ $active ? ' is-active' : '' }} w-100 border-0 bg-transparent text-start"
                    role="menuitem"
                    data-cbp-module-key="{{ $moduleKey }}"
                    data-cbp-open-new-tab="{{ $absolute ? '1' : '0' }}"
                    onclick="if (window.cbpLaunchModule) { cbpLaunchModule(this.dataset.cbpModuleKey, this.dataset.cbpOpenNewTab === '1'); }"
                >
                    <i class="{{ $icon }} cbp-modules-dd-icon" aria-hidden="true"></i>
                    <span class="cbp-modules-dd-item-text">
                        <span class="cbp-modules-dd-item-label">{{ $label }}</span>
                    </span>
                </button>
                @else
                <a
                    href="{{ $href }}"
                    class="cbp-modules-dd-item{{ $active ? ' is-active' : '' }}"
                    role="menuitem"
                    @if ($absolute) target="_blank" rel="noopener noreferrer" @endif
                >
                    <i class="{{ $icon }} cbp-modules-dd-icon" aria-hidden="true"></i>
                    <span class="cbp-modules-dd-item-text">
                        <span class="cbp-modules-dd-item-label">{{ $label }}</span>
                    </span>
                </a>
                @endif
            @endforeach
        @else
            <p class="cbp-modules-dd-empty" role="status">No other CBP systems are assigned to your account.</p>
        @endif
        </div>
        <div data-cbp-pane="programs" hidden>
            @foreach ($cbpProgramGroups as $group)
                <p class="cbp-modules-dd-section">
                    {{ $group['title'] }}
                    <span class="cbp-modules-dd-count">{{ count($group['items'] ?? []) }}</span>
                </p>
                @foreach ($group['items'] ?? [] as $item)
                    <span class="cbp-modules-dd-item is-soon">
                        <i class="fa-solid {{ $item['icon'] }} cbp-modules-dd-icon" aria-hidden="true"></i>
                        <span class="cbp-modules-dd-item-text">
                            <span class="cbp-modules-dd-item-label">{{ $item['label'] }}</span>
                        </span>
                    </span>
                @endforeach
            @endforeach
        </div>
    </div>
</li>
<script src="{{ rtrim($cbpCssBase !== '' ? $cbpCssBase : ($staffWebBaseUrl ?? ''), '/') }}/assets/js/cbp-sso-launch.js" defer></script>
<script>
(function () {
    var root = document.getElementById('cbp-modules-dd');
    var btn = document.getElementById('cbp-modules-dd-btn');
    if (!root || !btn) {
        return;
    }
    function closeDd() {
        root.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
    }
    function toggleDd(e) {
        e.preventDefault();
        e.stopPropagation();
        var open = root.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    btn.addEventListener('click', toggleDd);
    root.querySelectorAll('[data-cbp-scope]').forEach(function (scopeBtn) {
        scopeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var scope = scopeBtn.getAttribute('data-cbp-scope');
            root.querySelectorAll('[data-cbp-scope]').forEach(function (other) {
                other.classList.toggle('is-active', other === scopeBtn);
            });
            root.querySelectorAll('[data-cbp-pane]').forEach(function (pane) {
                pane.hidden = pane.getAttribute('data-cbp-pane') !== scope;
            });
        });
    });
    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) {
            closeDd();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDd();
        }
    });
})();
</script>
