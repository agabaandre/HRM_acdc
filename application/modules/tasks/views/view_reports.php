<div class="mt-5">
    <h2 class="mb-4">My Reports</h2>
    
    <!-- Filter Form -->
    <form method="get" action="<?php echo current_url(); ?>">
        <div class="row">
            <div class="col-md-3">
                <input type="text" name="activity_name" class="form-control" placeholder="Activity Name" 
                    value="<?php echo isset($_GET['activity_name']) ? $_GET['activity_name'] : ''; ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="employee_name" class="form-control" placeholder="Employee Name" 
                    value="<?php echo isset($_GET['employee_name']) ? $_GET['employee_name'] : ''; ?>">
            </div>
            <!-- Add other relevant filters as needed -->
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </div>
    </form>
    <br>
    
    <!-- Reports Table -->
    <table class="table table-bordered mydata">
        <thead class="thead-dark">
            <tr>
                <th>Activity Name</th>
                <th>Report Date</th>
                <th>Description</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
            <tr>
                <td><?php echo $report->activity_name; ?></td>
                <td><?php echo $report->report_date; ?></td>
                <td>
                    <?php
                    // Remove HTML tags and limit to 100 characters
                    $cleanDescription = strip_tags($report->description);
                    if (strlen($cleanDescription) > 10) {
                        echo substr($cleanDescription, 0, 10) . '...';
                        echo ' <a href="#" data-bs-toggle="modal" data-bs-target="#reportModal-' . $report->report_id . '">Read More and Approve</a>';
                    } else {
                        echo $cleanDescription;
                    }
                    ?>
                </td>
                <td>
                    <span class="badge text-bg-<?php echo $report->status === 'approved' ? 'success' : ($report->status === 'rejected' ? 'danger' : 'warning'); ?>">
                        <?php echo ucfirst($report->status); ?>
                    </span>
                </td>
            </tr>
            
            <!-- Report Modal -->
            <div class="modal fade" id="reportModal-<?php echo $report->report_id; ?>" tabindex="-1" aria-labelledby="reportModalLabel-<?php echo $report->report_id; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content ">
                        <div class="modal-header">
                            <h5 class="modal-title" id="reportModalLabel-<?php echo $report->report_id; ?>">Report Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <h5>Activity: <?php echo $report->activity_name; ?></h5>
                            <p><?php echo $report->description; ?></p>
                            
                            <?php if($report->status === 'pending'): ?>
                            <div class="form-group">
                                <label for="supervisorComment-<?php echo $report->report_id; ?>">Supervisor Comment</label>
                                <textarea class="form-control" id="supervisorComment-<?php echo $report->report_id; ?>" rows="3"></textarea>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <?php if($report->status === 'pending'): ?>
                            <button type="button" class="btn btn-success approve-report" data-report-id="<?php echo $report->report_id; ?>">Approve</button>
                            <button type="button" class="btn btn-danger reject-report" data-report-id="<?php echo $report->report_id; ?>">Reject</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
$(document).ready(function() {
    // Handler for Approve button click
    $('.approve-report').on('click', function() {
        let reportId = $(this).data('report-id');
        // Get supervisor comment if present
        let commentSelector = '#supervisorComment-' + reportId;
        let supervisorComment = $(commentSelector).length ? $(commentSelector).val() : '';
        
        $.ajax({
            url: '<?php echo base_url("tasks/update_status"); ?>',
            type: 'POST',
            data: {
                report_id: reportId,
                status: 'approved',
                supervisor_comment: supervisorComment,
                '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    show_notification(response.message, 'success');
                    // Hide the modal on success
                    $('#reportModal-' + reportId).modal('hide');
                } else {
                    show_notification(response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                show_notification('An error occurred. Please try again.', 'error');
            }
        });
    });

    // Handler for Reject button click
    $('.reject-report').on('click', function() {
        let reportId = $(this).data('report-id');
        // Get supervisor comment if present
        let commentSelector = '#supervisorComment-' + reportId;
        let supervisorComment = $(commentSelector).length ? $(commentSelector).val() : '';

        $.ajax({
            url: '<?php echo base_url("tasks/update_status"); ?>',
            type: 'POST',
            data: {
                report_id: reportId,
                status: 'rejected',
                supervisor_comment: supervisorComment,
                '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    show_notification(response.message, 'success');
                    // Hide the modal on success
                    $('#reportModal-' + reportId).modal('hide');
                } else {
                    show_notification(response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                show_notification('An error occurred. Please try again.', 'error');
            }
        });
    });
});
</script>
