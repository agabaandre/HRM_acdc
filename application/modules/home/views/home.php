<style>
body {
  background: linear-gradient(to bottom, #f9f9f9, #eaeaea);
  font-family: "Segoe UI", sans-serif;
}

.settings-search {
  margin: 2rem auto 2rem;
  max-width: 400px;
  text-align: center;
}

.container {
  background: linear-gradient(to bottom, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8)), url('<?= base_url() ?>assets/images/bg_login.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-attachment: fixed;
  border-radius: 1rem;
  padding: 2rem;
  margin-top: 1rem;
  margin-bottom: 1rem;
}

.settings-card {
  height: 200px;
  padding: 1.5rem;
  transition: all 0.3s ease-in-out;
  font-size: 0.9rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  border-radius: 1rem;
  background: white;
  border: 1px solid #ccc;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  position: relative;
  overflow: hidden;
}

.settings-card:hover {
  box-shadow: 0 0 12px #911c3966;
  transform: translateY(-4px);
}

.settings-card h6 {
  font-weight: 700;
  font-size: 1.1rem;
  color: #911C39;
  margin-bottom: 0.5rem;
}

.settings-card p {
  font-size: 0.85rem;
  color: #5F5F5F;
  margin: 0;
}

.widgets-icons i {
  width: 60px;
  height: 60px;
  font-size: 1.6rem;
  color: #C3A366;
  background: #f4f4f4;
  border-radius: 50%;
  box-shadow: 0 0 4px rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: auto;
}

.settings-title {
  font-size: 1.4rem;
  color: #119A48;
  font-weight: bold;
  text-align: center;
  margin-top: 2rem;
}

@media (min-width: 768px) {
  .setting-card-item {
    flex: 0 0 50%;
    max-width: 50%;
  }
}
</style>

<div class="container">
  <div class="row g-4 justify-content-center" id="settingsContainer">
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
