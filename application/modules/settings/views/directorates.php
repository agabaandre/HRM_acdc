<?php
/** @var CI_Controller $this */
$lists = $this->staff_mdl->get_all_staff_data([]);
?>
<div class="container-fluid">
  <div class="row">
    <!-- Add Directorate Form -->
    <div class="col-md-5">
      <div class="card border border-secondary">
        <div class="card-header bg-light">
          <h5 class="mb-0">Add New Directorate</h5>
        </div>
        <div class="card-body">
          <?= form_open('settings/add_content') ?>
          <input type="hidden" name="table" value="directorates">
          <input type="hidden" name="redirect" value="directorates">

          <div class="mb-3">
            <label class="form-label">Directorate Name</label>
            <input type="text" name="directorate_name" class="form-control" placeholder="Enter directorate name" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Is Active?</label>
            <select name="is_active" class="form-select" required>
              <option value="1">Yes</option>
              <option value="0">No</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Director</label>
            <select name="director_id" class="form-control select2" data-placeholder="Select director (optional)">
              <option value="">— None —</option>
              <?php foreach ($lists as $staff): ?>
                <option value="<?= (int) $staff->staff_id ?>"><?= htmlspecialchars($staff->lname . ' ' . $staff->fname, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Optional. Same staff id as division director fields (<code>staff.staff_id</code>).</small>
          </div>

          <div class="text-end">
            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Save</button>
            <button type="reset" class="btn btn-secondary"><i class="fa fa-undo"></i> Reset</button>
          </div>
          <?= form_close(); ?>
        </div>
      </div>
    </div>

    <!-- List of Directorates -->
    <div class="col-md-7">
      <div class="card border border-secondary">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Directorates List</h5>
        </div>
        <div class="card-body table-responsive">
          <table class="table mydata table-striped table-bordered">
            <thead>
              <tr>
                <th>#</th>
                <th>Directorate Name</th>
                <th>Director</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($directorates->result())): ?>
                <?php $no = 1; foreach ($directorates->result() as $dir): ?>
                  <tr>
                    <td><?= $no++ ?>.</td>
                    <td><?= htmlspecialchars($dir->name, ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                      <?php
                      $dirId = isset($dir->director_id) ? (int) $dir->director_id : 0;
                      if ($dirId > 0 && (!empty($dir->director_fname) || !empty($dir->director_lname))) {
                          echo htmlspecialchars(trim(($dir->director_lname ?? '') . ' ' . ($dir->director_fname ?? '')), ENT_QUOTES, 'UTF-8');
                      } elseif ($dirId > 0) {
                          echo '<span class="text-muted">Staff #' . $dirId . '</span>';
                      } else {
                          echo '<span class="text-muted">—</span>';
                      }
                      ?>
                    </td>
                    <td>
                      <span class="badge bg-<?= $dir->is_active ? 'success' : 'danger' ?>">
                        <?= $dir->is_active ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td><?= $dir->created_at ? date('d M Y', strtotime($dir->created_at)) : '-' ?></td>
                    <td>
                      <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editDirectorate<?= $dir->id ?>">
                        <i class="fa fa-edit"></i> Edit
                      </button>
                      <!-- Edit Modal -->
                      <div class="modal fade" id="editDirectorate<?= $dir->id ?>" tabindex="-1" aria-labelledby="editDirectorateLabel<?= $dir->id ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-sm">
                          <div class="modal-content">
                            <?= form_open('settings/update_content') ?>
                            <input type="hidden" name="table" value="directorates">
                            <input type="hidden" name="column_name" value="id">
                            <input type="hidden" name="caller_value" value="<?= $dir->id ?>">
                            <input type="hidden" name="redirect" value="directorates">

                            <div class="modal-header">
                              <h5 class="modal-title" id="editDirectorateLabel<?= $dir->id ?>">Edit Directorate</h5>
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
                                  <?php
                                  $currentDirector = isset($dir->director_id) ? (int) $dir->director_id : 0;
                                  foreach ($lists as $staff):
                                      $sid = (int) $staff->staff_id;
                                  ?>
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
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted">No directorates found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
$(function () {
  if (!$.fn.select2) {
    return;
  }
  $('select.select2').each(function () {
    var $el = $(this);
    if ($el.data('select2') || $el.closest('.modal').length) {
      return;
    }
    $el.select2({ width: '100%', placeholder: $el.data('placeholder') || '' });
  });
  $(document).on('shown.bs.modal', '.modal', function () {
    $(this).find('select.select2').each(function () {
      var $el = $(this);
      if ($el.data('select2')) {
        return;
      }
      $el.select2({
        dropdownParent: $el.closest('.modal'),
        width: '100%',
        placeholder: $el.data('placeholder') || ''
      });
    });
  });
});
</script>
