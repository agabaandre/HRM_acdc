<style>
  .activity-col {
    width: 300px !important;
    text-overflow: break-word;
  }

  .comments-col {
    width: 200px !important;
    word-break: break-word;
  }

  .text-wrap {
    white-space: normal;
    word-break: break-word;
  }

  /* Enhanced styling for better UX */
  .page-header {
    background: rgba(52, 143, 65, 1);
    color: white;
    padding: 1.2rem 0;
    margin-bottom: 1rem;
    border-radius: 0 0 15px 15px;
  }

  .filter-card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-radius: 1px;
    margin-bottom: 2rem;
  }

  .filter-card .card-header {
    background: rgba(52, 143, 65, 1);
    color: white;
    border-radius: 15px 15px 0 0;
    border: none;
    padding: 1rem 1.5rem;
  }

  .table-card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-radius: 1px;
    overflow: hidden;
  }

  .table-card .card-header {
    background: rgb(73, 74, 73);
    color: white;
    border: none;
    padding: 1rem 1.5rem;
  }

  .table-card .card-body {
    padding: 1.5rem;
  }

  .btn-modern {
    border-radius: 25px;
    padding: 0.5rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
  }

  .btn-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  }

  /* Modal Calendar Styling */
  #calendarModal .modal-dialog {
    max-width: 95%;
  }

  #calendarModal .modal-body {
    padding: 1.5rem;
  }

  #calendarModal .btn-outline-success {
    border-color: rgba(52, 143, 65, 1);
    color: rgba(52, 143, 65, 1);
  }

  #calendarModal .btn-outline-success:hover {
    background-color: rgba(52, 143, 65, 1);
    border-color: rgba(52, 143, 65, 1);
    color: white;
  }


  .status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
  }

  .loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
  }

  .loading-spinner {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    text-align: center;
  }

  @media (max-width: 768px) {
    .calendar-grid {
      grid-template-columns: repeat(7, 1fr);
      gap: 0.25rem;
    }

    .calendar-day {
      min-height: 40px;
      padding: 0.25rem;
      font-size: 0.8rem;
    }

    .page-header {
      padding: 1rem 0;
    }

    .page-header h1 {
      font-size: 1.5rem;
    }
  }
</style>

<!-- Page Header -->
<div class="page-header">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-md-6">
        <h4 class="mb-0 text-white"><i class="fa fa-calendar-week me-2 text-white"></i>Weekly Tasks Management</h1>
          <p class="mb-0 opacity-75">Manage and track weekly activities for your team</p>
      </div>
       <!-- Activity Calendar Button -->
    <div class="d-flex col-md-6 justify-content-end">
    <div class="row mb-6 text-end">
      <div class="col-12 text-center">
        <button class="btn btn-warning btn-modern" data-bs-toggle="modal" data-bs-target="#calendarModal" id="openCalendarBtn">
          <i class="fa fa-calendar-alt me-2"></i>View Activity Calendar
        </button>
      </div>
    </div>
      <div class="col-md-6 text-end">
        <button class="btn btn-light btn-modern" data-bs-toggle="modal" data-bs-target="#addModal">
          <i class="fa fa-plus-circle me-1"></i> Add Weekly Task
        </button>
      </div>
    </div>
  </div>
  </div>
</div>

