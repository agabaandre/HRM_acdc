{{-- Top-bar CBP Modules menu (APM + Helpdesk). Data: PortalNavigation::cbpModulesPayload(). --}}
@php
    $cbp_nav_home = $home ?? ['label' => 'CBP Home', 'href' => route('core.home'), 'is_active' => false];
    $cbp_nav_modules = $modules ?? [];
    $cbp_nav_home_active = ! empty($cbp_nav_home['is_active']);
    $cbp_toggle_active = $cbp_nav_home_active;
    foreach ($cbp_nav_modules as $_m) {
        if (! empty($_m['is_active'])) {
            $cbp_toggle_active = true;
            break;
        }
    }
    $cbp_program_groups = [];
    $cbp_program_file = null;
    $cbp_program_dir = base_path();
    for ($cbp_i = 0; $cbp_i < 6; $cbp_i++) {
        $cbp_candidate = $cbp_program_dir.'/shared/cbp-program-modules.php';
        if (is_file($cbp_candidate)) {
            $cbp_program_file = $cbp_candidate;
            break;
        }
        $cbp_parent = dirname($cbp_program_dir);
        if ($cbp_parent === $cbp_program_dir) {
            break;
        }
        $cbp_program_dir = $cbp_parent;
    }
    if ($cbp_program_file) {
        $cbp_program_groups = require $cbp_program_file;
    }
    $cbp_program_count = 0;
    foreach ($cbp_program_groups as $cbp_group) {
        $cbp_program_count += count($cbp_group['items'] ?? []);
    }
    $cbp_operations_count = count($cbp_nav_modules);
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
        <a href="{{ $cbp_nav_home['href'] ?? '/' }}" class="cbp-modules-dd-home" role="menuitem">CBP HOME</a>
        <div class="cbp-modules-dd-scope" role="tablist">
            <button type="button" class="is-active" data-cbp-scope="operations">
                CBP Operations <span class="cbp-modules-dd-count">{{ $cbp_operations_count }}</span>
            </button>
            <button type="button" data-cbp-scope="programs">
                CBP Programs <span class="cbp-modules-dd-count">{{ $cbp_program_count }}</span>
            </button>
        </div>
        <div data-cbp-pane="operations">
            @if (count($cbp_nav_modules) > 0)
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
            @else
                <p class="cbp-modules-dd-empty" role="status">No other CBP systems are assigned to your account.</p>
            @endif
        </div>
        <div data-cbp-pane="programs" hidden>
            @foreach ($cbp_program_groups as $group)
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
        if (!root.contains(e.target)) closeDd();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDd();
    });
})();
</script>
