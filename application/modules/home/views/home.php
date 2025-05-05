<style>
.settings-search {
  margin-bottom: 1rem;
}

.settings-card {
  min-height: 110px;
  padding: 0.5rem 1rem;
  transition: all 0.2s ease-in-out;
  font-size: 0.9rem;
}

.settings-card:hover {
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
  transform: scale(1.02);
}

.widgets-icons i {
  font-size: 1.2rem;
}
</style>

<div class="container">
  <div class="row justify-content-between align-items-center mb-3">
    <div class="col-md-6">
      <h5 class="mb-0">Home</h5>
    </div>
    <div class="col-md-6 text-end">
      <input type="text" id="settingsSearch" class="form-control form-control-sm settings-search"
        placeholder="Search settings...">
    </div>
  </div>

  <div class="row" id="settingsContainer">
    <?php
    $session = (array) $this->session->userdata('user');
    $session['base_url'] = base_url();
    $settings = [
      ['staff/dashboard', 'HR Dashbaord', 'bx-user', false],//admin /dashboard
      ['http://localhost/laravel?token='.urlencode(base64_encode(json_encode($session))), 'BMS', 'bx-building', true],
    ];

    foreach ($settings as [$path, $label, $icon, $absolute]) :
    ?>
    <div class="col-6 col-md-4 col-lg-4 mb-3 setting-card-item" data-title="<?= strtolower($label) ?>">
      <a href="<?= $absolute ? $path : base_url($path) ?>" class="text-decoration-none text-dark">
        <div class="card border border-secondary rounded-2 overflow-hidden settings-card">
          <div class="card-body d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><?= $label ?></h6>
            <div class="widgets-icons text-muted"><i class='bx <?= $icon ?>'></i></div>
          </div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
document.getElementById("settingsSearch").addEventListener("keyup", function() {
  let filter = this.value.toLowerCase();
  document.querySelectorAll(".setting-card-item").forEach(function(card) {
    card.style.display = card.getAttribute("data-title").includes(filter) ? "block" : "none";
  });
});
</script>