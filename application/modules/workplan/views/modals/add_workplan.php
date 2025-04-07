<!-- Create Workplan Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="createForm">
        <div class="modal-header">
          <h5 class="modal-title" id="createModalLabel">Add New Workplan Activity</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Scrollable content area -->
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
          <!-- Division -->
          <div class="mb-3">
            <label class="form-label">Division/Directorate</label>
            <select class="form-select" name="division_id" required>
              <option value="">-- Select Division --</option>
              <?php foreach ($divisions as $div): ?>
                <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Year -->
          <div class="mb-3">
            <label class="form-label">Year</label>
            <input type="text" class="form-control" name="year" value="<?= date('Y') ?>" readonly>
          </div>

          <!-- Intermediate Outcome -->
          <div class="mb-3">
            <label class="form-label">Intermediate Outcome</label>
            <textarea class="form-control" name="intermediate_outcome" rows="2" required></textarea>
          </div>

          <!-- Broad Activity -->
          <div class="mb-3">
            <label class="form-label">Broad Activity</label>
            <textarea class="form-control" name="broad_activity" rows="2" required></textarea>
          </div>

          <!-- Output Indicator -->
          <div class="mb-3">
            <label class="form-label">Output Indicator</label>
            <textarea class="form-control" name="output_indicator" rows="2" required></textarea>
          </div>

          <!-- Cumulative Target -->
          <div class="mb-3">
            <label class="form-label">Cumulative Target</label>
            <input type="text" class="form-control" name="cumulative_target" required>
          </div>

          <!-- Activity Name -->
          <div class="mb-3">
            <label class="form-label">Activity Name</label>
            <textarea class="form-control" name="activity_name" rows="2" required></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">
            <i class="fa fa-save me-1"></i> Save
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>
    </div>
  </div>
</div>
