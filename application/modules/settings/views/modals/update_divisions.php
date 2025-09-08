<div class="modal fade" id="update_divisions<?= $division->division_id; ?>" tabindex="-1" aria-labelledby="edit_division_label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, rgba(52, 143, 65, 1) 0%, rgba(52, 143, 65, 0.8) 100%); color: white;">
        <h5 class="modal-title" id="edit_division_label">
          <i class="fas fa-edit me-2"></i>Edit Division: <?= $division->division_name; ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <?= validation_errors(); ?>
        <?= form_open('settings/update_content'); ?>
        <input type="hidden" name="table" value="divisions">
        <input type="hidden" name="redirect" value="divisions">
        <input type="hidden" name="column_name" value="division_id">
        <input type="hidden" name="caller_value" value="<?= $division->division_id; ?>">

        <table class="table table-borderless form-table">
          <tbody>
            <!-- Row 1: Basic Information -->
            <tr>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-building me-1 text-primary"></i>Division Name <span class="text-danger">*</span>
                  </label>
                  <input type="text" class="form-control" name="division_name" value="<?= $division->division_name; ?>" required>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-tag me-1 text-info"></i>Short Name
                  </label>
                  <input type="text" class="form-control" name="division_short_name" value="<?= $division->division_short_name; ?>" maxlength="50" placeholder="e.g., DHIS">
                  <small class="form-text text-muted">Optional: Short code</small>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-layer-group me-1 text-warning"></i>Category <span class="text-danger">*</span>
                  </label>
                  <select name="category" class="form-control" required>
                    <option value="">Select Category</option>
                    <option value="Programs" <?= $division->category == 'Programs' ? 'selected' : '' ?>>Programs</option>
                    <option value="Operations" <?= $division->category == 'Operations' ? 'selected' : '' ?>>Operations</option>
                    <option value="Other" <?= $division->category == 'Other' ? 'selected' : '' ?>>Other</option>
                  </select>
                </div>
              </td>
            </tr>

            <!-- Row 2: Key Personnel -->
            <tr>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-user-tie me-1 text-primary"></i>Division Head <span class="text-danger">*</span>
                  </label>
                  <select name="division_head" class="form-control select2" required>
                    <option value="">Select Division Head</option>
                    <?php foreach ($lists as $staff): ?>
                      <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->division_head) ? 'selected' : '' ?>>
                        <?= $staff->lname . ' ' . $staff->fname ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-user me-1 text-info"></i>Focal Person <span class="text-danger">*</span>
                  </label>
                  <select name="focal_person" class="form-control select2" required>
                    <option value="">Select Focal Person</option>
                    <?php foreach ($lists as $staff): ?>
                      <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->focal_person) ? 'selected' : '' ?>>
                        <?= $staff->lname . ' ' . $staff->fname ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-calculator me-1 text-success"></i>Finance Officer <span class="text-danger">*</span>
                  </label>
                  <select name="finance_officer" class="form-control select2" required>
                    <option value="">Select Finance Officer</option>
                    <?php foreach ($lists as $staff): ?>
                      <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->finance_officer) ? 'selected' : '' ?>>
                        <?= $staff->lname . ' ' . $staff->fname ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </td>
            </tr>

            <!-- Row 3: Support Staff -->
            <tr>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-user-cog me-1 text-warning"></i>Admin Assistant <span class="text-danger">*</span>
                  </label>
                  <select name="admin_assistant" class="form-control select2" required>
                    <option value="">Select Admin Assistant</option>
                    <?php foreach ($lists as $staff): ?>
                      <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->admin_assistant) ? 'selected' : '' ?>>
                        <?= $staff->lname . ' ' . $staff->fname ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-crown me-1 text-danger"></i>Director
                  </label>
                  <select name="director_id" class="form-control select2">
                    <option value="">Select Director (Optional)</option>
                    <?php foreach ($lists as $staff): ?>
                      <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->director_id) ? 'selected' : '' ?>>
                        <?= $staff->lname . ' ' . $staff->fname ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="form-text text-muted">Optional: Division director</small>
                </div>
              </td>
              <td class="form-cell">
                <!-- Empty cell for alignment -->
              </td>
            </tr>

            <!-- Row 4: Head OIC Information -->
            <tr>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-user-clock me-1 text-secondary"></i>Head OIC
                  </label>
                  <select name="head_oic_id" class="form-control select2">
                    <option value="">Select Head OIC (Optional)</option>
                    <?php foreach ($lists as $staff): ?>
                      <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->head_oic_id) ? 'selected' : '' ?>>
                        <?= $staff->lname . ' ' . $staff->fname ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="form-text text-muted">Optional: Officer in charge of division head</small>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-calendar-alt me-1 text-info"></i>Head OIC Start Date
                  </label>
                  <input type="text" class="form-control datepicker" name="head_oic_start_date" value="<?= $division->head_oic_start_date ?>" placeholder="Select start date">
                  <small class="form-text text-muted">Optional: When OIC period started</small>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-calendar-alt me-1 text-info"></i>Head OIC End Date
                  </label>
                  <input type="text" class="form-control datepicker" name="head_oic_end_date" value="<?= $division->head_oic_end_date ?>" placeholder="Select end date">
                  <small class="form-text text-muted">Optional: When OIC period ends</small>
                </div>
              </td>
            </tr>

            <!-- Row 5: Director OIC Information -->
            <tr>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-user-shield me-1 text-dark"></i>Director OIC
                  </label>
                  <select name="director_oic_id" class="form-control select2">
                    <option value="">Select Director OIC (Optional)</option>
                    <?php foreach ($lists as $staff): ?>
                      <option value="<?= $staff->staff_id ?>" <?= ($staff->staff_id == @$division->director_oic_id) ? 'selected' : '' ?>>
                        <?= $staff->lname . ' ' . $staff->fname ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <small class="form-text text-muted">Optional: Officer in charge of director</small>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-calendar-alt me-1 text-warning"></i>Director OIC Start Date
                  </label>
                  <input type="text" class="form-control datepicker" name="director_oic_start_date" value="<?= $division->director_oic_start_date ?>" placeholder="Select start date">
                  <small class="form-text text-muted">Optional: When OIC period started</small>
                </div>
              </td>
              <td class="form-cell">
                <div class="form-group">
                  <label class="form-label fw-semibold">
                    <i class="fas fa-calendar-alt me-1 text-warning"></i>Director OIC End Date
                  </label>
                  <input type="text" class="form-control datepicker" name="director_oic_end_date" value="<?= $division->director_oic_end_date ?>" placeholder="Select end date">
                  <small class="form-text text-muted">Optional: When OIC period ends</small>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="row mt-4">
          <div class="col-md-12">
            <div class="d-flex gap-2 justify-content-end">
              <button type="reset" class="btn btn-outline-secondary">
                <i class="fas fa-undo me-1"></i> Reset Form
              </button>
              <button type="submit" class="btn btn-success">
                <i class="fas fa-save me-1"></i> Update Division
              </button>
            </div>
          </div>
        </div>
        <?= form_close(); ?>
      </div>
    </div>
  </div>
</div>
