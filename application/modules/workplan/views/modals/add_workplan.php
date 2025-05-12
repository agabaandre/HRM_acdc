<!-- Create Workplan Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <?= form_open('', ['id' => 'createForm']) ?>
        <div class="modal-header">
          <h5 class="modal-title" id="createModalLabel">Add New Workplan Activity</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
          <div class="row">
            <!-- Column 1 -->
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Division/Directorate</label>
                <select class="form-select select2" name="division_id" required>
                  <option value="">-- Select Division --</option>
                  <?php foreach ($divisions as $div): ?>
                    <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Intermediate Outcome</label>
                <textarea class="form-control" name="intermediate_outcome" rows="2" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Output Indicator</label>
                <textarea class="form-control" name="output_indicator" rows="2" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Activity Name</label>
                <textarea class="form-control" name="activity_name" rows="2" required></textarea>
              </div>
            </div>

            <!-- Column 2 -->
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Year</label>
                <input type="text" class="form-control" name="year" value="<?= date('Y') ?>" readonly>
              </div>

              <div class="mb-3">
                <label class="form-label">Broad Activity</label>
                <textarea class="form-control" name="broad_activity" rows="2" required></textarea>
              </div>

              <div class="mb-3">
                <label class="form-label">Cumulative Target</label>
                <input type="text" class="form-control" name="cumulative_target" required>
              </div>
            </div>
          </div>

          <!-- Has Budget (still inside .modal-body) -->
          <div class="mb-3 form-check mt-3">
            <input type="checkbox" class="form-check-input" id="has_budget" name="has_budget" value="1">
            <label class="form-check-label" for="has_budget">This activity has a budget</label>
          </div>
        </div> <!-- End .modal-body -->

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">
            <i class="fa fa-save me-1"></i> Save
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      <?= form_close() ?>
    </div> <!-- End .modal-content -->
  </div>
</div>
<script>
  $('#createForm').on('submit', function (e) {
    e.preventDefault();

    // CSRF token
    const csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
    const csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

    const form = $(this);
    const formData = form.serializeArray();
    formData.push({ name: csrfName, value: csrfHash });

    $.ajax({
      url: '<?= base_url("workplan/create_task") ?>',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function (res) {
        if (res.status === 'success') {
          // Close modal and reset form
          $('#createModal').modal('hide');
          form[0].reset();
          show_notification('Workplan created successfully.', 'success');

          // ✅ Force refresh of task table
          const query = $('#searchBox').val() || '';
          const year = $('#yearSelect').val() || '';
          fetchTasks(query, year);
        } else {
          show_notification(res.message || 'Failed to create workplan.', 'error');
        }
      },
      error: function () {
        show_notification('Server error occurred.', 'error');
      }
    });
  });
</script>
