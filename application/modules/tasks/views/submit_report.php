<!-- Submit Report Modal -->
<div class="modal fade" id="submitReportModal" tabindex="-1" aria-labelledby="submitReportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="submitReportModalLabel">
          <i class="fa fa-file-text me-2"></i>Submit Report
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="submitReportForm" method="post" class="needs-validation" novalidate>
        <div class="modal-body">
          <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
          <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">
          
          <div class="form-group mb-3">
            <label for="description" class="form-label">Report Description:</label>
            <textarea name="description" id="description" class="form-control" rows="5" required placeholder="Describe the completion of this activity..."></textarea>
            <div class="invalid-feedback">Please enter a description.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i>Submit Report
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    $(document).ready(function() {
        $('#submitReportForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            // Check form validity
            if (this.checkValidity() === false) {
                e.stopPropagation();
                $(this).addClass('was-validated');
                return;
            }

            // Get the activity_id from the URL or a hidden input field
            var activity_id = <?php echo $activity_id; ?>; // Ensure $activity_id is defined in the view

            // Submit the form via AJAX
            $.ajax({
                url: '<?php echo base_url("tasks/submit_report/"); ?>' + activity_id, // Correct URL concatenation
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        show_notification(response.message, 'success');
                        setTimeout(function() {
                            window.location.href = '<?php echo base_url("tasks/view_reports"); ?>';
                        }, 1500); // Redirect after 1.5 seconds
                    } else {
                        show_notification(response.message, 'error');
                    }
                },
                error: function() {
                    show_notification('An error occurred. Please try again.', 'error');
                }
            });
        });
    });

    function show_notification(message, msgtype) {
        Lobibox.notify(msgtype, {
            pauseDelayOnHover: true,
            continueDelayOnInactiveTab: false,
            position: 'top right',
            icon: 'bx bx-check-circle',
            msg: message
        });
    }
</script>