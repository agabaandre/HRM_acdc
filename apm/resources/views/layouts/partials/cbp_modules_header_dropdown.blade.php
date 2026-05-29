@php
    $cbpNav = $cbpModulesNav ?? ['home' => ['label' => 'CBP Home', 'href' => ($staffWebBaseUrl ?? '') . '/home/index', 'is_active' => false], 'modules' => []];
    $cbpHome = $cbpNav['home'] ?? [];
    $cbpModules = $cbpNav['modules'] ?? [];
    $cbpHomeHref = (string) ($cbpHome['href'] ?? ($staffWebBaseUrl ?? '') . '/home/index');
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
@endphp
<link rel="stylesheet" href="{{ $cbpCssBase }}/assets/css/cbp-modules-nav.css">

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
        <a
            href="{{ $cbpHomeHref }}"
            class="cbp-modules-dd-primary{{ $cbpHomeActive ? ' is-active' : '' }}"
            role="menuitem"
        >
            <span class="cbp-modules-dd-primary-title">{{ $cbpHomeLabel }}</span>
        </a>
        @if (count($cbpModules) > 0)
            <p class="cbp-modules-dd-section">Systems</p>
            @foreach ($cbpModules as $mod)
                @php
                    $href = (string) ($mod['href'] ?? '#');
                    $label = (string) ($mod['label'] ?? 'Module');
                    $icon = trim((string) ($mod['icon'] ?? 'fa-th'));
                    if ($icon !== '' && !str_starts_with($icon, 'fa ')) {
                        if (str_starts_with($icon, 'fa-')) {
                            $icon = 'fa ' . $icon;
                        }
                    }
                    $absolute = !empty($mod['opens_in_new_tab']);
                    $active = !empty($mod['is_active']);
                @endphp
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
            @endforeach
        @else
            <p class="cbp-modules-dd-empty" role="status">No other CBP systems are assigned to your account.</p>
        @endif
    </div>
</li>
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
