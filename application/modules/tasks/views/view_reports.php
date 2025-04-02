<?php $this->load->view('tasks_tabs')?>
<div class="mt-5"> 
    <h2 class="mb-4">My Reports</h2>
    
    <!-- Filter Form -->
    <form method="get" action="<?php echo current_url(); ?>">
        <div class="row">
            <!-- Activity Name -->
            <div class="col-md-3">
                <input type="text" name="activity_name" class="form-control" placeholder="Activity Name" 
                    value="<?php echo isset($_GET['activity_name']) ? $_GET['activity_name'] : ''; ?>">
            </div>
            <!-- Report Status -->
            <div class="col-md-3">
                <select name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <!-- Employee Name -->
            <div class="col-md-3">
                <input type="text" name="employee_name" class="form-control" placeholder="Employee Name" 
                    value="<?php echo isset($_GET['employee_name']) ? $_GET['employee_name'] : ''; ?>">
            </div>
            <!-- Period -->
            <div class="col-md-3">
                <select name="period" class="form-control">
                    <option value="">All Periods</option>
                    <option value="Q1" <?php echo (isset($_GET['period']) && $_GET['period'] == 'Q1') ? 'selected' : ''; ?>>Q1</option>
                    <option value="Q2" <?php echo (isset($_GET['period']) && $_GET['period'] == 'Q2') ? 'selected' : ''; ?>>Q2</option>
                    <option value="Q3" <?php echo (isset($_GET['period']) && $_GET['period'] == 'Q3') ? 'selected' : ''; ?>>Q3</option>
                    <option value="Q4" <?php echo (isset($_GET['period']) && $_GET['period'] == 'Q4') ? 'selected' : ''; ?>>Q4</option>
                </select>
            </div>
        </div>
        <br>
        <div class="row">
            <!-- Output -->
            <div class="col-md-3">
                <select name="output_id" class="form-control">
                    <option value="">All Outputs</option>
                    <!-- Ideally, load these options from your database -->
                    <option value="1" <?php echo (isset($_GET['output_id']) && $_GET['output_id'] == '1') ? 'selected' : ''; ?>>Output 1</option>
                    <option value="2" <?php echo (isset($_GET['output_id']) && $_GET['output_id'] == '2') ? 'selected' : ''; ?>>Output 2</option>
                    <!-- Add more options as required -->
                </select>
            </div>
            <!-- Quarter -->
            <div class="col-md-3">
                <select name="period" class="form-control">
                    <option value="">All Quarters</option>
                    <option value="1" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '1') ? 'selected' : ''; ?>>Quarter 1</option>
                    <option value="2" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '2') ? 'selected' : ''; ?>>Quarter 2</option>
                    <option value="3" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '3') ? 'selected' : ''; ?>>Quarter 3</option>
                    <option value="4" <?php echo (isset($_GET['quarter']) && $_GET['quarter'] == '4') ? 'selected' : ''; ?>>Quarter 4</option>
                </select>
            </div>
            <!-- Start Date -->
            <div class="col-md-3">
                <input type="text" name="start_date" class="form-control datepicker" placeholder="Start Date" 
                    value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
            </div>
            <!-- End Date -->
            <div class="col-md-3">
                <input type="text" name="end_date" class="form-control datepicker" placeholder="End Date" 
                    value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
            </div>
        </div>
        <br>
        <div class="row">
            <div class="col-md-12 text-end">
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
                <th>Period</th>
                <th>Quarter</th>
                <th>Week</th>
                
                <th>Supervisor Remarks</th>
                <th>Staff</th>
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
                    $cleanDescription = strip_tags($report->report_description ?? '');
                    if (strlen($cleanDescription) > 10) {
                        echo substr($cleanDescription, 0, 20) . '...';
                        echo ' <a href="#" data-bs-toggle="modal" data-bs-target="#reportModal-' . $report->report_id . '">Read More and Approve</a>';
                    } else {
                        echo $cleanDescription . ' <a href="#" data-bs-toggle="modal" data-bs-target="#reportModal-' . $report->report_id . '">Read More and Approve</a>';
                    }
                    ?>
                </td>
                <td><?php echo $report->period; ?></td>
                <td><?php echo isset($report->quarter) ? $report->quarter : 'N/A'; ?></td>
                <td><?php echo $report->week; ?></td>
                <td><?php echo $report->supervisor_comment; ?></td>
                <td><?php echo staff_name($report->staff_id); ?></td>
                <td>
                    <?php 
                    $status = $report->report_status ?? ''; 
                    if ($status === ''): 
                    ?>
                        <span class="badge text-bg-secondary">No Report</span>
                    <?php else: ?>
                        <span class="badge text-bg-<?php echo $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning'); ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            
            <!-- Report Modal -->
            <div class="modal fade" id="reportModal-<?php echo $report->report_id; ?>" tabindex="-1" aria-labelledby="reportModalLabel-<?php echo $report->report_id; ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="reportModalLabel-<?php echo $report->report_id; ?>">Report Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="">
                                <?php
                                $staff_id = $this->session->userdata('user')->staff_id;
                                if(($report->report_status === 'pending') && ($report->unit_head == $staff_id)): ?>
                                <button type="button" class="btn btn-success approve-report" data-report-id="<?php echo $report->report_id; ?>">Approve</button>
                                <button type="button" class="btn btn-danger reject-report" data-report-id="<?php echo $report->report_id; ?>">Reject</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                            <h5>Activity: <?php echo $report->activity_name; ?></h5>
                            <p><?php echo $report->report_description; ?></p>
                            
                            <?php if($report->report_status === 'pending'): ?>
                            <div class="form-group">
                                <label for="supervisorComment-<?php echo $report->report_id; ?>">Supervisor Comment</label>
                                <textarea class="form-control" id="supervisorComment-<?php echo $report->report_id; ?>" rows="3"></textarea>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <?php
                            if(($report->report_status === 'pending') && ($report->unit_head == $staff_id)): ?>
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
