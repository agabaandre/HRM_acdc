<style>
  table.dataTable td {
  white-space: normal !important;
  word-break: break-word;
}
</style>
<div class="container-fluid my-4">
<?php $this->load->view('tasks_tabs')?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="fa fa-plus-circle me-1"></i> Add Weekly Task
    </button>
  </div>
  <input type="hidden" id="csrf_token" 
       name="<?= $this->security->get_csrf_token_name(); ?>" 
       value="<?= $this->security->get_csrf_hash(); ?>">

  <!-- Filters -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
    <?= form_open('', ['id' => 'filterForm']) ?>

        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold">Division</label>
            <select id="filterDivision" class="form-select select2">
              <option value="">All Divisions</option>
              <?php foreach ($divisions as $division): ?>
                <option value="<?= $division->division_id ?>"><?= $division->division_name ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Staff</label>
            <select id="filterStaff" class="form-select select2" multiple>
              <?php foreach ($staff_list as $staff): ?>
                <option value="<?= $staff->staff_id ?>"><?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Sub-Activity</label>
            <select id="filterOutput" class="form-select select2">
              <option value="">All Sub-Activities</option>
              <?php foreach ($outputs as $output): ?>
                <option value="<?= $output->activity_id ?>"><?= $output->activity_name ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">Start Date</label>
            <input type="text" id="filterStartDate" class="form-control datepicker" placeholder="YYYY-MM-DD">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">End Date</label>
            <input type="text" id="filterEndDate" class="form-control datepicker" placeholder="YYYY-MM-DD">
          </div>

          <div class="col-md-3 mt-2">
            <button type="button" class="btn btn-success w-100 mt-1" id="applyFilters">
              <i class="fa fa-filter me-1"></i> Apply Filters
            </button>
          </div>
        </div>
        <?= form_close(); ?>
      <!-- Print Buttons -->
      <div class="row mt-4" id="printButtons" style="display: none;">
        <div class="col-md-3">
          <button class="btn btn-outline-dark w-100" id="printStaffBtn">
            <i class="fa fa-print me-1"></i> Print Staff Report
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-success w-100" id="printDivisionBtn">
            <i class="fa fa-print me-1"></i> Print Division Report
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-success w-100" id="printCombinedBtn">
            <i class="fa fa-print me-1"></i> Combined Effort Report
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Data Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover" id="activitiesTable">
      <thead class="table-dark text-center">
        <tr>
          <th>#</th>
          <th>Activity</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Comments</th>
          <th>Assigned To</th>
          <th>Created By</th>
          <th>Updated By</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<!-- Modals -->
