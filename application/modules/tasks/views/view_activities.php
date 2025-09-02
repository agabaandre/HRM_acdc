<style>
  /* Enhanced Activity Page Styling */

  .filter-card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-radius: 1px;
    margin-bottom: 2rem;
  }

  .filter-card .card-header {
    background: rgba(52, 143, 65, 1);
    color: white;
    border-radius: 2px 2px 0 0;
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

  /* Enhanced Stats Styling */
  .stats-container {
    background: white;
    border-radius: 2px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    padding: 2rem;
    margin-bottom: 2rem;
  }

  .stat-item {
    text-align: center;
    padding: 1.5rem 1rem;
    border-radius: 2px;
    background: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
  }

  .stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  }

  .stat-item.total {
    --stat-color: #17a2b8;
    --stat-color-light: #20c997;
  }

  .stat-item.completed {
    --stat-color: #28a745;
    --stat-color-light: #34ce57;
  }

  .stat-item.pending {
    --stat-color: #ffc107;
    --stat-color-light: #ffed4e;
  }

  .stat-item.overdue {
    --stat-color: #dc3545;
    --stat-color-light: #e74c3c;
  }

  .stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--stat-color);
    display: block;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .stat-label {
    font-size: 0.9rem;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }

  .stat-icon {
    font-size: 1.5rem;
    color: var(--stat-color);
    margin-bottom: 0.5rem;
    display: block;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }

  /* Team Performance Cards */
  .team-member-card {
    background: white;
    border-radius: 2px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    padding: 1rem;
    margin-bottom: 0.75rem;
    transition: all 0.3s ease;
  }

  .team-member-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
  }

  .member-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(52, 143, 65, 1), rgba(40, 120, 50, 1));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 1rem;
  }

  .member-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6c757d, #495057);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 0.9rem;
    margin: 0 auto;
  }

  .performance-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 0.5rem;
  }

  .performance-fill {
    height: 100%;
    background: linear-gradient(90deg, rgba(52, 143, 65, 1), rgba(40, 120, 50, 1));
    border-radius: 4px;
    transition: width 1s ease-out;
  }

  /* Enhanced Table Styling */
  .table {
    border-radius: 2px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
  }

  .table thead th {
    background: linear-gradient(135deg, #343a40 0%, #495057 100%);
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
    padding: 1rem 0.75rem;
    border: none;
  }

  .table tbody tr {
    transition: all 0.3s ease;
  }

  .table tbody tr:hover {
    background-color: rgba(52, 143, 65, 0.05);
    transform: scale(1.01);
  }

  .table tbody td {
    padding: 1rem 0.75rem;
    vertical-align: middle;
    border-color: #f1f3f4;
  }

  /* Text wrapping for table cells */
  .table td.text-wrap {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    max-width: 200px;
  }

  /* Specific column widths for better layout */
  #activitiesTable th:nth-child(1),
  #activitiesTable td:nth-child(1) {
    width: 50px;
    text-align: center;
  }

  #activitiesTable th:nth-child(2),
  #activitiesTable td:nth-child(2) {
    width: 250px;
    min-width: 200px;
  }

  #activitiesTable th:nth-child(3),
  #activitiesTable td:nth-child(3) {
    width: 150px;
    min-width: 120px;
  }

  #activitiesTable th:nth-child(4),
  #activitiesTable td:nth-child(4) {
    width: 120px;
    min-width: 100px;
  }

  #activitiesTable th:nth-child(5),
  #activitiesTable td:nth-child(5) {
    width: 100px;
  }

  #activitiesTable th:nth-child(6),
  #activitiesTable td:nth-child(6) {
    width: 100px;
  }

  #activitiesTable th:nth-child(7),
  #activitiesTable td:nth-child(7) {
    width: 120px;
  }

  #activitiesTable th:nth-child(8),
  #activitiesTable td:nth-child(8) {
    width: 120px;
  }

  #activitiesTable th:nth-child(9),
  #activitiesTable td:nth-child(9) {
    width: 120px;
    text-align: center;
  }

  .status-badge {
    padding: 0.5rem 1rem;
    border-radius: 25px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

  /* Rich text content styling */
  #previewReportContent {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.6;
  }

  #previewReportContent h1,
  #previewReportContent h2,
  #previewReportContent h3,
  #previewReportContent h4,
  #previewReportContent h5,
  #previewReportContent h6 {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
  }

  #previewReportContent p {
    margin-bottom: 1rem;
  }

  #previewReportContent ul,
  #previewReportContent ol {
    margin-bottom: 1rem;
    padding-left: 2rem;
  }

  #previewReportContent table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1rem;
  }

  #previewReportContent table th,
  #previewReportContent table td {
    border: 1px solid #dee2e6;
    padding: 0.5rem;
    text-align: left;
  }

  #previewReportContent table th {
    background-color: #f8f9fa;
    font-weight: 600;
  }

  /* Summernote editor styling */
  .note-editor {
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
  }

  .note-editor .note-toolbar {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
  }

  .note-editor .note-editing-area {
    background-color: white;
  }
