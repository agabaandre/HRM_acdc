<?php $this->load->view('tasks_tabs')?>
<div class="modal fade" id="addActivitiesModal" tabindex="-1" aria-labelledby="addActivitiesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <form id="addActivityForm" method="post" class="needs-validation" novalidate>

        <div class="modal-header">
          <h5 class="modal-title" id="addActivitiesModalLabel">Add New Sub-Activities</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">

          <!-- Output Selection -->
          <div class="mb-4">
            <label for="output" class="form-label fw-bold">Work Plan Activity:</label>
            <?php @$division_id = $this->session->userdata('user')->division_id; ?>
            <select name="quarterly_output_id" class="form-select select2" required>
              <option value="">Select Output</option>
              <?php foreach ($outputs as $deliverable): ?>

                <?php 
                    
                   // dd($deliverable);
                    if (in_array($deliverable->division_id, [$division_id])): ?>
                  <option value="<?= $deliverable->id ?>"><?= $deliverable->activity_name ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select a work plan activity.</div>
          </div>

          <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">

          <!-- Activities Table -->
          <div class="table-responsive">
            <table class="table table-bordered align-middle">
              <thead class="table-light text-center">
                <tr>
                  <th>Activity Name</th>
                  <th>Start Date</th>
                  <th>End Date</th>
                  <th>Comments</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 0; $i < 3; $i++): ?>
                  <tr>
                    <td>
                      <input type="text" name="activity_name[]" class="form-control" required>
                      <div class="invalid-feedback">Required</div>
                    </td>
                    <td>
                      <input type="text" name="start_date[]" class="form-control datepicker" required autocomplete="off">
                      <div class="invalid-feedback">Required</div>
                    </td>
                    <td>
                      <input type="text" name="end_date[]" class="form-control datepicker" required autocomplete="off">
                      <div class="invalid-feedback">Required</div>
                    </td>
                    <td>
                      <textarea name="comments[]" class="form-control" rows="1"></textarea>
                    </td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>

        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Save Activities</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>

      </form>
    </div>
  </div>
</div>

<button class="btn btn-dark mb-3" data-bs-toggle="modal" data-bs-target="#addActivitiesModal">
  <i class="fa fa-plus-circle me-1"></i> Add Sub-Activities
</button>