<?php $this->load->view('modals.php'); ?>
<script>
$(function () {
  const csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
  const csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

  const table = $('#activitiesTable').DataTable({
  processing: true,
  serverSide: true,
  searching: false,
  ajax: {
    url: '<?= base_url("weektasks/fetch") ?>',
    type: 'POST',
    data: function (d) {
      d.division = $('#filterDivision').val();
      d.staff_id = $('#filterStaff').val();
      d.output = $('#filterOutput').val();
      d.start_date = $('#filterStartDate').val();
      d.end_date = $('#filterEndDate').val();
      d['<?= $this->security->get_csrf_token_name(); ?>'] = $('#csrf_token').val();
    }
  },
  pageLength: 10,
  columns: [
    {
      data: null,
      title: '#',
      orderable: false,
      searchable: false,
      render: function (data, type, row, meta) {
        return meta.row + 1 + meta.settings._iDisplayStart;
      }
    },
    {
      data: 'activity_name',
      render: function (data, type, row) {
        const wordCount = data.trim().split(/\s+/).length;
        if (wordCount > 5) {
          return `<span class="text-wrap d-block" style="white-space: normal;">${data}</span>`;
        }
        return data;
      }
    },
    { data: 'start_date' },
    { data: 'end_date' },
    { data: 'comments' },
    { data: 'executed_by' },
    { data: 'created_by_name' },
    { data: 'updated_by_name' },
    {
      data: 'status',
      render: function (status) {
        switch (parseInt(status)) {
          case 1: return '<span class="badge bg-warning">Pending</span>';
          case 2: return '<span class="badge bg-success">Done</span>';
          case 3: return '<span class="badge bg-primary">Next Week</span>';
          case 4: return '<span class="badge bg-danger">Cancelled</span>';
          default: return '<span class="badge bg-secondary">Unknown</span>';
        }
      }
    },
    {
      data: null,
      orderable: false,
      render: function (row) {
        return `<button class="btn btn-sm btn-primary edit-btn"
                  data-id="${row.activity_id}"
                  data-name="${row.activity_name}"
                  data-comments="${row.comments}"
                  data-status="${row.status}"
                  data-staff_id="${row.staff_id}">
                  <i class="fa fa-edit"></i> Edit
              </button>`;
      }
    }
  ]
});

  $('#applyFilters').on('click', () => table.ajax.reload());

  function show_notification(message, type) {
    Lobibox.notify(type, {
      pauseDelayOnHover: true,
      continueDelayOnInactiveTab: false,
      position: 'top right',
      icon: 'bx bx-check-circle',
      msg: message
    });
  }

  // Add Activity
  $('#addActivityForm').on('submit', function (e) {
    e.preventDefault();
    if (!this.checkValidity()) {
      $(this).addClass('was-validated');
      return;
    }

    const formData = $(this).serializeArray();
    formData.push({ name: csrfName, value: csrfHash });

    $.post('<?= base_url("weektasks/save") ?>', formData, function (res) {
      if (res.status === 'success') {
        $('#addModal').modal('hide');
        $('#addActivityForm')[0].reset();
        $('#addActivityForm').removeClass('was-validated');
        table.ajax.reload();
        show_notification(res.message, 'success');
      } else {
        show_notification(res.message, 'error');
      }
    }, 'json');
  });

  // Edit Activity
  $(document).on('click', '.edit-btn', function () {
    const data = $(this).data();
    $('#edit_id').val(data.id);
    $('#edit_name').val(data.name);
    $('#edit_comments').val(data.comments);
    $('#edit_status').val(data.status);
    $('#editModal .edit-staff-checkbox').prop('checked', false);

    const staff_ids_raw = $(this).attr('data-staff_id');
    if (staff_ids_raw) {
      const staffIds = staff_ids_raw.toString().split(',');
      staffIds.forEach(id => {
        $('#editModal #staff_' + id.trim()).prop('checked', true);
      });
    }

    const editModalInstance = new bootstrap.Modal(document.getElementById('editModal'));
    editModalInstance.show();
  });

  // Update Activity
  $('#editActivityForm').on('submit', function (e) {
    e.preventDefault();
    const formData = $(this).serializeArray();
    formData.push({ name: csrfName, value: csrfHash });

    $.post('<?= base_url("weektasks/update") ?>', formData, function (res) {
      if (res.status === 'success') {
        $('#editModal').modal('hide');
        $('#editActivityForm')[0].reset();
        $('#editActivityForm').removeClass('was-validated');
        table.ajax.reload();
        show_notification(res.message, 'success');
      } else {
        show_notification(res.message, 'error');
      }
    }, 'json');
  });

  // Enable Print Buttons
  function checkPrintEligibility() {
    const staff = $('#filterStaff').val();
    const start = $('#filterStartDate').val();
    const end = $('#filterEndDate').val();
    if ((staff && start && end) || ($('#filterDivision').val() && start && end)) {
      $('#printButtons').fadeIn();
    } else {
      $('#printButtons').fadeOut();
    }
  }

  $('#filterStaff, #filterStartDate, #filterEndDate, #filterDivision').on('change keyup', checkPrintEligibility);

  $('#printStaffBtn').on('click', function () {
    const staff = $('#filterStaff').val();
    const start = $('#filterStartDate').val();
    const end = $('#filterEndDate').val();
    if (staff && start && end) {
      window.open(`<?= base_url('weektasks/print_staff_report/') ?>${staff[0]}/${start}/${end}`, '_blank');
    }
  });

  $('#printDivisionBtn').on('click', function () {
    const division = $('#filterDivision').val();
    const start = $('#filterStartDate').val();
    const end = $('#filterEndDate').val();
    if (division && start && end) {
      window.open(`<?= base_url('weektasks/print_division_report/') ?>${division}/${start}/${end}`, '_blank');
    }
  });

  $('#printCombinedBtn').on('click', function () {
    const division = $('#filterDivision').val();
    const start = $('#filterStartDate').val();
    const end = $('#filterEndDate').val();
    if (division && start && end) {
      window.open(`<?= base_url('weektasks/print_combined_division_report/') ?>${division}/${start}/${end}`, '_blank');
    }
  });

});
</script>
<script>
$(document).ready(function () {
  const minActivities = 1;

  function updateRemoveButtons() {
    const count = $('#activityContainer .activity-row').length;
    $('.remove-activity').prop('disabled', count <= minActivities);
  }

  // Add new activity row
  $('#activityContainer').on('click', '.add-activity', function () {
    const row = $(this).closest('.activity-row');
    const newRow = row.clone();

    newRow.find('input').val('');
    $('#activityContainer').append(newRow);

    updateRemoveButtons();
  });

  // Remove activity row
  $('#activityContainer').on('click', '.remove-activity', function () {
    const count = $('#activityContainer .activity-row').length;
    if (count > minActivities) {
      $(this).closest('.activity-row').remove();
      updateRemoveButtons();
    }
  });

  updateRemoveButtons();
});
</script>
<script>
$(document).ready(function () {
  $('#team_lead_select').on('change', function () {
    const teamLeadId = $(this).val();
    const subActivitySelect = $('#sub_activity_select');

    subActivitySelect.html('<option value="">Loading...</option>');

    // Get CSRF token data
    const csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
    const csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

    const requestData = {
      team_lead_id: teamLeadId
    };
    requestData[csrfName] = csrfHash;

    $.ajax({
      url: '<?= base_url("weektasks/get_sub_activities_by_teamlead") ?>',
      type: 'POST',
      data: requestData,
      dataType: 'json',
      success: function (data) {
        let options = '<option value="">Select</option>';
        $.each(data, function (i, item) {
          options += `<option value="${item.activity_id}">${item.activity_name}</option>`;
        });
        subActivitySelect.html(options);
      },
      error: function (xhr, status, error) {
        console.error('AJAX Error:', status, error);
        subActivitySelect.html('<option value="">Failed to load</option>');
      }
    });
  });
});
</script>