</style>

<?php $this->load->view('tasks_tabs')?>

<?php
// Prepare header data
$header_data = [
    'title' => 'Activity Management Dashboard',
    'subtitle' => 'Track team performance and activity progress',
    'icon' => 'fa-tasks',
    'actions' => [
        [
            'text' => 'Refresh',
            'icon' => 'fa-sync-alt',
            'class' => 'btn-light',
            'onclick' => 'onclick="refreshData()"'
        ]
    ]
];

// Load shared header
$this->load->view('templates/partials/shared_page_header', $header_data);
?>

<div class="container-fluid">
  <!-- Unit Lead Statistics -->
  <div class="stats-container">
    <h5 class="mb-4 fw-bold text-center">
      <i class="fa fa-chart-bar me-2"></i>Team Performance Overview
    </h5>
    <div class="row g-3">
      <div class="col-md-3">
        <div class="stat-item total">
          <i class="fa fa-tasks stat-icon"></i>
          <span class="stat-number" id="totalActivities">0</span>
          <span class="stat-label">Total Activities</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item completed">
          <i class="fa fa-check-circle stat-icon"></i>
          <span class="stat-number" id="completedActivities">0</span>
          <span class="stat-label">Completed</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item pending">
          <i class="fa fa-clock stat-icon"></i>
          <span class="stat-number" id="pendingActivities">0</span>
          <span class="stat-label">In Progress</span>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stat-item overdue">
          <i class="fa fa-exclamation-triangle stat-icon"></i>
          <span class="stat-number" id="overdueActivities">0</span>
          <span class="stat-label">Overdue</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Activity Button (Unit Leads Only) -->
  <?php  
  $session = $this->session->userdata('user');
  if(is_unit_lead($session->staff_id)): ?>
  <div class="row mb-3">
    <div class="col-12">
      <button class="btn btn-dark btn-modern" data-bs-toggle="modal" data-bs-target="#addActivitiesModal">
        <i class="fa fa-plus-circle me-1"></i> Add Sub-Activities
      </button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Enhanced Filters ------>
  <div class="card filter-card">
    <div class="card-header">
      <h5 class="mb-0">
        <i class="fa fa-filter me-2"></i>Filter Activities
      </h5>
    </div>
    <div class="card-body">
      <?= form_open('', ['id' => 'filterForm']) ?>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-calendar me-1"></i>Start Date
          </label>
          <input type="text" id="filterStartDate" class="form-control datepicker" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-calendar me-1"></i>End Date
          </label>
          <input type="text" id="filterEndDate" class="form-control datepicker" placeholder="YYYY-MM-DD">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-users me-1"></i>Team Members
          </label>
          <select id="filterTeamMembers" class="form-select select2" multiple>
            <?php if(isset($team_members)): ?>
              <?php foreach ($team_members as $member): ?>
                <option value="<?= $member->staff_id ?>"><?= $member->fname . ' ' . $member->lname ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">
            <i class="fa fa-briefcase me-1"></i>Work Plan
          </label>
          <select id="filterWorkPlan" class="form-select">
            <option value="">All Work Plans</option>
            <?php if(isset($work_plans)): ?>
              <?php foreach ($work_plans as $plan): ?>
                <option value="<?= $plan->id ?>"><?= $plan->activity_name ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-12 text-end">
          <button type="button" class="btn btn-success btn-modern" id="applyFilters">
            <i class="fa fa-search me-1"></i> Apply Filters
          </button>
          <button type="button" class="btn btn-outline-secondary btn-modern ms-2" id="clearFilters">
            <i class="fa fa-times me-1"></i> Clear All
          </button>
          <button type="button" class="btn btn-primary btn-modern ms-2" id="printReport">
            <i class="fa fa-file-pdf me-1"></i> Print Report
          </button>
        </div>
      </div>
      <?= form_close(); ?>
    </div>
  </div>

  <!-- Team Performance Breakdown -->
  <div class="card table-card mb-4">
    <div class="card-header text-white">
      <h5 class="mb-0 text-white">
        <i class="fa fa-users me-2 text-white"></i>Team Performance Breakdown
      </h5>
    </div>
    <div class="card-body">
      <div id="teamPerformanceContainer">
        <!-- Team member performance cards will be loaded here -->
      </div>
    </div>
  </div>

  <!-- Activities Table -->
  <div class="card table-card">
    <div class="card-header text-white">
      <h5 class="mb-0 text-white">
        <i class="fa fa-list me-2 text-white"></i>Activities
      </h5>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="activitiesTable">
                    <thead>
            <tr>
              <th>#</th>
                <th>Activity Name</th>
              <th>Team Member</th>
              <th>Division</th>
                <th>Start Date</th>
                <th>End Date</th>
              <th>Status</th>
              <th>Progress</th>
              <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <!-- Activities will be loaded here via DataTables server-side processing -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="loading-spinner">
    <div class="spinner-border text-success" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <p class="mt-2">Loading activities...</p>
  </div>
