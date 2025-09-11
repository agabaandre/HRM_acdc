<style>
:root {
  --primary-color: #119a48;
  --primary-dark: #0d7a3a;
  --primary-light: #1bb85a;
  --secondary-color: #9f2240;
  --secondary-light: #c44569;
  --accent-black: #2c3e50;
  --light-grey: #f8f9fa;
  --medium-grey: #e9ecef;
  --dark-grey: #6c757d;
  --text-dark: #2c3e50;
  --text-muted: #6c757d;
  --border-color: #e9ecef;
  --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
  --transition: all 0.2s ease;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  background-image: url('<?= base_url() ?>assets/images/bg_login.jpg');
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center center;
  background-attachment: fixed;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  min-height: 100vh;
  padding: 20px;
}

.settings-search {
  margin: 2rem auto 2rem;
  max-width: 400px;
  text-align: center;
}

.container {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  padding: 3rem 2rem;
  margin: 2rem auto;
  max-width: 1200px;
  box-shadow: var(--shadow-lg);
  position: relative;
  overflow: hidden;
}

.container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--primary-color) 0%, var(--primary-light) 100%);
}

.settings-card {
  height: 250px;
  padding: 2.5rem;
  transition: var(--transition);
  font-size: 0.9rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  background: white;
  border: 1px solid var(--medium-grey);
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
  margin-bottom: 1.5rem;
  width: 115%;
  max-width: 115%;
}

.settings-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--primary-color);
  transform: scaleX(0);
  transition: var(--transition);
}

.settings-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
  border-color: var(--primary-color);
}

.settings-card:hover::before {
  transform: scaleX(1);
}

.settings-card h6 {
  font-weight: 700;
  font-size: 1.2rem;
  color: var(--text-dark);
  margin-bottom: 0.8rem;
  line-height: 1.3;
  position: relative;
  z-index: 2;
}

.settings-card p {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.5;
  flex-grow: 1;
  position: relative;
  z-index: 2;
}

.widgets-icons {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1;
  pointer-events: none;
}

.widgets-icons i {
  width: 140px;
  height: 140px;
  font-size: 5rem;
  color: rgba(17, 154, 72, 0.12);
  background: transparent;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  transition: var(--transition);
}

.settings-card:hover .widgets-icons i {
  color: rgba(17, 154, 72, 0.18);
  transform: scale(1.02);
}

.settings-title {
  font-size: 2rem;
  color: var(--primary-color);
  font-weight: 700;
  text-align: center;
  margin-bottom: 2rem;
  position: relative;
}

.settings-title::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 60px;
  height: 3px;
  background: var(--primary-color);
}

.setting-card-item {
  margin-bottom: 2.5rem;
  margin-right: 1rem;
  margin-left: 1rem;
}

.setting-card-item a {
  text-decoration: none;
  color: inherit;
  display: block;
  height: 100%;
}

.setting-card-item a:hover {
  text-decoration: none;
  color: inherit;
}

/* Loading animation for cards */
.settings-card {
  animation: fadeInUp 0.6s ease forwards;
}

.setting-card-item:nth-child(1) .settings-card { animation-delay: 0.1s; }
.setting-card-item:nth-child(2) .settings-card { animation-delay: 0.2s; }
.setting-card-item:nth-child(3) .settings-card { animation-delay: 0.3s; }
.setting-card-item:nth-child(4) .settings-card { animation-delay: 0.4s; }

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive Design */
@media (min-width: 768px) {
  .setting-card-item {
    flex: 0 0 48%;
    max-width: 48%;
  }
  
  .container {
    padding: 3rem;
  }
}

@media (min-width: 992px) {
  .setting-card-item {
    flex: 0 0 32%;
    max-width: 32%;
  }
}

@media (max-width: 767px) {
  .container {
    padding: 1.5rem;
    margin: 1rem;
  }
  
  .settings-card {
    height: auto;
    min-height: 220px;
    width: 100%;
    max-width: 100%;
  }
  
  .setting-card-item {
    margin-bottom: 2rem;
    margin-right: 0.5rem;
    margin-left: 0.5rem;
  }
  
  .settings-title {
    font-size: 1.5rem;
  }
}

/* Accessibility improvements */
.settings-card:focus-within {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .settings-card {
    border-width: 3px;
  }
  
  .widgets-icons i {
    border-width: 3px;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>

<div class="container">
  <h1 class="settings-title">Welcome to Africa CDC Central Business Platform</h1>
  <div class="row g-5 justify-content-center" id="settingsContainer">
    <?php
    $sessionobj = $this->session->userdata('user');
    $permissions = $sessionobj->permissions;

    $session = (array) $sessionobj;
    $session['base_url'] = base_url();
    $settings = [];

    // HR Dashboard
    if (in_array('84', $permissions)) {
      $hrPath = ($session['role'] == 17) ? 'auth/profile' : 'dashboard';
      $settings[] = [
        $hrPath,
        'Staff Portal',
        'fa-users',
        false,
        'Manage staff details, contracts, appraisals and access HR services efficiently.'
      ];
    }

    // ap
    if (in_array('85', $permissions)) {
      $token = urlencode(base64_encode(json_encode($session)));
      $settings[] = [
        $session['base_url'] . 'apm?token=' . $token,
        'Approvals Management (APM)',
        'fa-sitemap',
        true,
        'Tracks submissions, reviews, and approvals ie requests for Travel Matrix, Single and Special Memos, Change, DSA and ARF requests'
      ];
    }

    foreach ($settings as [$path, $label, $icon, $absolute, $desc]) :
    ?>
    <div class="col-12 col-md-6 setting-card-item" data-title="<?= strtolower($label) ?>">
      <a href="<?= $absolute ? $path : base_url($path) ?>" class="text-decoration-none">
        <div class="settings-card">
          <div>
            <h6><?= $label ?></h6>
            <p><?= $desc ?></p>
          </div>
          <div class="widgets-icons"><i class="fas <?= $icon ?>"></i></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
document.getElementById("settingsSearch")?.addEventListener("keyup", function () {
  let filter = this.value.toLowerCase();
  document.querySelectorAll(".setting-card-item").forEach(function (card) {
    card.style.display = card.getAttribute("data-title").includes(filter) ? "block" : "none";
  });
});
</script>
