<?php
/** @var CI_Controller $this */
if (!isset($lists)) {
    $lists = $this->staff_mdl->get_all_staff_data([]);
}
$currentDirector = isset($dir->director_id) ? (int) $dir->director_id : 0;
?>
<div class="modal fade" id="update_directorate<?= $dir->id ?>" tabindex="-1" aria-labelledby="updateDirectorateLabel<?= $dir->id ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <?= form_open('settings/update_content') ?>
      <input type="hidden" name="table" value="directorates">
      <input type="hidden" name="column_name" value="id">
      <input type="hidden" name="caller_value" value="<?= $dir->id ?>">
      <input type="hidden" name="redirect" value="directorates">

      <div class="modal-header">
        <h5 class="modal-title" id="updateDirectorateLabel<?= $dir->id ?>">Edit Directorate</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Directorate Name</label>
          <input type="text" class="form-control" name="directorate_name" value="<?= ($dir->name) ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Is Active?</label>
          <select class="form-select" name="is_active" required>
            <option value="1" <?= $dir->is_active ? 'selected' : '' ?>>Yes</option>
            <option value="0" <?= !$dir->is_active ? 'selected' : '' ?>>No</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Director</label>
          <select name="director_id" class="form-control select2" data-placeholder="Optional">
            <option value="">— None —</option>
            <?php foreach ($lists as $staff): ?>
              <?php $sid = (int) $staff->staff_id; ?>
              <option value="<?= $sid ?>" <?= $sid === $currentDirector ? 'selected' : '' ?>>
                <?= htmlspecialchars($staff->lname . ' ' . $staff->fname, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-dark"><i class="fa fa-save"></i> Update</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
      </div>
      <?= form_close(); ?>
    </div>
  </div>
</div>
