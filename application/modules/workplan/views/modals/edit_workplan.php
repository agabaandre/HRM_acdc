<!-- Edit Workplan Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="editForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editModalLabel">Edit Workplan Activity</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <!-- Scrollable Content -->
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
          <input type="hidden" name="id" id="edit_id">

          <!-- Division -->
          <div class="mb-3">
            <label class="form-label">Division/Directorate</label>
            <select class="form-select" name="division_id" id="edit_division_id" required>
              <option value="">-- Select Division --</option>
              <?php foreach ($divisions as $div): ?>
                <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Year -->
          <div class="mb-3">
            <label class="form-label">Year</label>
            <input type="text" class="form-control" name="year" id="edit_year" required>
          </div>

          <!-- Intermediate Outcome -->
          <div class="mb-3">
            <label class="form-label">Intermediate Outcome</label>
            <textarea class="form-control" name="intermediate_outcome" id="edit_intermediate_outcome" rows="2" required></textarea>
          </div>

          <!-- Broad Activity -->
          <div class="mb-3">
            <label class="form-label">Broad Activity</label>
            <textarea class="form-control" name="broad_activity" id="edit_broad_activity" rows="2" required></textarea>
          </div>

          <!-- Output Indicator -->
          <div class="mb-3">
            <label class="form-label">Output Indicator</label>
            <textarea class="form-control" name="output_indicator" id="edit_output_indicator" rows="2" required></textarea>
          </div>

          <!-- Cumulative Target -->
          <div class="mb-3">
            <label class="form-label">Cumulative Target</label>
            <input type="text" class="form-control" name="cumulative_target" id="edit_cumulative_target" required>
          </div>

          <!-- Activity Name -->
          <div class="mb-3">
            <label class="form-label">Activity Name</label>
            <textarea class="form-control" name="activity_name" id="edit_activity_name" rows="2" required></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i> Update
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>
    </div>
  </div>
</div>