</div>

<script>
$(document).ready(function() {
  const csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
  const csrfHash = '<?= $this->security->get_csrf_hash(); ?>';

  // Initialize date pickers
  $('.datepicker').datepicker({
    format: 'yyyy-mm-dd',
    autoclose: true,
    todayHighlight: true
  });

  // Initialize select2 for team members
  $('#filterTeamMembers').select2({
    placeholder: 'Select team members',
    allowClear: true
  });

  // Initialize select2 for workplan dropdown in add activities modal
  $('#quarterly_output_id').select2({
    placeholder: 'Choose a workplan activity...',
    allowClear: true,
    width: '100%',
    dropdownParent: $('#addActivitiesModal')
  });

  // Initialize Summernote rich text editor
  function initializeSummernote() {
    $('.summernote').summernote({
      height: 200,
      minHeight: 150,
      maxHeight: 300,
      focus: false,
      toolbar: [
        ['style', ['style']],
        ['font', ['bold', 'italic', 'underline', 'clear']],
        ['fontname', ['fontname']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'picture', 'video']],
        ['view', ['fullscreen', 'codeview', 'help']]
      ],
      styleTags: ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
      fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
      fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '36', '48'],
      callbacks: {
        onInit: function() {
          // Ensure the editor is properly initialized
        }
      }
    });
  }

  // Initialize Summernote when the modal is shown
  $('#submitReportModal').on('shown.bs.modal', function() {
    initializeSummernote();
  });

  // Destroy Summernote when modal is hidden to prevent conflicts
  $('#submitReportModal').on('hidden.bs.modal', function() {
    $('.summernote').summernote('destroy');
  });

  // Initialize DataTable with server-side processing
  let activitiesTable;
  
  function initializeDataTable() {
    activitiesTable = $('#activitiesTable').DataTable({
      processing: true,
      serverSide: true,
      responsive: true,
      autoWidth: false,
      ajax: {
        url: '<?= base_url("tasks/fetch_activities_filtered") ?>',
        type: 'POST',
        data: function(d) {
          // Add filter data to the request
          d.start_date = $('#filterStartDate').val();
          d.end_date = $('#filterEndDate').val();
          d.team_members = $('#filterTeamMembers').val();
          d.work_plan = $('#filterWorkPlan').val();
          d[csrfName] = csrfHash;
        },
        error: function(xhr, error, thrown) {
          console.error('DataTables AJAX error:', error);
          console.error('Response:', xhr.responseText);
        }
      },
      columns: [
        { 
          data: null,
          name: 'row_number',
          orderable: false,
          searchable: false,
          render: function(data, type, row, meta) {
            return meta.row + meta.settings._iDisplayStart + 1;
          }
        },
        { 
          data: 'activity_name', 
          name: 'activity_name',
          render: function(data, type, row) {
            return data || 'N/A';
          },
          createdCell: function(td, cellData, rowData, row, col) {
            $(td).addClass('text-wrap');
          }
        },
        { 
          data: 'member_name', 
          name: 'member_name',
          render: function(data, type, row) {
            return data || 'N/A';
          },
          createdCell: function(td, cellData, rowData, row, col) {
            $(td).addClass('text-wrap');
          }
        },
        { 
          data: 'division_name', 
          name: 'division_name',
          render: function(data, type, row) {
            return data || 'N/A';
          },
          createdCell: function(td, cellData, rowData, row, col) {
            $(td).addClass('text-wrap');
          }
        },
        { data: 'start_date', name: 'start_date' },
        { data: 'end_date', name: 'end_date' },
        { 
          data: 'status', 
          name: 'status',
          render: function(data, type, row) {
            const hasReport = row.report_id && row.report_id !== '';
            return getStatusBadge(data, hasReport);
          }
        },
        { 
          data: 'report_id', 
          name: 'report_id',
          render: function(data, type, row) {
            const hasReport = data && data !== '';
            return getProgressBar(hasReport ? 100 : 0);
          }
        },
                   {
             data: 'activity_id',
             name: 'activity_id',
             orderable: false,
             searchable: false,
             render: function(data, type, row) {
               const hasReport = row.report_id && row.report_id !== '';
               return `
                 <div class="btn-group" role="group">
                   <button class="btn btn-sm btn-warning edit-activity-btn"
                           data-id="${row.activity_id || ''}"
                           data-name="${row.activity_name || ''}"
                           data-start_date="${row.start_date || ''}"
                           data-end_date="${row.end_date || ''}"
                           data-comments="${row.comments || ''}"
                           title="Edit Activity">
                     <i class="fa fa-edit"></i>
                   </button>
                   ${hasReport ?
                     `<button class="btn btn-sm btn-info preview-report-btn"
                             data-activity-id="${row.activity_id || ''}"
                             data-activity-name="${row.activity_name || ''}"
                             data-report-id="${row.report_id || ''}"
                             data-report-description="${row.report || ''}"
                             data-report-date="${row.report_date || ''}"
                             title="Preview Report">
                       <i class="fa fa-eye"></i>
                     </button>
                     <button class="btn btn-sm btn-success print-activity-report-btn"
                             data-activity-id="${row.activity_id || ''}"
                             data-activity-name="${row.activity_name || ''}"
                             data-report-id="${row.report_id || ''}"
                             title="Print Activity Report">
                       <i class="fa fa-file-pdf"></i>
                     </button>` :
                     `<button class="btn btn-sm btn-primary submit-report-btn"
                             data-activity-id="${row.activity_id || ''}"
                             data-activity-name="${row.activity_name || ''}"
                             title="Submit Report">
                       <i class="fa fa-file-text"></i>
                     </button>`
                   }
                 </div>
               `;
             }
           }
      ],
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      order: [[4, 'desc']], // Order by start date descending by default
      language: {
        processing: "Loading activities...",
        emptyTable: "No activities found",
        zeroRecords: "No matching activities found"
      },
      dom: 'Bfrtip',
      buttons: [
        {
          extend: 'excelHtml5',
          title: 'Activities Export',
          exportOptions: {
            columns: [1, 2, 3, 4, 5, 6, 7] // Exclude numbering and actions columns
          }
        },
        {
          extend: 'csvHtml5',
          title: 'Activities Export',
          exportOptions: {
            columns: [1, 2, 3, 4, 5, 6, 7] // Exclude numbering and actions columns
          }
        },
        {
          extend: 'pdfHtml5',
          title: 'Activities Export',
          exportOptions: {
            columns: [1, 2, 3, 4, 5, 6, 7] // Exclude numbering and actions columns
          }
        }
      ]
    });
  }

  // Load initial data
  initializeDataTable();
  loadTeamPerformance();
  loadStatistics();

  // Filter functionality
  $('#applyFilters').on('click', function() {
    showLoading();
    activitiesTable.ajax.reload();
    loadTeamPerformance();
    loadStatistics();
    setTimeout(hideLoading, 1000);
  });

  $('#clearFilters').on('click', function() {
    $('#filterForm')[0].reset();
    $('#filterTeamMembers').val(null).trigger('change');
    showLoading();
    activitiesTable.ajax.reload();
    loadTeamPerformance();
    loadStatistics();
    setTimeout(hideLoading, 1000);
  });

  // Auto-apply filters on change
  let filterTimeout;
  $('#filterStartDate, #filterEndDate, #filterTeamMembers, #filterWorkPlan').on('change', function() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
      showLoading();
      activitiesTable.ajax.reload();
      loadTeamPerformance();
      loadStatistics();
      setTimeout(hideLoading, 1000);
    }, 500);
  });

  // Print Report functionality
  $('#printReport').on('click', function() {
    const filters = {
      start_date: $('#filterStartDate').val(),
      end_date: $('#filterEndDate').val(),
      team_members: $('#filterTeamMembers').val(),
      work_plan: $('#filterWorkPlan').val()
    };

    // Build query string
    const queryParams = new URLSearchParams();
    Object.keys(filters).forEach(key => {
      if (filters[key] && filters[key] !== '') {
        if (Array.isArray(filters[key])) {
          filters[key].forEach(value => {
            queryParams.append(key + '[]', value);
          });
        } else {
          queryParams.append(key, filters[key]);
        }
      }
    });

    // Open print URL in new window
    const printUrl = '<?= base_url("tasks/print_activity_report") ?>?' + queryParams.toString();
    window.open(printUrl, '_blank');
    
    show_notification('Generating PDF report...', 'info');
  });

  // Print Individual Activity Report functionality
  $(document).on('click', '.print-activity-report-btn', function() {
    const activityId = $(this).data('activity-id');
    const activityName = $(this).data('activity-name');
    
    // Open individual activity report in new window
    const printUrl = '<?= base_url("tasks/print_individual_activity_report") ?>/' + activityId;
    window.open(printUrl, '_blank');
    
    show_notification('Generating activity report for: ' + activityName, 'info');
  });

  function loadTeamPerformance() {
    const filters = {
      start_date: $('#filterStartDate').val(),
      end_date: $('#filterEndDate').val(),
      team_members: $('#filterTeamMembers').val(),
      work_plan: $('#filterWorkPlan').val()
    };

    console.log('Loading team performance with filters:', filters);

    $.ajax({
      url: '<?= base_url("tasks/get_team_performance") ?>',
      method: 'POST',
      data: {
        ...filters,
        [csrfName]: csrfHash
      },
      dataType: 'json',
      success: function(response) {
        console.log('Team performance response:', response);
        if (response.success) {
          populateTeamPerformance(response.data);
        } else {
          console.error('Team performance load failed:', response.message);
          $('#teamPerformanceContainer').html('<p class="text-center text-danger">Error loading team performance: ' + (response.message || 'Unknown error') + '</p>');
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX error loading team performance:', error);
        console.error('Response:', xhr.responseText);
        $('#teamPerformanceContainer').html('<p class="text-center text-danger">Failed to load team performance. Please try again.</p>');
      }
    });
  }

  function loadStatistics() {
    const filters = {
      start_date: $('#filterStartDate').val(),
      end_date: $('#filterEndDate').val(),
      team_members: $('#filterTeamMembers').val(),
      work_plan: $('#filterWorkPlan').val()
    };

    console.log('Loading statistics with filters:', filters);

    $.ajax({
      url: '<?= base_url("tasks/get_activity_statistics") ?>',
      method: 'POST',
      data: {
        ...filters,
        [csrfName]: csrfHash
      },
      dataType: 'json',
      success: function(response) {
        console.log('Statistics response:', response);
        if (response.success) {
          updateStatistics(response.data);
        } else {
          console.error('Statistics load failed:', response.message);
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX error loading statistics:', error);
        console.error('Response:', xhr.responseText);
      }
    });
  }



  function populateTeamPerformance(teamData) {
    const container = $('#teamPerformanceContainer');
    if (container.length === 0) {
      console.error('Team performance container not found');
      return;
    }
    
    container.empty();

    if (!teamData || teamData.length === 0) {
      container.append('<p class="text-center text-muted">No team performance data available</p>');
      return;
    }

    teamData.forEach(function(member, index) {
      const initials = ((member.fname || '').charAt(0) + (member.lname || '').charAt(0)).toUpperCase();
      const completionRate = (member.total_activities || 0) > 0 ? Math.round(((member.completed_activities || 0) / (member.total_activities || 1)) * 100) : 0;
      
      const card = `
        <div class="team-member-card">
          <div class="row align-items-center">
            <div class="col-md-1">
              <div class="member-number">${index + 1}</div>
            </div>
            <div class="col-md-1">
              <div class="member-avatar">${initials}</div>
            </div>
            <div class="col-md-3">
              <h6 class="mb-1">${member.fname || ''} ${member.lname || ''}</h6>
              <small class="text-muted">${member.job_name || member.job_title || 'Team Member'}</small>
            </div>
            <div class="col-md-3">
              <div class="d-flex justify-content-between">
                <span class="text-muted">Completed:</span>
                <span class="fw-bold">${member.completed_activities || 0}/${member.total_activities || 0}</span>
              </div>
              <div class="performance-bar">
                <div class="performance-fill" style="width: ${completionRate}%"></div>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <span class="badge bg-success">${completionRate}% Complete</span>
            </div>
          </div>
        </div>
      `;
      container.append(card);
    });
  }

  function updateStatistics(stats) {
    animateNumber('#totalActivities', stats.total || 0);
    animateNumber('#completedActivities', stats.completed || 0);
    animateNumber('#pendingActivities', stats.pending || 0);
    animateNumber('#overdueActivities', stats.overdue || 0);
  }

  function getStatusBadge(status, hasReport) {
    // If activity has a report, mark it as completed regardless of status
    if (hasReport) {
      return '<span class="status-badge bg-success text-white">Completed</span>';
    }
    
    const statusMap = {
      1: '<span class="status-badge bg-warning text-dark">Pending</span>',
      2: '<span class="status-badge bg-success text-white">Completed</span>',
      3: '<span class="status-badge bg-primary text-white">Carried Forward</span>',
      4: '<span class="status-badge bg-danger text-white">Cancelled</span>'
    };
    return statusMap[parseInt(status)] || '<span class="status-badge bg-secondary text-white">Unknown</span>';
  }

  function getProgressBar(progress) {
    return `
      <div class="progress" style="height: 8px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: ${progress}%"></div>
      </div>
      <small class="text-muted">${progress}%</small>
    `;
  }

  function animateNumber(selector, targetNumber) {
    const element = $(selector);
    const startNumber = parseInt(element.text()) || 0;
    const duration = 1000;
    const increment = (targetNumber - startNumber) / (duration / 16);
    let currentNumber = startNumber;
    
    const timer = setInterval(() => {
      currentNumber += increment;
      if ((increment > 0 && currentNumber >= targetNumber) || 
          (increment < 0 && currentNumber <= targetNumber)) {
        currentNumber = targetNumber;
        clearInterval(timer);
      }
      element.text(Math.round(currentNumber));
    }, 16);
  }

  function showLoading() {
    $('#loadingOverlay').css('display', 'flex');
  }

  function hideLoading() {
    $('#loadingOverlay').hide();
  }

  window.refreshData = function() {
    showLoading();
    activitiesTable.ajax.reload();
    loadTeamPerformance();
    loadStatistics();
    setTimeout(hideLoading, 1000);
  };

  // Edit Activity functionality
  $(document).on('click', '.edit-activity-btn', function() {
    const data = $(this).data();
    $('#edit_activity_id').val(data.id);
    $('#edit_activity_name').val(data.name);
    $('#edit_start_date').val(data.start_date);
    $('#edit_end_date').val(data.end_date);
    $('#edit_comments').val(data.comments);
    
    const editModal = new bootstrap.Modal(document.getElementById('editActivityModal'));
    editModal.show();
  });

  // Handle edit form submission
  $('#editActivityForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serializeArray();
    formData.push({
      name: csrfName,
      value: csrfHash
    });

    $.ajax({
      url: '<?= base_url("tasks/update_activity") ?>',
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          $('#editActivityModal').modal('hide');
          show_notification('Activity updated successfully!', 'success');
          activitiesTable.ajax.reload(); // Reload activities table
        } else {
          show_notification('Error updating activity: ' + response.message, 'error');
        }
      },
      error: function() {
        show_notification('Error updating activity. Please try again.', 'error');
      }
    });
  });

  // Submit Report functionality
  $(document).on('click', '.submit-report-btn', function() {
    const activityId = $(this).data('activity-id');
    const activityName = $(this).data('activity-name');
    
    // Update modal with activity info
    $('#submitReportModal .modal-title').html(`<i class="fa fa-file-text me-2"></i>Submit Report - ${activityName}`);
    $('#submitReportForm input[name="activity_id"]').val(activityId);
    
    // Clear any existing Summernote content
    if ($('#description').hasClass('note-editable')) {
      $('#description').summernote('code', '');
    } else {
      $('#description').val('');
    }
    
    const submitModal = new bootstrap.Modal(document.getElementById('submitReportModal'));
    submitModal.show();
  });

  // Preview Report functionality
  $(document).on('click', '.preview-report-btn', function() {
    const data = $(this).data();
    
    // Update modal with report info
    $('#previewReportModal .modal-title').html(`<i class="fa fa-eye me-2"></i>Report Preview - ${data.activityName}`);
    
    // Display rich text content properly
    const reportContent = data.reportDescription || 'No report content available';
    $('#previewReportContent').html(reportContent);
    $('#previewReportDate').text(data.reportDate || 'N/A');
    
    const previewModal = new bootstrap.Modal(document.getElementById('previewReportModal'));
    previewModal.show();
  });

  // Handle submit report form
  $('#submitReportForm').on('submit', function(e) {
    e.preventDefault();
    
    // Get the rich text content from Summernote
    const description = $('#description').summernote('code');
    
    // Validate that description is not empty
    if (!description || description.trim() === '' || description === '<p><br></p>') {
      show_notification('Please enter a report description.', 'error');
      return;
    }
    
    const formData = {
      activity_id: $('#submitReportForm input[name="activity_id"]').val(),
      description: description,
      [csrfName]: csrfHash
    };

    $.ajax({
      url: '<?= base_url("tasks/submit_report/") ?>' + formData.activity_id,
      method: 'POST',
      data: formData,
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          $('#submitReportModal').modal('hide');
          show_notification('Report submitted successfully!', 'success');
          activitiesTable.ajax.reload(); // Reload activities table
        } else {
          show_notification('Error submitting report: ' + response.message, 'error');
        }
      },
      error: function() {
        show_notification('Error submitting report. Please try again.', 'error');
      }
    });
  });

  // Add Activity functionality
  $('#addActivityForm').on('submit', function(e) {
    e.preventDefault();

    if (!this.checkValidity()) {
      $(this).addClass('was-validated');
      return;
    }

    // Extract form and CSRF token
    var form = $(this);
    var formData = form.serializeArray();
    var csrfName = '<?= $this->security->get_csrf_token_name(); ?>';
    var csrfValue = $('input[name="<?= $this->security->get_csrf_token_name(); ?>"]').val();

    formData.push({
      name: csrfName,
      value: csrfValue
    });

    $.post('<?= base_url("tasks/add_activity") ?>', formData, function(res) {
      if (res.status === 'success') {
        show_notification(res.message, 'success');
        $('#addActivityForm')[0].reset();
        $('#addActivityForm').removeClass('was-validated');
        $('#addActivitiesModal').modal('hide');
        activitiesTable.ajax.reload(); // Reload activities table
      } else {
        show_notification(res.message, 'error');
      }

      // If you're using csrf_regenerate = TRUE
      if (res.new_csrf_hash) {
        $('input[name="<?= $this->security->get_csrf_token_name(); ?>"]').val(res.new_csrf_hash);
      }
    }, 'json');
  });

  // Add/Remove row functionality for activities
  const minRows = 1;

  function initFlatpickr(elem) {
    flatpickr(elem, {
      theme: "confetti",
      altInput: true,
      altFormat: "F j, Y",
      dateFormat: "Y-m-d",
      allowInput: true,
      appendTo: document.body
    });
  }

  // Initialize flatpickr on existing datepickers
  $('.datepicker').each(function() {
    initFlatpickr(this);
  });

  function updateRemoveButtons() {
    const rowCount = $('#activityRows .activity-row').length;
    $('.remove-row').prop('disabled', rowCount <= minRows);
  }

  // Add new row
  $('#activityRows').on('click', '.add-row', function() {
    const newRow = `
      <tr class="activity-row">
        <td>
          <input type="text" name="activity_name[]" class="form-control" required placeholder="Enter activity name">
          <div class="invalid-feedback">Required</div>
        </td>
        <td>
          <input type="text" name="start_date[]" class="form-control datepicker" required autocomplete="off" placeholder="Select start date">
          <div class="invalid-feedback">Required</div>
        </td>
        <td>
          <input type="text" name="end_date[]" class="form-control datepicker" required autocomplete="off" placeholder="Select end date">
          <div class="invalid-feedback">Required</div>
        </td>
        <td>
          <textarea name="comments[]" class="form-control" rows="2" placeholder="Optional comments"></textarea>
        </td>
        <td class="text-center">
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-success btn-sm add-row" title="Add Row">
              <i class="fa fa-plus"></i>
            </button>
            <button type="button" class="btn btn-danger btn-sm remove-row" title="Remove Row">
              <i class="fa fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>`;

    $('#activityRows').append(newRow);

    // Only initialize flatpickr on new fields
    $('#activityRows tr:last .datepicker').each(function() {
      initFlatpickr(this);
    });

    updateRemoveButtons();
  });

  // Remove row
  $('#activityRows').on('click', '.remove-row', function() {
    if ($('#activityRows .activity-row').length > minRows) {
      $(this).closest('tr').remove();
      updateRemoveButtons();
    }
  });

  updateRemoveButtons();

  function show_notification(message, type) {
    Lobibox.notify(type, {
      pauseDelayOnHover: true,
      continueDelayOnInactiveTab: false,
      position: 'top right',
      icon: type === 'success' ? 'bx bx-check-circle' : 'bx bx-error-circle',
      msg: message
    });
  }
});
</script>