<div class="container-fluid">
  <?php $this->load->view('tasks_tabs') ?>

  <input type="hidden" id="csrf_token"
    name="<?= $this->security->get_csrf_token_name(); ?>"
    value="<?= $this->security->get_csrf_hash(); ?>">

 

  <!-- Enhanced Filters -->
  <div class="card filter-card">

    <div class="card-body">
      <?= form_open('', ['id' => 'filterForm']) ?>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-building me-1"></i>Division
          </label>
          <select id="filterDivision" class="form-select select2">
            <option value="">All Divisions</option>
            <?php foreach ($divisions as $division): ?>
              <option value="<?= $division->division_id ?>"><?= $division->division_name ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-users me-1"></i>Staff Members
          </label>
          <select id="filterStaff" class="form-select select2" multiple>
            <?php foreach ($staff_list as $staff): ?>
              <option value="<?= $staff->staff_id ?>"><?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-user-tie me-1"></i>Team Lead
          </label>
          <select id="filterLead" class="form-select select2">
            <option value="all">All Team Leads</option>
            <?php foreach ($team_leads as $lead): ?>
              <option value="<?= $lead->staff_id ?>"><?= $lead->fname . ' ' . $lead->lname ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-calendar me-1"></i>Status
          </label>
          <select id="filterStatus" class="form-select">
            <option value="">All Statuses</option>
            <option value="1">Pending</option>
            <option value="2">Done</option>
            <option value="3">Next Week</option>
            <option value="4">Cancelled</option>
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

        <div class="col-md-6">
          <div class="d-flex gap-2 mt-4">
            <button type="button" class="btn btn-success btn-modern" id="applyFilters" style="background-color: rgba(52, 143, 65, 1); border-color: rgba(52, 143, 65, 1);">
              <i class="fa fa-filter me-1"></i> Apply Filters
            </button>
            <button type="button" class="btn btn-outline-secondary btn-modern" id="clearFilters">
              <i class="fa fa-times me-1"></i> Clear All
            </button>
            <button type="button" class="btn btn-outline-info btn-modern" id="exportData">
              <i class="fa fa-download me-1"></i> Export
            </button>
          </div>
        </div>
      </div>
      <?= form_close(); ?>
      <!-- Print Buttons -->
      <div class="row mt-4" id="printButtons">
        <div class="col-md-3">
          <button class="btn btn-outline-dark w-100" id="printStaffBtn" style="display: none;">
            <i class="fa fa-print me-1"></i> Print Staff Report
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-success w-100" id="printDivisionBtn" style="display: none;">
            <i class="fa fa-print me-1"></i> Print Division Report
          </button>
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-success w-100" id="printCombinedBtn" style="display: none;">
            <i class="fa fa-print me-1"></i> Combined Effort Report
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Enhanced Data Table -->
  <div class="card table-card">
    <div class="card-header">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h6 class="mb-0 text-white"><i class="fa fa-table me-2"></i>Weekly Tasks Overview</h6>
        </div>
        <div class="col-md-4 text-end">
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-light btn-sm" id="viewModeList">
              <i class="fa fa-list"></i>
            </button>
            <button type="button" class="btn btn-outline-light btn-sm" id="viewModeGrid">
              <i class="fa fa-th"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="activitiesTable">
          <thead class="table-dark">
            <tr>
              <th class="text-center">#</th>
              <th class="activity-col">
                <i class="fa fa-tasks me-1"></i>Activity
              </th>
              <th class="text-center">
                <i class="fa fa-calendar-start me-1"></i>Start Date
              </th>
              <th class="text-center">
                <i class="fa fa-calendar-end me-1"></i>End Date
              </th>
              <th class="comments-col">
                <i class="fa fa-comment me-1"></i>Comments
              </th>
              <th class="text-center">
                <i class="fa fa-users me-1"></i>Assigned To
              </th>
              <th class="text-center">
                <i class="fa fa-user-plus me-1"></i>Created By
              </th>
              <th class="text-center">
                <i class="fa fa-user-edit me-1"></i>Updated By
              </th>
              <th class="text-center">
                <i class="fa fa-flag me-1"></i>Status
              </th>
              <th class="text-center">
                <i class="fa fa-cogs me-1"></i>Actions
              </th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="loading-spinner">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2 mb-0">Loading tasks...</p>
  </div>
</div>

<!-- FullCalendar CDN -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>

<!-- Activity Calendar Modal -->
<div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background: rgba(52, 143, 65, 1); color: white;">
        <h5 class="modal-title" id="calendarModalLabel">
          <i class="fa fa-calendar-alt me-2"></i>Activity Calendar
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row align-items-center mb-3">
          <div class="col-md-8">
            <p class="mb-0 text-muted">Interactive calendar view of your weekly activities and tasks</p>
          </div>
          <div class="col-md-4 text-end">
            <button class="btn btn-outline-success btn-sm btn-modern" id="refreshCalendar">
              <i class="fa fa-sync-alt me-1"></i> Refresh
            </button>
            <button class="btn btn-outline-success btn-sm btn-modern ms-2" id="todayCalendar">
              <i class="fa fa-home me-1"></i> Today
            </button>
          </div>
        </div>
        <div id="fullCalendar"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Modals -->
