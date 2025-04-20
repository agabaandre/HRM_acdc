<div class="row">
  <div class="col-md-12">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-success text-white">
        <h4 class="card-title mb-0"><i class="fa fa-cogs me-2"></i>System Variables</h4>
      </div>

      <div class="card-body bg-light">
        <?= form_open(base_url('settings/sysvariables'), ['class' => 'p-3']) ?>
      
        <div class="row">
          <?php foreach ($setting as $key => $value): ?>
            <?php if ($key !== 'id'): ?>
              <div class="col-md-6 mb-4">
                <label class="form-label text-uppercase fw-semibold text-success"><?= ucwords(str_replace("_", " ", $key)) ?></label>
                <textarea name="<?= $key ?>" rows="4" class="form-control shadow-sm"><?= htmlspecialchars($value) ?></textarea>
              </div>
            <?php else: ?>
              <input type="hidden" name="id" value="<?= htmlspecialchars($value) ?>">
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <div class="text-end mt-3">
          <button type="submit" class="btn btn-success px-4">
            <i class="fa fa-save me-1"></i> Save Changes
          </button>
        </div>
        <?= form_close(); ?>
      </div>
    </div>
  </div>
</div>
