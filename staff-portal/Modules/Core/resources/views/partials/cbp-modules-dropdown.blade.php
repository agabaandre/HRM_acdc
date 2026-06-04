@php
    $cbp_nav_home = $home ?? ['label' => 'CBP Home', 'href' => route('core.home'), 'is_active' => false];
    $cbp_nav_modules = $modules ?? [];
    $cbp_nav_home_url = $cbp_nav_home['href'] ?? route('core.home');
    $cbp_nav_home_label = $cbp_nav_home['label'] ?? 'CBP Home';
    $cbp_nav_home_active = ! empty($cbp_nav_home['is_active']);
    $cbp_toggle_active = $cbp_nav_home_active;
    foreach ($cbp_nav_modules as $_m) {
        if (! empty($_m['is_active'])) {
            $cbp_toggle_active = true;
            break;
        }
    }
@endphp
<li class="nav-item cbp-modules-dd" id="cbp-modules-dd">
    <button type="button"
            class="cbp-modules-dd-toggle nav-link border-0{{ $cbp_toggle_active ? ' is-active' : '' }}"
            id="cbp-modules-dd-btn"
            aria-haspopup="true"
            aria-expanded="false"
            aria-controls="cbp-modules-dd-panel"
            title="CBP Modules">
        <i class="bx bx-category" style="color: #fff; font-size: 1.1rem;" aria-hidden="true"></i>
        <span class="cbp-modules-dd-label ms-2 d-none d-md-inline" style="color: #fff; font-size: 0.875rem;">CBP Modules</span>
        <span class="cbp-modules-dd-caret d-none d-md-inline" aria-hidden="true">▼</span>
    </button>
    <div class="cbp-modules-dd-panel" id="cbp-modules-dd-panel" role="menu" aria-labelledby="cbp-modules-dd-btn">
        <a href="{{ $cbp_nav_home_url }}" class="cbp-modules-dd-primary{{ $cbp_nav_home_active ? ' is-active' : '' }}" role="menuitem">
            <span class="cbp-modules-dd-primary-title">{{ $cbp_nav_home_label }}</span>
        </a>
        @if (count($cbp_nav_modules) > 0)
            <p class="cbp-modules-dd-section">Systems</p>
            @foreach ($cbp_nav_modules as $mod)
                @php
                    $href = $mod['href'] ?? '#';
                    $label = $mod['label'] ?? 'Module';
                    $icon = \App\Support\CbpIcon::classes($mod['icon'] ?? null);
                    $absolute = ! empty($mod['opens_in_new_tab']);
                @endphp
                <a href="{{ $href }}"
                   class="cbp-modules-dd-item{{ ! empty($mod['is_active']) ? ' is-active' : '' }}"
                   role="menuitem"
                   @if ($absolute) target="_blank" rel="noopener noreferrer" @endif>
                    <i class="{{ $icon }} cbp-modules-dd-icon" aria-hidden="true"></i>
                    <span class="cbp-modules-dd-item-text">
                        <span class="cbp-modules-dd-item-label">{{ $label }}</span>
                    </span>
                </a>
            @endforeach
        @endif
    </div>
</li>
<script>
(function () {
    var root = document.getElementById('cbp-modules-dd');
    var btn = document.getElementById('cbp-modules-dd-btn');
    if (!root || !btn) return;
    function closeDd() {
        root.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
    }
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var open = root.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (!root.contains(e.target)) closeDd();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDd();
    });
})();
</script>
