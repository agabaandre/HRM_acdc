<div class=" mt-5">
    
    <form id="addActivityForm" method="post" class="needs-validation" novalidate>
        <!-- Quarterly Output -->
        <div class="form-group col-md-4 col-lg-4 col-sm-12 mb-4">
          <label for="output"><h5>Output:</h5></label>
          <?php @$unit_id = $this->session->userdata('user')->unit_id; ?>
                                <select name="quarterly_output_id" class="form-control form-control-md"  required>
                                    <option value="">Select Output</option>
                                    <?php foreach ($outputs as $deliverable): ?>
                                        <?php if (in_array($deliverable->unit_id, [$unit_id])): ?>
                                            <option value="<?php echo $deliverable->quarterly_output_id; ?>">
                                                <?php echo $deliverable->name; ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a Quarterly Output.</div>
        </div>
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
       

        <!-- Table-like structure for dynamic fields -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                  
                        <th>Activity Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Priority</th>
                        <th>Days</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < 1; $i++): ?>
                        <tr>
                          

                            <!-- Activity Name -->
                            <td>
                                <input type="text" name="activity_name" class="form-control form-control-md" required autocomplete="false">
                                <div class="invalid-feedback">Please enter the activity name.</div>
                            </td>

                            <!-- Start Date -->
                            <td>
                                <input type="text" name="start_date" class="form-control form-control-md datepicker" required autocomplete="off">
                                <div class="invalid-feedback">Please select a start date.</div>
                            </td>

                            <!-- End Date -->
                            <td>
                                <input type="text" name="end_date" class="form-control form-control-md datepicker" required autocomplete="off">
                                <div class="invalid-feedback">Please select an end date.</div>
                            </td>
                            <td>
                        
                                            <select name="week" class="form-control form-control-md"  required>
                                                <option value="Low">Low</option>
                                                <option value="Medium">Medium</option>
                                                <option value="High">High</option>
                                           
                                               
                                                    
                                            </select>
                  
                                <div class="invalid-feedback">Please set the Priority .</div>
                            </td>

                            <td>
                                
                                <div class="days" style="border:1px #FFF solid; border-radius:4px; padding:7px;"></div>
                            </td>

                            <!-- Comments -->
                            <td>
                                <textarea name="comments" class="form-control form-control-md" rows="1"></textarea>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>

        <!-- Submit Button -->
        <div class="form-group mt-3">
            <button type="submit" class="btn btn-dark">Add Activities</button>
        </div>
    </form>
</div>


<!-- Filters -->
<div class="mt-4">
    <h5>Filter Activities</h5>
    <div class="row">
        <div class="col-md-4">
            <label for="filterOutput">Output:</label>
            <select id="filterOutput" class="form-control">
                <option value="">All Outputs</option>
                <?php foreach ($outputs as $deliverable): ?>
                    <?php if (in_array($deliverable->unit_id, [$unit_id])): ?>
                    <option value="<?php echo $deliverable->quarterly_output_id; ?>"><?php echo $deliverable->name; ?></option>
                    <?php endif;?>
                <?php endforeach; ?>
                   
            </select>
        </div>
        <div class="col-md-3">
            <label for="filterStartDate">Start Date:</label>
            <input type="text" id="filterStartDate" class="form-control datepicker" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label for="filterEndDate">End Date:</label>
            <input type="text" id="filterEndDate" class="form-control datepicker" autocomplete="off">
        </div>
        <div class="col-md-2">
            <button id="applyFilters" class="btn btn-primary mt-4">Apply Filters</button>
        </div>
    </div>
</div>

<!-- Activities Table -->
<div class="table-responsive mt-3">
    <table class="table table-bordered" id="activitiesTable">
        <thead>
            <tr>
                <th>Activity Name</th>
                <th>Output</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Days</th>
                <th>Priority</th>
                <th>Comments</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Modal for Approve/Edit/Delete -->
<div class="modal fade" id="activityModal" tabindex="-1" role="dialog"  aria-labelledby="add_item_label" aria-modal="true">
    <div class="modal-dialog modal-md modal-dialog-centered ">

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editActivityForm">
                    <input type="hidden" id="activity_id">
                    <div class="form-group">
                        <label>Activity Name</label>
                        <input type="text" id="edit_activity_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="text" id="edit_start_date" class="form-control datepicker" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="text" id="edit_end_date" class="form-control datepicker" required>
                    </div>
                    <label>Priority (Low, Medium, High)</label>
                    <input type="text" id="edit_priority" class="form-control" required>

                    <div class="form-group">
                        <label>Comments</label>
                        <textarea id="edit_comments" class="form-control"></textarea>
                    </div>
                    <div class="form-group"><br>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report -->