<!-- Edit Activity Modal -->
<div class="modal fade" id="editActivityModal" tabindex="-1" aria-labelledby="editActivityModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editActivityModalLabel">
          <i class="fa fa-edit me-2"></i>Edit Activity
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editActivityForm">
        <div class="modal-body">
          <input type="hidden" name="activity_id" id="edit_activity_id">
          
          <div class="row g-3">
            <div class="col-md-12">
              <label for="edit_activity_name" class="form-label">Activity Name</label>
              <input type="text" class="form-control" id="edit_activity_name" name="activity_name" required>
            </div>
            
            <div class="col-md-6">
              <label for="edit_start_date" class="form-label">Start Date</label>
              <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
            </div>
            
            <div class="col-md-6">
              <label for="edit_end_date" class="form-label">End Date</label>
              <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
            </div>
            
            <div class="col-md-6">
              <label class="form-label">Completion Status</label>
              <div class="form-control-plaintext">
                <span class="badge bg-info">Based on Report Submission</span>
                <small class="text-muted d-block">Activity is completed when a report is submitted</small>
              </div>
            </div>
            
            <div class="col-md-12">
              <label for="edit_comments" class="form-label">Comments</label>
              <textarea class="form-control" id="edit_comments" name="comments" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fa fa-save me-1"></i>Update Activity
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

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
          <input type="hidden" name="activity_id" value="">
          
          <div class="form-group mb-3">
            <label for="description" class="form-label">Report Description:</label>
            <textarea name="description" id="description" class="form-control summernote" rows="8" required placeholder="Describe the completion of this activity..."></textarea>
            <div class="invalid-feedback">Please enter a description.</div>
            <div class="form-text">
              <i class="fa fa-info-circle me-1"></i>Use the toolbar above to format your report with bold, italic, lists, and more.
            </div>
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

