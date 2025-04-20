<div class="row">
  <div class="col-md-12">
    <div class="card border shadow-sm">
      <div class="card-header bg-light">
        <h5 class="mb-0">PPA System Configuration</h5>
      </div>

      <div class="card-body">
        <?= form_open(base_url('settings/ppa_variables')) ?>

        <div class="row g-3">
          <?php foreach ($setting as $key => $value): ?>
            <div class="col-md-<?= ($key === 'id') ? '12' : '6' ?>">
              <label class="form-label text-uppercase small fw-semibold"><?= str_replace("_", " ", $key) ?></label>
              <input 
                type="text" 
                name="<?= $key ?>" 
                class="form-control <?= ($key === 'id') ? 'bg-light' : '' ?>" 
                value="<?= $value ?>" 
                <?= ($key === 'id') ? 'readonly' : '' ?>>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="mt-4 text-end">
          <button class="btn btn-success px-4" type="submit">
            <i class="fa fa-save me-1"></i> Save Settings
          </button>
        </div>

        <?= form_close() ?>
      </div>
    </div>
  </div>
</div>
