<div class="modal fade" id="update_divisions<?= $division->division_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="add_item_label">Edit Division: <?= $division->division_name; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <?= validation_errors(); ?>
        <?= form_open('settings/update_content'); ?>
        <input type="hidden" name="table" value="divisions">
        <input type="hidden" name="redirect" value="division">
        <input type="hidden" name="column_name" value="division_id">
        <input type="hidden" name="caller_value" value="<?= $division->division_id; ?>">


<div class="row">
  <div class="col-md-6">
    <div class="form-group">
      <label>Division Name</label>
      <input type="text" class="form-control" value="<?= $division->division_name; ?>" name="division_name" required>
    </div>
  </div>

  <div class="col-md-6">
    <div class="form-group">
      <label>Division Head</label>
      <select name="division_head" class="form-control select2" required>
        <option value="">Select  Head</option>
        <?php foreach ($lists as $staff): ?>
          <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == $division->focal_person) ? 'selected' : '' ?>>
            <?= $staff->lname . ' ' . $staff->fname ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-group">
      <label>Focal Person</label>
      <select name="focal_person" class="form-control select2" required>
        <option value="">Select Focal Person</option>
        <?php foreach ($lists as $staff): ?>
          <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == $division->focal_person) ? 'selected' : '' ?>>
            <?= $staff->lname . ' ' . $staff->fname ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="col-md-6">
    <div class="form-group">
      <label>Finance Officer</label>
      <select name="finance_officer" class="form-control select2" required>
        <option value="">Select Finance Officer</option>
        <?php foreach ($lists as $staff): ?>
          <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == $division->finance_officer) ? 'selected' : '' ?>>
            <?= $staff->lname . ' ' . $staff->fname ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="col-md-6">
    <div class="form-group">
      <label>Admin Assistant</label>
      <select name="admin_assistant" class="form-control select2" required>
        <option value="">Select Admin Assistant</option>
        <?php foreach ($lists as $staff): ?>
          <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == $division->admin_assistant) ? 'selected' : '' ?>>
            <?= $staff->lname . ' ' . $staff->fname ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</div>


        <div class="form-group text-end mt-4">
          <input type="submit" class="btn btn-dark" value="Update">
          <input type="reset" class="btn btn-danger" value="Reset">
        </div>

        <?= form_close(); ?>
      </div>
    </div>
  </div>
</div>
