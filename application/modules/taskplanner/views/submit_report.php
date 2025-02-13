<div class="container mt-5">
    <h2 class="mb-4">Submit Report</h2>
    <form id="submitReportForm" method="post" class="needs-validation" novalidate>
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        
        <div class="form-group">
            <label for="description">Description:</label>
            <textarea name="description" class="form-control" required></textarea>
            <div class="invalid-feedback">Please enter a description.</div>
        </div>

        <button type="submit" class="btn btn-primary">Submit Report</button>
    </form>
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
                url: '<?php echo base_url("taskplanner/submit_report/"); ?>' + activity_id, // Correct URL concatenation
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        show_notification(response.message, 'success');
                        setTimeout(function() {
                            window.location.href = '<?php echo base_url("taskplanner/view_reports"); ?>';
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