<!-- Preview Report Modal -->
<div class="modal fade" id="previewReportModal" tabindex="-1" aria-labelledby="previewReportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewReportModalLabel">
          <i class="fa fa-eye me-2"></i>Report Preview
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <strong>Report Date:</strong> <span id="previewReportDate"></span>
          </div>
          <div class="col-md-6">
            <span class="badge bg-success">Report Submitted</span>
          </div>
        </div>
        <hr>
        <div class="report-content">
          <h6>Report Description:</h6>
          <div id="previewReportContent" class="border p-3 rounded bg-light" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
            <!-- Report content will be loaded here -->
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Activities Modal -->
<div class="modal fade" id="addActivitiesModal" tabindex="-1" aria-labelledby="addActivitiesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <?= form_open('', [
          'id' => 'addActivityForm',
          'class' => 'needs-validation',
          'novalidate' => 'novalidate'
      ]) ?>

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="addActivitiesModalLabel">
          <i class="fa fa-plus-circle me-2"></i>Add New Sub-Activities
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <!-- Output Selection -->
        <div class="mb-4">
          <label for="quarterly_output_id" class="form-label fw-bold">
            <i class="fa fa-briefcase me-2"></i>Work Plan Activity:
          </label>
          <div class="input-group">
            <span class="input-group-text">
              <i class="fa fa-list"></i>
            </span>
            <select name="quarterly_output_id" id="quarterly_output_id" class="form-select select2" required>
              <option value="">Choose a workplan activity...</option>
              <?php if(isset($work_plans) && !empty($work_plans)): ?>
                <?php foreach ($work_plans as $workplan): ?>
                  <option value="<?= $workplan->id ?>"><?= htmlspecialchars($workplan->activity_name) ?></option>
            <?php endforeach; ?>
              <?php else: ?>
                <option value="" disabled>No workplan activities available for your division</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-text">
            <i class="fa fa-info-circle me-1"></i>Select the main workplan activity to create sub-activities for
            <?php if(isset($work_plans)): ?>
              <span class="badge bg-info ms-2"><?= count($work_plans) ?> activities available</span>
            <?php endif; ?>
          </div>
          <div class="invalid-feedback">Please select a work plan activity.</div>
        </div>

        <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">

        <!-- Activities Table -->
        <div class="table-responsive">
          <table class="table table-bordered align-middle table-hover">
            <thead class="table-dark text-center">
              <tr>
                <th><i class="fa fa-tasks me-1"></i>Activity Name</th>
                <th><i class="fa fa-calendar-start me-1"></i>Start Date</th>
                <th><i class="fa fa-calendar-end me-1"></i>End Date</th>
                <th><i class="fa fa-comment me-1"></i>Comments</th>
                <th><i class="fa fa-cog me-1"></i>Actions</th>
              </tr>
            </thead>
            <tbody id="activityRows">
              <tr class="activity-row">
                <td>
                  <input type="text" name="activity_name[]" class="form-control" required placeholder="Enter activity name">
                  <div class="invalid-feedback">Required</div>
                </td>
                <td>
                  <input type="text" name="start_date[]" class="form-control datepicker" required autocomplete="off" placeholder="Select start date">
                  <div class="invalid-feedback">Required</div>
                </td>
                <td>
                  <input type="text" name="end_date[]" class="form-control datepicker" required autocomplete="off" placeholder="Select end date">
                  <div class="invalid-feedback">Required</div>
                </td>
                <td>
                  <textarea name="comments[]" class="form-control" rows="2" placeholder="Optional comments"></textarea>
                </td>
                <td class="text-center">
                  <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success btn-sm add-row" title="Add Row">
                      <i class="fa fa-plus"></i>
                    </button>
                    <button type="button" class="btn btn-danger btn-sm remove-row" title="Remove Row">
                      <i class="fa fa-trash"></i>
                    </button>
                  </div>
                </td>
            </tr>
        </tbody>
    </table>
        </div>
      </div>

      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fa fa-times me-1"></i>Cancel
        </button>
        <button type="submit" class="btn btn-success">
          <i class="fa fa-save me-1"></i>Save Activities
        </button>
      </div>

      <?= form_close(); ?>
    </div>
  </div>
</div>