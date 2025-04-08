
<div class="container my-4">
<?php $this->load->view('tasks_tabs')?>
  <div class="d-flex justify-content-between align-items-center mb-3">
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="fa fa-plus-circle me-1"></i> Add Weekly Task
    </button>
  </div>

  <!-- Filters -->
  <div class="card shadow-sm mb-4">
    <div class="card-body">
      <form id="filterForm">
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
      </form>

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
      </div>
    </div>
  </div>

  <!-- Data Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover" id="activitiesTable">
      <thead class="table-dark text-center">
        <tr>
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
  const table = $('#activitiesTable').DataTable({
    processing: true,
    serverSide: true,
    searching: false,
    ajax: {
      url: '<?= base_url("weektasks/fetch") ?>',
      type: 'POST',
      data: function (d) {
        d.division = $('#filterDivision').val();
        d.staff_id = $('#filterStaff').val(); // multiple select
        d.output = $('#filterOutput').val();
        d.start_date = $('#filterStartDate').val();
        d.end_date = $('#filterEndDate').val();
      }
    },
    pageLength: 10,
    columns: [
      { data: 'activity_name' },
      { data: 'start_date' },
      { data: 'end_date' },
      { data: 'comments' },
      { data: 'executed_by' }, // from PHP: staffname csv
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

  // Apply filters
  $('#applyFilters').on('click', () => table.ajax.reload());

  // Notification using Lobibox
  function show_notification(message, type) {
    Lobibox.notify(type, {
      pauseDelayOnHover: true,
      continueDelayOnInactiveTab: false,
      position: 'top right',
      icon: 'bx bx-check-circle',
      msg: message
    });
  }

  // Add Activity Submit
  $('#addActivityForm').on('submit', function (e) {
    e.preventDefault();
    if (!this.checkValidity()) {
      $(this).addClass('was-validated');
      return;
    }

    $.post('<?= base_url("weektasks/save") ?>', $(this).serialize(), function (res) {
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

  // Open Edit Modal
  $(document).on('click', '.edit-btn', function () {
  const data = $(this).data();

  $('#edit_id').val(data.id);
  $('#edit_name').val(data.name);
  $('#edit_comments').val(data.comments);
  $('#edit_status').val(data.status);

  // Step 1: Clear checkboxes only inside #editModal
  $('#editModal .edit-staff-checkbox').prop('checked', false);

  // Step 2: Get staff IDs from button and check them inside #editModal
  const staff_ids_raw = $(this).attr('data-staff_id');

  if (staff_ids_raw) {
    const staffIds = staff_ids_raw.toString().split(',');
    staffIds.forEach(id => {
      $('#editModal #staff_' + id.trim()).prop('checked', true);
    });
  }

  $('#editModal').modal('show');
});



  // Update Activity Submit
  $('#editActivityForm').on('submit', function (e) {
  e.preventDefault();

  const formData = $(this).serialize();

  console.log("Form Data Being Sent to Server:");
 // console.log(formData); 

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


  // Enable Print Buttons when filters are valid
  function checkPrintEligibility() {
    const staff = $('#filterStaff').val();
    const start = $('#filterStartDate').val();
    if (staff && start) {
      $('#printButtons').fadeIn();
    } else {
      $('#printButtons').fadeOut();
    }
  }

  $('#filterStaff, #filterStartDate').on('change keyup', checkPrintEligibility);

  $('#printStaffBtn').on('click', function () {
    const staff = $('#filterStaff').val();
    const week = $('#filterStartDate').val();
    if (staff && week) {
      window.open(`<?= base_url('weektasks/print_staff_report/') ?>${staff[0]}/${week}`, '_blank');
    }
  });

  $('#printDivisionBtn').on('click', function () {
    const division = $('#filterDivision').val();
    const week = $('#filterStartDate').val();
    if (division && week) {
      window.open(`<?= base_url('weektasks/print_division_report/') ?>${division}/${week}`, '_blank');
    }
  });
});
</script>
