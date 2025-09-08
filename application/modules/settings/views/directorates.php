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
                    <td><?= $dir->name ?></td>
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
                <tr><td colspan="5" class="text-center text-muted">No directorates found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
