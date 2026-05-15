<div class="modal fade" id="divisionEditModal" tabindex="-1" aria-labelledby="divisionEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, rgba(52, 143, 65, 1) 0%, rgba(52, 143, 65, 0.8) 100%); color: white;">
        <h5 class="modal-title" id="divisionEditModalLabel">
          <i class="fas fa-edit me-2"></i><span id="divisionEditModalTitle">Edit Division</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body" id="divisionEditModalBody">
        <div class="text-center py-4 division-modal-loading">
          <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div>
        </div>

        <div class="division-modal-form d-none">
          <?= form_open('settings/update_content', ['id' => 'divisionEditForm']); ?>
          <input type="hidden" name="table" value="divisions">
          <input type="hidden" name="redirect" value="divisions">
          <input type="hidden" name="column_name" value="division_id">
          <input type="hidden" name="caller_value" id="divisionEditCallerValue" value="">

          <table class="table table-borderless form-table">
            <tbody>
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-building me-1 text-primary"></i>Division Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" name="division_name" id="divisionEditName" required>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-tag me-1 text-info"></i>Short Name
                    </label>
                    <input type="text" class="form-control" name="division_short_name" id="divisionEditShortName" maxlength="50" placeholder="e.g., DHIS">
                    <small class="form-text text-muted">Optional: Short code</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-layer-group me-1 text-warning"></i>Category <span class="text-danger">*</span>
                    </label>
                    <select name="category" id="divisionEditCategory" class="form-control" required>
                      <option value="">Select Category</option>
                      <option value="Programs">Programs</option>
                      <option value="Operations">Operations</option>
                      <option value="Other">Other</option>
                    </select>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-tie me-1 text-primary"></i>Division Head <span class="text-danger">*</span>
                    </label>
                    <select name="division_head" class="form-control select2 division-staff-select" data-placeholder="Select Division Head" required></select>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user me-1 text-info"></i>Focal Person <span class="text-danger">*</span>
                    </label>
                    <select name="focal_person" class="form-control select2 division-staff-select" data-placeholder="Select Focal Person" required></select>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calculator me-1 text-success"></i>Finance Officer <span class="text-danger">*</span>
                    </label>
                    <select name="finance_officer" class="form-control select2 division-staff-select" data-placeholder="Select Finance Officer" required></select>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-cog me-1 text-warning"></i>Admin Assistant <span class="text-danger">*</span>
                    </label>
                    <select name="admin_assistant" class="form-control select2 division-staff-select" data-placeholder="Select Admin Assistant" required></select>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-crown me-1 text-danger"></i>Director
                    </label>
                    <select name="director_id" class="form-control select2 division-staff-select" data-placeholder="Select Director (Optional)"></select>
                    <small class="form-text text-muted">Optional: Division director</small>
                  </div>
                </td>
                <td class="form-cell"></td>
              </tr>
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-clock me-1 text-secondary"></i>Head OIC
                    </label>
                    <select name="head_oic_id" class="form-control select2 division-staff-select" data-placeholder="Select Head OIC (Optional)"></select>
                    <small class="form-text text-muted">Optional: Officer in charge of division head</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-info"></i>Head OIC Start Date
                    </label>
                    <input type="text" class="form-control datepicker" name="head_oic_start_date" id="divisionEditHeadOicStart" placeholder="Select start date">
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-info"></i>Head OIC End Date
                    </label>
                    <input type="text" class="form-control datepicker" name="head_oic_end_date" id="divisionEditHeadOicEnd" placeholder="Select end date">
                  </div>
                </td>
              </tr>
              <tr>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-user-shield me-1 text-dark"></i>Director OIC
                    </label>
                    <select name="director_oic_id" class="form-control select2 division-staff-select" data-placeholder="Select Director OIC (Optional)"></select>
                    <small class="form-text text-muted">Optional: Officer in charge of director</small>
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-warning"></i>Director OIC Start Date
                    </label>
                    <input type="text" class="form-control datepicker" name="director_oic_start_date" id="divisionEditDirectorOicStart" placeholder="Select start date">
                  </div>
                </td>
                <td class="form-cell">
                  <div class="form-group">
                    <label class="form-label fw-semibold">
                      <i class="fas fa-calendar-alt me-1 text-warning"></i>Director OIC End Date
                    </label>
                    <input type="text" class="form-control datepicker" name="director_oic_end_date" id="divisionEditDirectorOicEnd" placeholder="Select end date">
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div class="row mt-4">
            <div class="col-md-12">
              <div class="d-flex gap-2 justify-content-end flex-wrap">
                <button type="reset" class="btn btn-outline-secondary">
                  <i class="fas fa-undo me-1"></i> Reset Form
                </button>
                <?php if (!empty($is_settings_admin)): ?>
                <button
                  type="submit"
                  class="btn btn-outline-primary"
                  formaction="<?= base_url('settings/copy_division') ?>"
                  formmethod="post"
                  onclick="return confirm('Create a new division from this form? The original division will not be changed.');"
                >
                  <i class="fas fa-copy me-1"></i> Save as Copy
                </button>
                <?php endif; ?>
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
</div>