<!-- Add Weekly Task Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <?= form_open('weektasks/save', [
      'id' => 'addActivityForm',
      'class' => 'modal-content needs-validation',
      'novalidate' => 'novalidate'
    ]) ?>
    <div class="modal-header">
      <h5 class="modal-title">Add Weekly Tasks</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <input type="hidden"
       name="<?= $this->security->get_csrf_token_name(); ?>"
       value="<?= $this->security->get_csrf_hash(); ?>">

    <div class="modal-body">

        <!-- Team Lead -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Unit Team Lead</label>
        <select name="team_lead" id="team_lead_select" class="form-select select2" required>
          <option value="">Select</option>
          <?php foreach ($team_leads as $leader): ?>
            <option value="<?= $leader->staff_id ?>"><?= $leader->fname . ' ' . $leader->lname ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Sub Activity -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Sub-Activity</label>
        <select name="work_planner_tasks_id" id="sub_activity_select" class="form-select select2" required>
          <option value="">Select</option>
          <!-- Will be populated dynamically -->
        </select>
      </div>
      <!-- Assigned Staff -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Assign Staff</label>
        <div class="row">
          <?php foreach ($staff_list as $staff): ?>
            <div class="col-md-6">
              <div class="form-check mb-2">
                <input class="form-check-input edit-staff-checkbox"
                  type="checkbox"
                  name="staff_ids[]"
                  value="<?= $staff->staff_id ?>"
                  id="staff_<?= $staff->staff_id ?>">
                <label class="form-check-label" for="staff_<?= $staff->staff_id ?>">
                  <?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="invalid-feedback">Please assign at least one staff.</div>
      </div>


      <!-- Start & End Dates -->
      <div class="row mb-3">
        <div class="col">
          <label>Start Date</label>
          <input type="text" name="start_date" class="form-control activity-dates" required>
        </div>
        <div class="col">
          <label>End Date</label>
          <input type="text" name="end_date" class="form-control activity-dates" required>
        </div>
      </div>

      <!-- Activities -->
    <div id="activityContainer">
      <div class="activity-row row g-3 mb-2">
        <div class="col">
          <input type="text" name="activity_name[]" class="form-control" placeholder="Activity Name" required>
        </div>
        <div class="col">
          <input type="text" name="comments[]" class="form-control" placeholder="Comments">
        </div>
        <div class="col-auto">
          <button type="button" class="btn btn-success btn-sm add-activity"><i class="fa fa-plus"></i></button>
          <button type="button" class="btn btn-danger btn-sm remove-activity"><i class="fa fa-trash"></i></button>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-success">Save</button>
    </div>
    <?= form_close(); ?>
  </div>
</div>
<!-- Edit Task Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <?= form_open('weektasks/update', [
      'id' => 'editActivityForm',
      'class' => 'modal-content needs-validation',
      'novalidate' => 'novalidate'
    ]) ?>
    <div class="modal-header">
      <h5 class="modal-title">Edit Task</h5>
      <button class="btn-close" data-bs-dismiss="modal" type="button" aria-label="Close"></button>
    </div>

    <input type="hidden"
       name="<?= $this->security->get_csrf_token_name(); ?>"
       value="<?= $this->security->get_csrf_hash(); ?>">

    <div class="modal-body">
      <input type="hidden" name="activity_id" id="edit_id">

      <!-- Activity Name -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Activity Name</label>
        <input type="text" name="activity_name" id="edit_name" class="form-control" required>
        <div class="invalid-feedback">Activity name is required.</div>
      </div>

      <!-- Assigned Staff -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Assign Staff</label>
        <div class="row">
          <?php foreach ($staff_list as $staff): ?>
            <div class="col-md-6">
              <div class="form-check mb-2">
                <input class="form-check-input edit-staff-checkbox"
                  type="checkbox"
                  name="staff_ids[]"
                  value="<?= $staff->staff_id ?>"
                  id="staff_<?= $staff->staff_id ?>">
                <label class="form-check-label" for="staff_<?= $staff->staff_id ?>">
                  <?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?>
                </label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>



      <!-- Status -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Status</label>
        <select name="status" id="edit_status" class="form-select" required>
          <option value="1">Pending</option>
          <option value="2">Completed</option>
          <option value="3">Carried Forward</option>
          <option value="4">Cancelled</option>
        </select>
        <div class="invalid-feedback">Please select a status.</div>
      </div>

      <!-- Comments -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Comments</label>
        <textarea name="comments" id="edit_comments" class="form-control" rows="3"></textarea>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-primary" type="submit">
        <i class="fa fa-check-circle me-1"></i> Update Task
      </button>
    </div>
    <?= form_close(); ?>

  </div>
</div>

