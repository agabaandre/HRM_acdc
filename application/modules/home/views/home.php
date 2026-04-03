<style>
:root {
  --cbp-primary: #119a48;
  --cbp-primary-light: #1bb85a;
  --cbp-text-dark: #2c3e50;
  --cbp-text-muted: #6c757d;
  --cbp-medium-grey: #e9ecef;
  --cbp-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  --cbp-shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
  --cbp-transition: all 0.2s ease;
}

/* Full-page background only on home (this view loads only here) */
body {
  background-image: url('<?= base_url() ?>assets/images/bg_login.jpg');
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center center;
  background-attachment: fixed;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  min-height: 100vh;
}

/* Single panel: styling lives on template wrapper .cbp-home-shell-inner */
.cbp-home-shell-inner {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  padding: 2rem 1.25rem;
  margin: 0 auto;
  max-width: 1200px;
  box-shadow: var(--cbp-shadow-lg);
  position: relative;
  overflow: hidden;
  border-radius: 0.5rem;
}

.cbp-home-shell-inner::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--cbp-primary) 0%, var(--cbp-primary-light) 100%);
}

.cbp-home {
  position: relative;
  z-index: 1;
}

.cbp-home-title {
  font-size: clamp(1.35rem, 3vw, 2rem);
  color: var(--cbp-primary);
  font-weight: 700;
  text-align: center;
  margin-bottom: 1.75rem;
  position: relative;
  line-height: 1.3;
}

.cbp-home-title::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 60px;
  height: 3px;
  background: var(--cbp-primary);
}

.cbp-home-search {
  max-width: 420px;
  margin: 0 auto 1.75rem;
}

.cbp-home-search .form-control:focus {
  border-color: var(--cbp-primary);
  box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.15);
}

.cbp-home .settings-card {
  min-height: 220px;
  height: 100%;
  padding: 1.75rem 1.25rem;
  transition: var(--cbp-transition);
  font-size: 0.9rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  background: #fff;
  border: 1px solid var(--cbp-medium-grey);
  box-shadow: var(--cbp-shadow);
  position: relative;
  overflow: hidden;
  width: 100%;
  max-width: 100%;
  border-radius: 0.5rem;
}

.cbp-home .settings-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--cbp-primary);
  transform: scaleX(0);
  transition: var(--cbp-transition);
}

.cbp-home .settings-card:hover {
  box-shadow: var(--cbp-shadow-lg);
  transform: translateY(-2px);
  border-color: var(--cbp-primary);
}

.cbp-home .settings-card:hover::before {
  transform: scaleX(1);
}

.cbp-home .settings-card h6 {
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--cbp-text-dark);
  margin-bottom: 0.65rem;
  line-height: 1.3;
  position: relative;
  z-index: 2;
}

.cbp-home .settings-card p {
  font-size: 0.875rem;
  color: var(--cbp-text-muted);
  margin: 0;
  line-height: 1.5;
  flex-grow: 1;
  position: relative;
  z-index: 2;
}

.cbp-home .widgets-icons {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1;
  pointer-events: none;
}

