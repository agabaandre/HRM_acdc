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
          <!-- Basic Fields -->
          <div class="col-md-6">
            <div class="form-group">
              <label>Division Name</label>
              <input type="text" class="form-control" name="division_name" value="<?= $division->division_name; ?>" required>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label>Category</label>
              <select name="category" class="form-control" required>
                <option value="">Select Category</option>
                <option value="Programs" <?= $division->category == 'Programs' ? 'selected' : '' ?>>Programs</option>
                <option value="Operations" <?= $division->category == 'Operations' ? 'selected' : '' ?>>Operations</option>
                <option value="Other" <?= $division->category == 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
          </div>

          <?php
          $fields = [
            'division_head' => 'Division Head',
            'focal_person' => 'Focal Person',
            'finance_officer' => 'Finance Officer',
            'admin_assistant' => 'Admin Assistant',
            'directorate_id' => 'Directorate',
            'head_oic_id' => 'Division Head OIC',
            'director_id' => 'Director',
            'director_oic_id' => 'Director OIC',
          ];
          foreach ($fields as $key => $label): ?>
            <div class="col-md-6 mt-2">
              <div class="form-group">
                <label><?= $label ?></label>
                <select name="<?= $key ?>" class="form-control select2">
                  <option value="">Select <?= $label ?></option>
                  <?php foreach ($lists as $staff): ?>
                    <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->$key) ? 'selected' : '' ?>>
                      <?= $staff->lname . ' ' . $staff->fname ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          <?php endforeach; ?>

          <!-- OIC Dates -->
          <div class="col-md-6 mt-2">
            <div class="form-group">
              <label>Head OIC Start Date</label>
              <input type="date" class="form-control" name="head_oic_start_date" value="<?= $division->head_oic_start_date ?>">
            </div>
          </div>
          <div class="col-md-6 mt-2">
            <div class="form-group">
              <label>Head OIC End Date</label>
              <input type="date" class="form-control" name="head_oic_end_date" value="<?= $division->head_oic_end_date ?>">
            </div>
          </div>

          <div class="col-md-6 mt-2">
            <div class="form-group">
              <label>Director OIC Start Date</label>
              <input type="date" class="form-control" name="director_oic_start_date" value="<?= $division->director_oic_start_date ?>">
            </div>
          </div>
          <div class="col-md-6 mt-2">
            <div class="form-group">
              <label>Director OIC End Date</label>
              <input type="date" class="form-control" name="director_oic_end_date" value="<?= $division->director_oic_end_date ?>">
            </div>
          </div>
        </div>

        <div class="form-group text-end mt-4">
          <button type="submit" class="btn btn-dark"><i class="fa fa-save"></i> Update</button>
          <button type="reset" class="btn btn-danger"><i class="fa fa-undo"></i> Reset</button>
        </div>
        <?= form_close(); ?>
      </div>
    </div>
  </div>
</div>
