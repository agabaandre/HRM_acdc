<div class="container mt-5">
    <h2 class="mb-4">Add Activity</h2>
    <form id="addActivityForm" method="post" class="needs-validation" novalidate>
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        
        <div class="form-group">
            <label for="deliverable_id">Deliverable:</label>
            <select name="deliverable_id" class="form-control" required>
                <?php foreach ($deliverables as $deliverable): ?>
                    <option value="<?php echo $deliverable->deliverable_id; ?>"><?php echo $deliverable->deliverable_name; ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select a deliverable.</div>
        </div>

        <div class="form-group">
            <label for="activity_name">Activity Name:</label>
            <input type="text" name="activity_name" class="form-control" required>
            <div class="invalid-feedback">Please enter the activity name.</div>
        </div>

        <div class="form-group">
            <label for="start_date">Start Date:</label>
            <input type="date" name="start_date" class="form-control" required>
            <div class="invalid-feedback">Please select a start date.</div>
        </div>

        <div class="form-group">
            <label for="end_date">End Date:</label>
            <input type="date" name="end_date" class="form-control" required>
            <div class="invalid-feedback">Please select an end date.</div>
        </div>

        <input type="hidden" name="staff_id" value="<?php echo $this->session->userdata('staff_id'); ?>">
        <button type="submit" class="btn btn-primary">Add Activity</button>
    </form>
</div>
<script>
    $(document).ready(function() {
        $('#addActivityForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            if (this.checkValidity() === false) {
                e.stopPropagation();
                $(this).addClass('was-validated');
                return;
            }

            $.ajax({
                url: '<?php echo base_url("taskplanner/add_activity"); ?>',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        show_notification(response.message, 'success');
                        setTimeout(function() {
                            window.location.href = '<?php echo base_url("taskplanner/view_activities"); ?>';
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