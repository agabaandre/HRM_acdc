
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
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    transform: scale(1.02);
  }
  .widgets-icons i {
    font-size: 1.2rem;
  }
</style>

<div class="container">
  <div class="row justify-content-between align-items-center mb-3">
    <div class="col-md-6">
      <h5 class="mb-0">Settings</h5>
    </div>
    <div class="col-md-6 text-end">
      <input type="text" id="settingsSearch" class="form-control form-control-sm settings-search" placeholder="Search settings...">
    </div>
  </div>

  <div class="row" id="settingsContainer">
    <?php
    $settings = [
      ['settings/nationalities', 'Nationalities', 'bx-globe'],
      ['settings/duty_stations', 'Duty Stations', 'bx-map'],
      ['settings/contracting_institutions', 'Contracting Institutions', 'bx-network-chart'],
      ['settings/contract_types', 'Contract Types', 'bx-group'],
      ['settings/divisions', 'Divisions', 'bx-sitemap'],
      ['settings/grades', 'Grades', 'bx-bar-chart-alt-2'],
      ['settings/jobs', 'Jobs', 'bx-briefcase'],
      ['settings/jobs_acting', 'Jobs Acting', 'bx-id-card'],
      ['settings/au_values', 'AU Values', 'bx-star'],
      ['settings/funders', 'Funders', 'bx-dollar'],
      ['settings/leave_types', 'Leave Types', 'bx-time-five'],
      ['settings/sysvariables', 'System Variables', 'bx-cog'],
      ['settings/training_skills', 'Training Skills', 'bx-book'],
      ['settings/regions', 'Regions', 'bx-compass'],
      ['settings/units', 'Units', 'bx-building'],
      ['settings/ppa_variables', 'PPA Configuration', 'bx-slider-alt']
    ];

    foreach ($settings as [$path, $label, $icon]) :
    ?>
      <div class="col-6 col-md-4 col-lg-3 mb-3 setting-card-item" data-title="<?= strtolower($label) ?>">
        <a href="<?= base_url($path) ?>" class="text-decoration-none text-dark">
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
  document.getElementById("settingsSearch").addEventListener("keyup", function () {
    let filter = this.value.toLowerCase();
    document.querySelectorAll(".setting-card-item").forEach(function (card) {
      card.style.display = card.getAttribute("data-title").includes(filter) ? "block" : "none";
    });
  });
</script>