<div class="modal fade" id="reportModal" tabindex="-1" role="dialog"  aria-labelledby="add_report_label" aria-modal="true">
    <div class="modal-dialog modal-lg modal-dialog-centered ">

        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Weekly Activity Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportActivityForm">
                    <input type="hidden" id="report_activity_id">
                    <div class="form-group">
                        <label>Activity Name</label>
                        <input type="text" id="report_activity_name" class="form-control" disabled autocomplete="false">
                    </div>
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="text" id="report_start_date" class="form-control" disabled autocomplete="false">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="text" id="report_end_date" class="form-control" disabled autocomplete="false">
                    </div>
                    <div class="form-group">
                    <label for="output">Report Week:</label>
                    <?php $unit_id = $this->session->userdata('user')->unit_id; ?>
                                            <select name="week" class="form-control form-control-md"  required>
                                                <option value="1">Week 1</option>
                                                <option value="2">Week 2</option>
                                                <option value="3">Week 3</option>
                                                <option value="4">Week 4</option>
                                               
                                                    
                                            </select>
                                            <div class="invalid-feedback">Please select a Quarterly Output.</div>
                    </div>
                    <div class="form-group">
                        <label>Report</label>
                        <textarea id="description" class="form-control summernote" rows="20"></textarea>
                    </div>
                    <div class="form-group"><br>
                    <button type="submit" class="btn btn-success">Submit Report</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize all date pickers
    // $(".datepicker").datepicker({
    //     dateFormat: 'yy-mm-dd',
    //     changeMonth: true,
    //     changeYear: true
    // });

    // Initialize DataTable with AJAX source.
    var table = $('#activitiesTable').DataTable({
        ajax: {
            url: '<?php echo base_url("tasks/fetch_activities"); ?>',
            dataSrc: '',
            data: function (d) {
                // If you have filters, attach them here.
                return $.extend({}, d, {
                    output: $('#filterOutput').val(),
                    start_date: $('#filterStartDate').val(),
                    end_date: $('#filterEndDate').val()
                });
            }
        },
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            { extend: 'excelHtml5', title: 'Activities Export' },
            { extend: 'csvHtml5', title: 'Activities Export' }
        ],
        columns: [
            { data: 'activity_name' },
            { data: 'quarterly_output_name' },
            { data: 'start_date' },
            { data: 'end_date' },
            
            { data: 'activity_days' },
            { data: 'priority' },
            { data: 'comments' },
            { 
                data: 'status', 
                render: function(data, type, row) {
                    if (data == 0) {
                        return '<span class="badge text-bg-warning">Pending Approval</span>';
                    } else if (data == 1) {
                        return '<span class="badge text-bg-success">Approved</span>';
                    } else if (data == 2) {
                        return '<span class="badge text-bg-danger">Rejected</span>';
                    } else {
                        return data;
                    }
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    var user_id = "<?php echo $this->session->userdata('user')->staff_id; ?>";
var unitlead_id = "<?php echo $this->session->userdata('user')->staff_id; ?>";

                    if (row.status != 1 && row.staff_id == user_id) {
                        return '<button class="btn btn-sm btn-primary edit-btn" ' +
                            'data-id="' + row.activity_id + '" ' +
                            'data-name="' + row.activity_name + '" ' +
                            'data-start_date="' + row.start_date + '" ' +
                            'data-end_date="' + row.end_date + '" ' +
                            'data-priority="' + row.priority + '" ' +
                            'data-comments="' + row.comments + '">Edit</button> ' +
                            '<button class="btn btn-sm btn-success report-btn" ' +
                            'data-reportid="' + row.activity_id + '" ' +
                            'data-reportname="' + row.activity_name + '" ' +
                            'data-reportstart_date="' + row.start_date + '" ' +
                            'data-reportend_date="' + row.end_date + '">Add report</button>';
                    } else if (row.status != 1 && row.unit_head == unitlead_id) {
                        return '<a href="<?php echo base_url(); ?>tasks/approve_activities/' + row.activity_id + '">Approve</a>';
                    }else{
                         return "No actions available"
                    }
                }
            }
        ]
    }
    //console.log(data);
 );

    // When filters are applied, simply reload the table.
    $('#applyFilters').on('click', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    // Recalculate days difference (if this part is still needed)
    const updateDays = ($row) => {
        const startVal = $row.find('input[name="start_date"]').val();
        const endVal = $row.find('input[name="end_date"]').val();
        const $daysCell = $row.find('.days');
        
        if (startVal && endVal) {
            const startDate = new Date(startVal);
            const endDate = new Date(endVal);
            
            if (isNaN(startDate) || isNaN(endDate)) {
                $daysCell.text('Invalid date');
                return;
            }
            
            if (endDate < startDate) {
                $daysCell.html('<span class="text-danger">End date must be after start date</span>');
                return;
            }
            
            const diffTime = endDate - startDate;
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            if (diffDays > 5) {
                $daysCell.html('<span class="text-danger">Difference must be 5 days or less</span>');
            } else {
                $daysCell.text(diffDays);
            }
        } else {
            $daysCell.text('');
        }
    };

    $('input[name="start_date"], input[name="end_date"]').on('change', function () {
        updateDays($(this).closest('tr'));
    });

    // Add Activity Form Submission with AJAX
    $('#addActivityForm').on('submit', function (e) {
        e.preventDefault();
        if (this.checkValidity() === false) {
            e.stopPropagation();
            $(this).addClass('was-validated');
            return;
        }
        $.ajax({
            url: '<?php echo base_url("tasks/add_activity"); ?>',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (response) {
                if (response.status === 'success') {
                    show_notification(response.message, 'success');
                    $('#addActivityForm')[0].reset();
                    $('#addActivityForm').removeClass('was-validated');
                    table.ajax.reload(); // Reload DataTable's AJAX source
                } else {
                    show_notification(response.message, 'error');
                }
            },
            error: function () {
                show_notification('An error occurred. Please try again.', 'error');
            }
        });
    });

    // Open Edit Modal when an Edit button is clicked
    $(document).on('click', '.edit-btn', function () {
        let activity = $(this).data();
        $('#activity_id').val(activity.id);
        $('#edit_activity_name').val(activity.name);
        $('#edit_start_date').val(activity.start_date);
        $('#edit_end_date').val(activity.end_date);
        $('#edit_priority').val(activity.priority);
        $('#edit_comments').val(activity.comments);
        $('#activityModal').modal('show');
    });

    // Open Report Modal when a Report button is clicked
    $(document).on('click', '.report-btn', function () {
        let activity = $(this).data();
        $('#report_activity_id').val(activity.reportid);
        $('#report_activity_name').val(activity.reportname);
        $('#report_start_date').val(activity.reportstart_date);
        $('#report_end_date').val(activity.reportend_date);
        $('#reportModal').modal('show');
    });

    // Update Activity Form Submission with AJAX
    $(document).on('submit', '#editActivityForm', function (e) {
        e.preventDefault();
        var csrfName = "<?= $this->security->get_csrf_token_name(); ?>";
        var csrfHash = "<?= $this->security->get_csrf_hash(); ?>";
        var formData = {
            activity_id: $('#activity_id').val(),
            activity_name: $('#edit_activity_name').val(),
            start_date: $('#edit_start_date').val(),
            end_date: $('#edit_end_date').val(),
            priority: $('#edit_priority').val(),
            comments: $('#edit_comments').val()
        };
        formData[csrfName] = csrfHash;
        var $submitButton = $(this).find('button[type="submit"]');
        $submitButton.prop('disabled', true);
        $.ajax({
            url: '<?php echo base_url("tasks/update_activity"); ?>',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (response) {
                $('#activityModal').modal('hide');
                show_notification('Activity updated successfully', 'success');
                table.ajax.reload(null, false);
            },
            error: function () {
                show_notification('Failed to update activity', 'error');
            },
            complete: function () {
                $submitButton.prop('disabled', false);
            }
        });
        return false;
    });

    // Add Activity Report Submission with AJAX
    $(document).on('submit', '#reportActivityForm', function (e) {
        e.preventDefault();
        var csrfName = "<?= $this->security->get_csrf_token_name(); ?>";
        var csrfHash = "<?= $this->security->get_csrf_hash(); ?>";
        $.ajax({
            url: '<?php echo base_url("tasks/add_report"); ?>',
            type: 'POST',
            data: {
                activity_id: $('#report_activity_id').val(),
                description: $('#description').val(),
                [csrfName]: csrfHash
            },
            dataType: 'json',
            success: function (response) {
                $('#reportModal').modal('hide');

                show_notification('Report added successfully', 'success');
                table.ajax.reload();
            },
            error: function () {
                show_notification('Failed to add report', 'error');
            }
        });
        return false;
    });
    
    // Delete Activity when the Delete button is clicked
    $('#deleteActivity').click(function () {
        let id = $('#activity_id').val();
        if (confirm("Are you sure you want to delete this activity?")) {
            $.ajax({
                url: '<?php echo base_url("tasks/delete_activity"); ?>',
                type: 'POST',
                data: { id: id },
                success: function (response) {
                    $('#activityModal').modal('hide');
                    show_notification('Activity deleted successfully', 'success');
                    table.ajax.reload();
                },
                error: function() {
                    show_notification('Failed to delete activity', 'error');
                }
            });
        }
    });
    
    // Notification function using Lobibox
    function show_notification(message, msgtype) {
        Lobibox.notify(msgtype, {
            pauseDelayOnHover: true,
            continueDelayOnInactiveTab: false,
            position: 'top right',
            icon: 'bx bx-check-circle',
            msg: message
        });
    }
});
</script>