<?php $this->load->view('modals.php'); ?>
<script>
  $(function() {
    const csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
    const csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

    const table = $('#activitiesTable').DataTable({
      processing: true,
      serverSide: true,
      searching: false,
      ordering: true,
      ajax: {
        url: '<?= base_url("weektasks/fetch") ?>',
        type: 'POST',
        data: function(d) {
          d.division = $('#filterDivision').val();
          d.staff_id = $('#filterStaff').val();
          d.teamlead = $('#filterLead').val();
          d.start_date = $('#filterStartDate').val();
          d.end_date = $('#filterEndDate').val();
          d.status = $('#filterStatus').val();
          d['<?= $this->security->get_csrf_token_name(); ?>'] = $('#csrf_token').val();
        }
      },
      pageLength: 10,
      columns: [{
          data: null,
          title: '#',
          orderable: false,
          searchable: false,
          render: function(data, type, row, meta) {
            return meta.row + 1 + meta.settings._iDisplayStart;
          }
        },
        {
          data: 'activity_name',
          render: function(data, type, row) {
            if (!data) return ''; // handle empty/null
            const wordCount = data.trim().split(/\s+/).length;
            const safeText = $('<div>').text(data).html(); // escape HTML

            return wordCount > 6 ?
              `<div class="text-wrap" style="white-space: normal;">${safeText}</div>` :
              safeText;
          },
          createdCell: function(td) {
            $(td).css('white-space', 'normal');
          }
        },
        {
          data: 'start_date'
        },
        {
          data: 'end_date'
        },
        {
          data: 'comments',
          render: function(data, type, row) {
            if (!data) return ''; // handle empty or null comments

            const wordCount = data.trim().split(/\s+/).length;
            const safeText = $('<div>').text(data).html(); // escape HTML

            return wordCount > 6 ?
              `<div class="text-wrap" style="white-space: normal;">${safeText}</div>` :
              safeText;
          },
          createdCell: function(td) {
            $(td).css('white-space', 'normal');
          }
        },
        {
          data: 'executed_by'
        },
        {
          data: 'created_by_name'
        },
        {
          data: 'updated_by_name'
        },
        {
          data: 'status',
          render: function(status) {
            switch (parseInt(status)) {
              case 1:
                return '<span class="status-badge bg-warning text-dark">Pending</span>';
              case 2:
                return '<span class="status-badge bg-success text-white">Done</span>';
              case 3:
                return '<span class="status-badge bg-primary text-white">Next Week</span>';
              case 4:
                return '<span class="status-badge bg-danger text-white">Cancelled</span>';
              default:
                return '<span class="status-badge bg-secondary text-white">Unknown</span>';
            }
          }
        },
        {
          data: null,
          orderable: false,
          render: function(row) {
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

    // Enhanced filter functionality
    $('#applyFilters').on('click', () => {
      showLoading();
      table.ajax.reload(() => {
        // Add a small delay before hiding to ensure smooth transition
        setTimeout(() => {
          hideLoading();
          // Refresh calendar if modal is open
          if (calendar && $('#calendarModal').hasClass('show')) {
            refreshCalendar();
          }
        }, 100);
      });
    });

    // Clear filters functionality
    $('#clearFilters').on('click', function() {
      $('#filterForm')[0].reset();
      $('#filterStaff').val(null).trigger('change');
      $('#filterDivision').val('').trigger('change');
      $('#filterLead').val('all').trigger('change');
      $('#filterStatus').val('').trigger('change');
      showLoading();
      table.ajax.reload(() => {
        // Add a small delay before hiding to ensure smooth transition
        setTimeout(() => {
          hideLoading();
          // Refresh calendar if modal is open
          if (calendar && $('#calendarModal').hasClass('show')) {
            refreshCalendar();
          }
        }, 100);
      });
    });

    // Auto-apply filters on change (with debounce)
    let filterTimeout;
    $('#filterDivision, #filterStaff, #filterLead, #filterStatus, #filterStartDate, #filterEndDate').on('change', function() {
      clearTimeout(filterTimeout);
      filterTimeout = setTimeout(() => {
        showLoading();
        table.ajax.reload(() => {
          // Add a small delay before hiding to ensure smooth transition
          setTimeout(() => {
            hideLoading();
            // Refresh calendar if modal is open
            if (calendar && $('#calendarModal').hasClass('show')) {
              refreshCalendar();
            }
          }, 100);
        });
      }, 500);
    });

    // Export functionality
    $('#exportData').on('click', function() {
      const filters = {
        division: $('#filterDivision').val(),
        staff_id: $('#filterStaff').val(),
        teamlead: $('#filterLead').val(),
        start_date: $('#filterStartDate').val(),
        end_date: $('#filterEndDate').val(),
        status: $('#filterStatus').val()
      };

      // Create export URL with filters
      const exportUrl = '<?= base_url("weektasks/export") ?>?' + $.param(filters);
      window.open(exportUrl, '_blank');
    });

    // Calendar refresh
    $('#refreshCalendar').on('click', function() {
      if (calendar) {
        refreshCalendar();
      }
    });

    // Loading functions with better timing
    let loadingTimeout;

    function showLoading() {
      // Clear any existing timeout
      clearTimeout(loadingTimeout);

      // Show loader after a short delay to prevent flicker for fast operations
      loadingTimeout = setTimeout(() => {
        $('#loadingOverlay').css('display', 'flex');
      }, 150);
    }

    function hideLoading() {
      // Clear the show timeout if it hasn't executed yet
      clearTimeout(loadingTimeout);

      // Hide loader immediately
      $('#loadingOverlay').hide();
    }

    // FullCalendar Implementation
    let calendar;

    function initializeCalendar() {
      const calendarEl = document.getElementById('fullCalendar');

      calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,listWeek'
        },
        height: 'auto',
        aspectRatio: 1.8,
        events: function(info, successCallback, failureCallback) {
          // Fetch events based on current filters
          fetchCalendarEvents(info.start, info.end, successCallback, failureCallback);
        },
        eventClick: function(info) {
          // Handle event click - could open modal or navigate
          console.log('Event clicked:', info.event);
        },
        dateClick: function(info) {
          // Handle date click - could add new task
          console.log('Date clicked:', info.dateStr);
        },
        eventDidMount: function(info) {
          // Add custom styling to events
          info.el.style.borderRadius = '6px';
          info.el.style.border = 'none';
          info.el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
        },
        dayMaxEvents: 3,
        moreLinkClick: 'popover',
        eventDisplay: 'block',
        displayEventTime: true,
        eventTimeFormat: {
          hour: 'numeric',
          minute: '2-digit',
          hour12: true
        }
      });

      calendar.render();
    }

    function fetchCalendarEvents(start, end, successCallback, failureCallback) {
      // Get current filter values
      const filters = {
        start_date: $('#filterStartDate').val(),
        end_date: $('#filterEndDate').val(),
        status: $('#filterStatus').val(),
        division: $('#filterDivision').val(),
        staff_id: $('#filterStaff').val(),
        teamlead: $('#filterLead').val(),
        [csrfName]: csrfHash
      };

      $.ajax({
        url: '<?= base_url("weektasks/fetch_calendar_events") ?>',
        type: 'POST',
        data: filters,
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            successCallback(response.events);
          } else {
            failureCallback(response.message);
          }
        },
        error: function() {
          failureCallback('Failed to load calendar events');
        }
      });
    }

    function refreshCalendar() {
      if (calendar) {
        calendar.refetchEvents();
      }
    }

    function goToToday() {
      if (calendar) {
        calendar.today();
      }
    }

    // Calendar Modal Events
    $('#calendarModal').on('shown.bs.modal', function() {
      // Initialize calendar when modal is shown
      if (!calendar) {
        setTimeout(initializeCalendar, 100);
      } else {
        // Refresh calendar if already initialized
        refreshCalendar();
      }
    });

    // Calendar control buttons
    $('#refreshCalendar').on('click', function() {
      refreshCalendar();
    });

    $('#todayCalendar').on('click', function() {
      goToToday();
    });

    function formatDate(date) {
      return date.toISOString().split('T')[0];
    }

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
    $('#addActivityForm').on('submit', function(e) {
      e.preventDefault();
      if (!this.checkValidity()) {
        $(this).addClass('was-validated');
        return;
      }


      const formData = $(this).serializeArray();
      formData.push({
        name: csrfName,
        value: csrfHash
      });

      $.post('<?= base_url("weektasks/save") ?>', formData, function(res) {
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
    $(document).on('click', '.edit-btn', function() {
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
    $('#editActivityForm').on('submit', function(e) {
      e.preventDefault();
      const formData = $(this).serializeArray();
      formData.push({
        name: csrfName,
        value: csrfHash
      });

      $.post('<?= base_url("weektasks/update") ?>', formData, function(res) {
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

    // Enhanced Print Buttons with Filter Parameters
    function checkPrintEligibility() {
      const staff = $('#filterStaff').val();
      const division = $('#filterDivision').val();
      const start = $('#filterStartDate').val();
      const end = $('#filterEndDate').val();
      const status = $('#filterStatus').val();
      const teamlead = $('#filterLead').val();

      // Show all print buttons - they will work with current filters
      $('#printStaffBtn').fadeIn();
      $('#printDivisionBtn').fadeIn();
      $('#printCombinedBtn').fadeIn();
    }

    $('#filterStaff, #filterStartDate, #filterEndDate, #filterDivision, #filterStatus, #filterLead').on('change keyup', checkPrintEligibility);

    // Enhanced Print Staff Report - works with all current filters
    $('#printStaffBtn').on('click', function() {
      const filters = getCurrentFilters();

      if (filters.staff && filters.staff.length > 0) {
        // Use the first selected staff member for individual report
        const staffId = filters.staff[0];
        const queryParams = buildFilterQueryString(filters);
        window.open(`<?= base_url('weektasks/print_staff_report_filtered/') ?>${staffId}?${queryParams}`, '_blank');
      } else {
        show_notification('Please select at least one staff member to print individual report', 'warning');
      }
    });

    // Enhanced Print Division Report - works with all current filters
    $('#printDivisionBtn').on('click', function() {
      const filters = getCurrentFilters();

      if (filters.division) {
        const queryParams = buildFilterQueryString(filters);
        window.open(`<?= base_url('weektasks/print_division_report_filtered/') ?>${filters.division}?${queryParams}`, '_blank');
      } else {
        show_notification('Please select a division to print division report', 'warning');
      }
    });

    // Enhanced Print Combined Report - works with all current filters
    $('#printCombinedBtn').on('click', function() {
      const filters = getCurrentFilters();

      if (filters.division) {
        const queryParams = buildFilterQueryString(filters);
        window.open(`<?= base_url('weektasks/print_combined_division_report_filtered/') ?>${filters.division}?${queryParams}`, '_blank');
      } else {
        show_notification('Please select a division to print combined report', 'warning');
      }
    });

    // Helper function to get current filter values
    function getCurrentFilters() {
      return {
        staff: $('#filterStaff').val() || [],
        division: $('#filterDivision').val() || '',
        start_date: $('#filterStartDate').val() || '',
        end_date: $('#filterEndDate').val() || '',
        status: $('#filterStatus').val() || 'all',
        teamlead: $('#filterLead').val() || 'all'
      };
    }

    // Helper function to build query string from filters
    function buildFilterQueryString(filters) {
      const params = new URLSearchParams();

      if (filters.staff && filters.staff.length > 0) {
        params.append('staff_ids', filters.staff.join(','));
      }
      if (filters.start_date) {
        params.append('start_date', filters.start_date);
      }
      if (filters.end_date) {
        params.append('end_date', filters.end_date);
      }
      if (filters.status && filters.status !== 'all') {
        params.append('status', filters.status);
      }
      if (filters.teamlead && filters.teamlead !== 'all') {
        params.append('teamlead', filters.teamlead);
      }

      return params.toString();
    }


  });
</script>
<script>
  $(document).ready(function() {
    const minActivities = 1;

    function updateRemoveButtons() {
      const count = $('#activityContainer .activity-row').length;
      $('.remove-activity').prop('disabled', count <= minActivities);
    }

    // Add new activity row
    $('#activityContainer').on('click', '.add-activity', function() {
      const row = $(this).closest('.activity-row');
      const newRow = row.clone();

      newRow.find('input').val('');
      $('#activityContainer').append(newRow);

      updateRemoveButtons();
    });

    // Remove activity row
    $('#activityContainer').on('click', '.remove-activity', function() {
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
  $(document).ready(function() {
    $('#team_lead_select').on('change', function() {
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
        success: function(data) {
          let options = '<option value="">Select</option>';
          $.each(data, function(i, item) {
            options += `<option value="${item.activity_id}">${item.activity_name}</option>`;
          });
          subActivitySelect.html(options);
        },
        error: function(xhr, status, error) {
          console.error('AJAX Error:', status, error);
          subActivitySelect.html('<option value="">Failed to load</option>');
        }
      });
    });
  });
</script>