<!-- Activities Table -->
<div class="table-responsive mt-3">
    <table class="table table-bordered" id="activitiesTable">
        <thead class="table-dark text-center">
            <tr>
                <th>Sub Activities</th>
                <th>Work Plan Actvity</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Comments</th>
                <th>Reporting Date</th>
                <!-- <th>Status</th> -->
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
                    <select name="priority" class="form-control form-control-md"  id="edit_priority" required>
                                                <option value="Low">Low</option>
                                                <option value="Medium">Medium</option>
                                                <option value="High">High</option>
                                                    
                    </select>

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
                <h5 class="modal-title">End of Activity Report</h5>
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
                        <input type="hidden" id="report_id" class="form-control" disabled autocomplete="false">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="text" id="report_end_date" class="form-control" disabled autocomplete="false">
                    </div>
                    <div class="form-group">
    
                    <div class="form-group">
                        <label>Report</label>
                        <textarea id="report_description" class="form-control summernote" rows="20"></textarea>
                    </div>
                    <div class="form-group"><br>
                    <button type="submit" class="btn btn-success">Submit/Update Report</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="approvalModalLabel">Update Activity Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to update the status of this activity?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="confirmApprove">Approve</button>
        <button type="button" class="btn btn-danger" id="confirmReject">Reject</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
    const table = $('#activitiesTable').DataTable({
        ajax: {
            url: '<?= base_url("tasks/fetch_activities") ?>',
            dataSrc: '',
            data: function (d) {
                return {
                    output: $('#filterOutput').val(),
                    start_date: $('#filterStartDate').val(),
                    end_date: $('#filterEndDate').val()
                };
            }
        },
        pageLength: 25,
        dom: 'Bfrtip',
        buttons: [
            { extend: 'excelHtml5', title: 'Activities Export' },
            { extend: 'csvHtml5', title: 'Activities Export' }
        ],
        columns: [
            {
                data: 'activity_name',
                createdCell: function (td) {
                    $(td).addClass('text-wrap');
                }
            },
            {
                data: 'work_activity_name',
                createdCell: function (td) {
                    $(td).addClass('text-wrap');
                }
            },
            { data: 'start_date' },
            { data: 'end_date' },
            {
                data: 'comments',
                createdCell: function (td) {
                    $(td).addClass('text-wrap');
                }
            },
            { data: 'report_date' },
            {
                data: null,
                render: function (row) {
                    const user_id = "<?= $this->session->userdata('user')->staff_id ?>";
                    if (row.staff_id == user_id) {
                        return `
                            <button class="btn btn-sm btn-primary edit-btn"
                                data-id="${row.activity_id}"
                                data-name="${row.activity_name}"
                                data-start_date="${row.start_date}"
                                data-end_date="${row.end_date}"
                                data-priority="${row.priority}"
                                data-comments="${row.comments}">
                                <i class="fa fa-pencil"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-success report-btn"
                                data-reportid="${row.activity_id}"
                                data-report_id="${row.report_id || ''}"
                                data-reportname="${row.activity_name}"
                                data-reportstart_date="${row.start_date}"
                                data-reportend_date="${row.end_date}"
                                data-reportdescription="${row.report || ''}">
                                <i class="fa fa-book"></i> Report
                            </button>`;
                    }
                    return '<span class="text-muted">No actions available</span>';
                }
            }
        ]
    });

    $('#applyFilters').on('click', function (e) {
        e.preventDefault();
        table.ajax.reload();
    });

    $('#addActivityForm').on('submit', function (e) {
        e.preventDefault();
        if (!this.checkValidity()) {
            $(this).addClass('was-validated');
            return;
        }

        $.post('<?= base_url("tasks/add_activity") ?>', $(this).serialize(), function (res) {
            if (res.status === 'success') {
                show_notification(res.message, 'success');
                $('#addActivityForm')[0].reset();
                $('#addActivityForm').removeClass('was-validated');
                table.ajax.reload();
            } else {
                show_notification(res.message, 'error');
            }
        }, 'json');
    });

    $(document).on('click', '.edit-btn', function () {
        const d = $(this).data();
        $('#activity_id').val(d.id);
        $('#edit_activity_name').val(d.name);
        $('#edit_start_date').val(d.start_date);
        $('#edit_end_date').val(d.end_date);
        $('#edit_priority').val(d.priority);
        $('#edit_comments').val(d.comments);
        $('#activityModal').modal('show');
    });

    $('#editActivityForm').on('submit', function (e) {
        e.preventDefault();
        const csrf = {
            name: "<?= $this->security->get_csrf_token_name() ?>",
            hash: "<?= $this->security->get_csrf_hash() ?>"
        };

        const formData = {
            activity_id: $('#activity_id').val(),
            activity_name: $('#edit_activity_name').val(),
            start_date: $('#edit_start_date').val(),
            end_date: $('#edit_end_date').val(),
            priority: $('#edit_priority').val(),
            comments: $('#edit_comments').val()
        };
        formData[csrf.name] = csrf.hash;

        $.post('<?= base_url("tasks/update_activity") ?>', formData, function (res) {
            $('#activityModal').modal('hide');
            show_notification('Activity updated successfully', 'success');
            table.ajax.reload();
        }, 'json').fail(function () {
            show_notification('Failed to update activity', 'error');
        });
    });

    $(document).on('click', '.report-btn', function () {
        const r = $(this).data();
        $('#report_activity_id').val(r.reportid);
        $('#report_id').val(r.report_id);
        $('#report_activity_name').val(r.reportname);
        $('#report_start_date').val(r.reportstart_date);
        $('#report_end_date').val(r.reportend_date);
        $('#report_description').summernote('code', r.reportdescription || '');
        $('#reportModal').modal('show');
    });

    $('#reportActivityForm').on('submit', function (e) {
        e.preventDefault();
        const csrf = {
            name: "<?= $this->security->get_csrf_token_name() ?>",
            hash: "<?= $this->security->get_csrf_hash() ?>"
        };

        const data = {
            report_id: $('#report_id').val(),
            activity_id: $('#report_activity_id').val(),
            description: $('#report_description').val(),
            week: $('#report_week').val()
        };
        data[csrf.name] = csrf.hash;

        $.post('<?= base_url("tasks/add_report") ?>', data, function (res) {
            $('#reportModal').modal('hide');
            show_notification('Report submitted successfully', 'success');
            table.ajax.reload();
        }, 'json').fail(function () {
            show_notification('Failed to add report', 'error');
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
});
</script>