.cbp-home .widgets-icons i {
  width: 120px;
  height: 120px;
  font-size: 4.25rem;
  color: rgba(17, 154, 72, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  transition: var(--cbp-transition);
}

.cbp-home .settings-card:hover .widgets-icons i {
  color: rgba(17, 154, 72, 0.16);
  transform: scale(1.02);
}

.cbp-home .setting-card-item a {
  text-decoration: none;
  color: inherit;
  display: block;
  height: 100%;
}

.cbp-home .setting-card-item a:hover {
  text-decoration: none;
  color: inherit;
}

.cbp-home .settings-card {
  animation: cbpFadeInUp 0.55s ease forwards;
}

.cbp-home .setting-card-item:nth-child(1) .settings-card { animation-delay: 0.05s; }
.cbp-home .setting-card-item:nth-child(2) .settings-card { animation-delay: 0.1s; }
.cbp-home .setting-card-item:nth-child(3) .settings-card { animation-delay: 0.15s; }
.cbp-home .setting-card-item:nth-child(4) .settings-card { animation-delay: 0.2s; }

@keyframes cbpFadeInUp {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.cbp-home-footer {
  margin-top: 1.75rem;
  padding-top: 1rem;
  border-top: 1px solid var(--cbp-medium-grey);
}

@media (min-width: 768px) {
  .cbp-home-shell-inner {
    padding: 2.5rem 2rem;
    margin-top: 0.5rem;
    margin-bottom: 1rem;
  }
}

@media (max-width: 767.98px) {
  .cbp-home .settings-card {
    min-height: 200px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .cbp-home .settings-card {
    animation: none;
  }
  .cbp-home .settings-card,
  .cbp-home .widgets-icons i {
    transition: none;
  }
}
</style>

<div class="cbp-home">
  <h1 class="cbp-home-title">Welcome to Africa CDC Central Business Platform</h1>
  <div class="cbp-home-search">
    <label for="cbpHomeModuleSearch" class="visually-hidden">Search modules</label>
    <div class="input-group input-group-sm shadow-sm">
      <span class="input-group-text bg-white border-end-0" aria-hidden="true"><i class="fas fa-search text-muted"></i></span>
      <input type="search" id="cbpHomeModuleSearch" class="form-control border-start-0" placeholder="Search modules by name or description…" autocomplete="off" spellcheck="false">
    </div>
  </div>
  <p id="cbpHomeSearchEmpty" class="text-center text-muted small d-none mb-0">No modules match your search.</p>
  <div class="row row-cols-1 row-cols-md-3 g-4 justify-content-center" id="settingsContainer">
    <?php
    $cbp_home_modules = isset($cbp_home_modules) && is_array($cbp_home_modules) ? $cbp_home_modules : [];
    foreach ($cbp_home_modules as $mod) :
      $href = $mod['absolute'] ? $mod['href'] : base_url($mod['href']);
      $label = $mod['label'];
      $icon = $mod['icon'];
      $desc = $mod['desc'];
    ?>
    <?php
      $modKey = isset($mod['module_key']) ? (string) $mod['module_key'] : '';
      if ($modKey === '') {
        $modKey = 'mod_' . md5($href . $label);
      }
    ?>
    <div class="col setting-card-item" data-module-key="<?= htmlspecialchars($modKey) ?>" data-title="<?= strtolower(htmlspecialchars($label)) ?>">
      <a href="<?= htmlspecialchars($href) ?>" class="text-decoration-none d-flex h-100">
        <div class="settings-card w-100">
          <div>
            <h6><?= htmlspecialchars($label) ?></h6>
            <p><?= htmlspecialchars($desc) ?></p>
          </div>
          <div class="widgets-icons"><i class="fas <?= htmlspecialchars($icon) ?>"></i></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <footer class="cbp-home-footer">
    <?php
    $apm_base = $this->config->item('apm_base_url');
    if (empty($apm_base)) {
      $apm_base = rtrim(base_url(), '/') . '/apm';
    }
    $apm_base = rtrim($apm_base, '/');
    if (!empty($apm_base) && strpos($apm_base, 'http') !== 0) {
      $apm_base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ltrim($apm_base, '/');
    }
    ?>
    <p class="mb-0 text-center text-md-start">
      <a href="<?= htmlspecialchars($apm_base) ?>/faq" target="_blank" rel="noopener" class="text-decoration-none me-3" style="color: var(--cbp-primary);">FAQs</a>
      <a href="<?= htmlspecialchars($apm_base) ?>/help" target="_blank" rel="noopener" class="text-decoration-none me-3" style="color: var(--cbp-primary);">Help</a>
    </p>
  </footer>
</div>

<script>
(function () {
  var input = document.getElementById('cbpHomeModuleSearch');
  var container = document.getElementById('settingsContainer');
  var emptyMsg = document.getElementById('cbpHomeSearchEmpty');
  if (!input || !container) return;

  var searchUrl = <?= json_encode(site_url('home/module_search')) ?>;

  function allModuleKeysFromDom() {
    return Array.prototype.map.call(container.querySelectorAll('.setting-card-item'), function (el) {
      return el.getAttribute('data-module-key') || '';
    }).filter(Boolean);
  }

  function applyModuleKeys(keys) {
    var set = new Set(keys);
    var visible = 0;
    container.querySelectorAll('.setting-card-item').forEach(function (el) {
      var k = el.getAttribute('data-module-key') || '';
      var show = k && set.has(k);
      el.classList.toggle('d-none', !show);
      if (show) visible++;
    });
    if (emptyMsg) {
      var q = input.value.trim();
      emptyMsg.classList.toggle('d-none', visible > 0 || q === '');
    }
  }

  function runSearch() {
    var q = input.value.trim();
    var url = searchUrl + (searchUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
    fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (res) {
        if (!res.ok) throw new Error('bad status');
        return res.json();
      })
      .then(function (data) {
        if (data && data.ok && Array.isArray(data.module_keys)) {
          applyModuleKeys(data.module_keys);
        } else {
          applyModuleKeys(allModuleKeysFromDom());
        }
      })
      .catch(function () {
        applyModuleKeys(allModuleKeysFromDom());
      });
  }

  var debounceTimer;
  function debouncedSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runSearch, 280);
  }

  input.addEventListener('input', debouncedSearch);
  input.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      input.value = '';
      runSearch();
    }
  });
})();
</script>
