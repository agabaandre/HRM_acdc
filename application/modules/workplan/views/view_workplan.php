<style>
  /* Enhanced styling for better UX */
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

  /* Statistics Cards Styling */
  .stat-item {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
  }

  .stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    border-color: var(--stat-color, #007bff);
  }

  .stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--stat-color, #007bff);
  }

  .stat-icon {
    font-size: 2.5rem;
    color: var(--stat-color, #007bff);
    margin-bottom: 1rem;
    display: block;
  }

  .stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--stat-color, #007bff);
    display: block;
    margin-bottom: 0.5rem;
  }

  .stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .stat-progress {
    width: 100%;
    height: 4px;
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 2px;
    margin-top: 1rem;
    overflow: hidden;
  }

  .stat-progress-bar {
    height: 100%;
    background: var(--stat-color, #007bff);
    border-radius: 2px;
    transition: width 1s ease-in-out;
  }

  /* Status-specific colors */
  .stat-item.total {
    --stat-color: #6c757d;
    --stat-color-light: #adb5bd;
  }

  .stat-item.completed {
    --stat-color: #28a745;
    --stat-color-light: #34ce57;
  }

  .stat-item.in-progress {
    --stat-color: #ffc107;
    --stat-color-light: #ffed4e;
  }

  .stat-item.overdue {
    --stat-color: #dc3545;
    --stat-color-light: #e74c3c;
  }

  .stat-item.execution-rate {
    --stat-color: #17a2b8;
    --stat-color-light: #20c997;
  }

  /* Animation keyframes */
  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
  }

  @keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes shimmer {
    0% { background-position: -200px 0; }
    100% { background-position: calc(200px + 100%) 0; }
  }

  @keyframes sparkle {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.1); }
  }

  @keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
  }

  /* Glow effect for execution rate */
  .stat-item.execution-rate:hover {
    box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
  }

  /* Success glow for completed tasks */
  .stat-item.completed:hover {
    box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
  }

  /* Warning glow for in-progress tasks */
  .stat-item.in-progress:hover {
    box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
  }

  /* Danger glow for overdue tasks */
  .stat-item.overdue:hover {
    box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
  }

  /* Sparkle effect for high execution rates */
  .sparkle {
    animation: sparkle 2s ease-in-out;
  }

  /* Tab styling */
  .nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
  }

  .nav-tabs .nav-link:hover {
    border: none;
    color: rgba(52, 143, 65, 1);
    background-color: rgba(52, 143, 65, 0.1);
  }

  .nav-tabs .nav-link.active {
    color: rgba(52, 143, 65, 1);
    background-color: rgba(52, 143, 65, 0.1);
    border: none;
    border-bottom: 3px solid rgba(52, 143, 65, 1);
  }

  .tab-content {
    padding: 2rem 0;
  }

  /* Loading overlay */
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
    .stat-item {
      margin-bottom: 1rem;
    }
    
    .stat-number {
      font-size: 2rem;
    }
    
    .stat-icon {
      font-size: 2rem;
    }
  }
</style>

<div class="container-fluid mt-4">
    <?php $this->load->view('tasks_tabs') ?>

    <!-- Enhanced Filter Card -->
    <div class="card filter-card">
        <div class="card-header">
            <h5 class="mb-0 text-white">
                <i class="fa fa-filter me-2 text-white"></i>Workplan Filters & Actions
            </h5>
        </div>
        <div class="card-body">
            <?= form_open('', ['id' => 'filterForm']) ?>
            <div class="row g-3">
                <!-- Create New -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Create New</label>
                    <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fa fa-plus-circle me-1"></i> New Activity
                    </button>
                </div>

                <!-- Download Template -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Download Template</label>
                    <a href="<?= site_url('workplan/download_template') ?>" class="btn btn-outline-primary w-100">
                        <i class="fa fa-download me-1"></i> Download Template
                    </a>
                </div>

                <!-- Export to CSV -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Export Data</label>
                    <button id="exportCsvBtn" class="btn btn-outline-success w-100">
                        <i class="fa fa-file-csv me-1"></i> Export to CSV
                    </button>
                </div>

                <!-- Search -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Search Activity</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-search"></i></span>
                        <input type="text" id="searchBox" class="form-control" placeholder="Enter keyword...">
                    </div>
                </div>

                <!-- Year Filter -->
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Year</label>
                    <select id="yearSelect" class="form-select">
                        <?php
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= 2025; $y--) {
                            echo "<option value='$y'>$y</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Division Filter -->
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Division</label>
                    <select id="divisionSelect" class="form-select">
                        <option value="">All Divisions</option>
                        <?php foreach ($divisions as $div): ?>
                            <option value="<?= $div->division_id ?>"><?= $div->division_name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="col-6">
                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="button" class="btn btn-success btn-sm" id="applyFilters" style="background-color: rgba(52, 143, 65, 1); border-color: rgba(52, 143, 65, 1);">
                            <i class="fa fa-filter me-1"></i> Apply Filters
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="clearFilters">
                            <i class="fa fa-times me-1"></i> Clear All
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="exportData">
                            <i class="fa fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>
            </div>
            <?= form_close(); ?>
        </div>
    </div>

    <!-- Upload Form -->
    <?php 
    $session = $this->session->userdata('user');
    $permissions = $session->permissions;
    ?>
    <?php if (in_array('86', $permissions)) : ?>
    <div class="card filter-card mb-4">
        <div class="card-header">
            <h5 class="mb-0 text-white">
                <i class="fa fa-upload me-2 text-white"></i>Upload Workplan
            </h5>
        </div>
        <div class="card-body">
            <?= form_open_multipart('workplan/upload_workplan', ['id' => 'uploadForm']) ?>
            <div class="input-group">
                <input type="file" name="file" class="form-control" required>
                <button class="btn btn-success" type="submit">
                    <i class="fa fa-upload me-1"></i> Upload Workplan
                </button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Workplan Statistics -->
    <div class="card table-card mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0 text-white">
                <i class="fa fa-chart-bar me-2 text-white"></i>Workplan Statistics
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-3" id="workplanStatsContainer">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-item total">
                        <i class="fa fa-tasks stat-icon"></i>
                        <span class="stat-number" id="totalActivities">0</span>
                        <span class="stat-label">Total Activities</span>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" id="totalProgress" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-item completed">
                        <i class="fa fa-check-circle stat-icon"></i>
                        <span class="stat-number" id="completedActivities">0</span>
                        <span class="stat-label">Completed</span>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" id="completedProgress" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-item in-progress">
                        <i class="fa fa-clock stat-icon"></i>
                        <span class="stat-number" id="inProgressActivities">0</span>
                        <span class="stat-label">In Progress</span>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" id="inProgressProgress" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-item overdue">
                        <i class="fa fa-exclamation-triangle stat-icon"></i>
                        <span class="stat-number" id="overdueActivities">0</span>
                        <span class="stat-label">Overdue</span>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" id="overdueProgress" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-item execution-rate">
                        <i class="fa fa-percentage stat-icon"></i>
                        <span class="stat-number" id="executionRate">0%</span>
                        <span class="stat-label">Execution Rate</span>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" id="executionProgress" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stat-item execution-rate">
                        <i class="fa fa-target stat-icon"></i>
                        <span class="stat-number" id="targetAchievement">0%</span>
                        <span class="stat-label">Target Achievement</span>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" id="targetProgress" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="card table-card mb-4">
        <div class="card-header text-white">
            <h5 class="mb-0 text-white">
                <i class="fa fa-list me-2 text-white"></i>Workplan Management
            </h5>
        </div>
        <div class="card-body">
            <ul class="nav nav-tabs" id="workplanTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="activities-tab" data-bs-toggle="tab" data-bs-target="#activities" type="button" role="tab">
                        <i class="fa fa-tasks me-1"></i> Activities
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="execution-tab" data-bs-toggle="tab" data-bs-target="#execution" type="button" role="tab">
                        <i class="fa fa-chart-line me-1"></i> Execution Tracking
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="unit-scores-tab" data-bs-toggle="tab" data-bs-target="#unit-scores" type="button" role="tab">
                        <i class="fa fa-trophy me-1"></i> Unit Performance
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="workplanTabContent">
                <!-- Activities Tab -->
                <div class="tab-pane fade show active" id="activities" role="tabpanel">
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered align-middle text-wrap">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th>#</th>
                                    <th>Year</th>
                                    <th>Division</th>
                                    <th>Intermediate Outcome</th>
                                    <th>Broad Activity</th>
                                    <th>Output Indicator</th>
                                    <th>Target</th>
                                    <th>Activity Name</th>
                                    <th>Has Budget</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="taskTableBody" class="text-center"></tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav>
                        <ul class="pagination justify-content-center mt-3" id="paginationContainer"></ul>
                    </nav>
                </div>

                <!-- Execution Tracking Tab -->
                <div class="tab-pane fade" id="execution" role="tabpanel">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Execution Progress by Activity</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped" id="executionTable">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Activity Name</th>
                                                    <th>Target</th>
                                                    <th>Sub-Activities Created</th>
                                                    <th>Sub-Activities Completed</th>
                                                    <th>Execution Rate</th>
                                                    <th>Progress</th>
                                                </tr>
                                            </thead>
                                            <tbody id="executionTableBody">
                                                <!-- Will be populated by JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unit Performance Tab -->
                <div class="tab-pane fade" id="unit-scores" role="tabpanel">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fa fa-trophy me-2"></i>Unit Performance Breakdown
                                        <small class="text-muted ms-2">(Current Division: <?= $this->session->userdata('user')->division_name ?? 'N/A' ?>)</small>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover" id="unitScoresTable">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th>Rank</th>
                                                    <th>Unit Name</th>
                                                    <th>Unit Lead</th>
                                                    <th>Activities</th>
                                                    <th>Target</th>
                                                    <th>Created</th>
                                                    <th>Completed</th>
                                                    <th>Execution Rate</th>
                                                    <th>Target Achievement</th>
                                                    <th>Overall Score</th>
                                                    <th>Performance</th>
                                                </tr>
                                            </thead>
                                            <tbody id="unitScoresTableBody">
                                                <!-- Will be populated by JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
        <p class="mt-2">Loading workplan data...</p>
    </div>
</div>

<?php $this->load->view('modals/edit_workplan'); ?>
<?php $this->load->view('modals/add_workplan'); ?>

<!-- Scripts -->
<script>
let latestFetchedData = [];
let executionData = [];

function show_notification(message, type) {
    Lobibox.notify(type, {
        pauseDelayOnHover: true,
        position: 'top right',
        icon: 'bx bx-check-circle',
        msg: message
    });
}

function showLoading() {
    $('#loadingOverlay').css('display', 'flex');
}

function hideLoading() {
    setTimeout(() => {
        $('#loadingOverlay').hide();
    }, 500);
}

function fetchTasks(query = '', year = '', division = '') {
    showLoading();
    $.ajax({
        url: "<?= site_url('workplan/get_workplan_ajax') ?>",
        method: "GET",
        data: { q: query, year: year, division: division },
        dataType: "json",
        success: function(data) {
            latestFetchedData = data;
            renderPaginatedTable(data);
            loadStatistics();
            hideLoading();
        },
        error: function() {
            hideLoading();
            show_notification('Error loading workplan data', 'error');
        }
    });
}

function loadStatistics() {
    $.ajax({
        url: "<?= site_url('workplan/get_statistics') ?>",
        method: "POST",
        data: {
            year: $('#yearSelect').val(),
            division: $('#divisionSelect').val(),
            '<?= $this->security->get_csrf_token_name(); ?>': '<?= $this->security->get_csrf_hash(); ?>'
        },
        dataType: "json",
        success: function(response) {
            if (response.success) {
                updateStatistics(response.data);
            } else {
                console.error('Statistics load failed:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error loading statistics:', error);
        }
    });
}

function updateStatistics(stats) {
    // Animate number counting
    animateNumber('#totalActivities', stats.total || 0);
    animateNumber('#completedActivities', stats.completed || 0);
    animateNumber('#inProgressActivities', stats.in_progress || 0);
    animateNumber('#overdueActivities', stats.overdue || 0);
    animateNumber('#executionRate', (stats.execution_rate || 0) + '%');
    animateNumber('#targetAchievement', (stats.target_achievement || 0) + '%');
    
    // Update progress bars
    const total = stats.total || 0;
    updateProgressBar('#totalProgress', total, total);
    updateProgressBar('#completedProgress', stats.completed || 0, total);
    updateProgressBar('#inProgressProgress', stats.in_progress || 0, total);
    updateProgressBar('#overdueProgress', stats.overdue || 0, total);
    updateProgressBar('#executionProgress', stats.execution_rate || 0, 100);
    updateProgressBar('#targetProgress', stats.target_achievement || 0, 100);
    
    // Add sparkle effect for high execution rates
    if ((stats.execution_rate || 0) > 80) {
        $('.stat-item.execution-rate').addClass('sparkle');
        setTimeout(() => {
            $('.stat-item.execution-rate').removeClass('sparkle');
        }, 2000);
    }
}

function animateNumber(selector, targetNumber) {
    const element = $(selector);
    const startNumber = parseInt(element.text()) || 0;
    const duration = 1000; // 1 second
    const increment = (targetNumber - startNumber) / (duration / 16); // 60fps
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

function updateProgressBar(selector, current, total) {
    const percentage = total > 0 ? (current / total) * 100 : 0;
    $(selector).css('width', percentage + '%');
}

function renderPaginatedTable(data) {
    const itemsPerPage = 50;
    let currentPage = 1;
    const totalPages = Math.ceil(data.length / itemsPerPage);

    function renderPage(page) {
        let html = '';
        const role = <?= $this->session->userdata('user')->role ?>;
        const start = (page - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageItems = data.slice(start, end);

        if (pageItems.length === 0) {
            html = `<tr><td colspan="10" class="text-center text-muted">No records found.</td></tr>`;
        } else {
            pageItems.forEach((item, index) => {
                const rowNumber = start + index + 1;
                html += `
                    <tr>
                        <td>${rowNumber}</td>
                        <td>${item.year}</td>
                        <td>${item.division_name}</td>
                        <td>${item.intermediate_outcome}</td>
                        <td>${item.broad_activity}</td>
                        <td>${item.output_indicator}</td>
                        <td>${item.cumulative_target}</td>
                        <td>${item.activity_name}</td>
                        <td>${item.has_budget == 1 ? 'Yes' : 'No'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary mb-1" onclick="edit(${item.id})">
                                <i class="fa fa-pencil-alt"></i>
                            </button>
                            ${role == 10 ? `
                            <button class="btn btn-sm btn-danger" onclick="deleteTask(${item.id})">
                                <i class="fa fa-trash"></i>
                            </button>` : ''}
                        </td>
                    </tr>`;
            });
        }

        $('#taskTableBody').html(html);
        renderPaginationControls(totalPages, page);
    }

    function renderPaginationControls(totalPages, currentPage) {
        let paginationHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            paginationHTML += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
                </li>`;
        }
        $('#paginationContainer').html(paginationHTML);
    }

    window.goToPage = function(page) {
        renderPage(page);
    };

    renderPage(currentPage);
}

function loadExecutionData() {
    $.ajax({
        url: "<?= site_url('workplan/get_execution_data') ?>",
        method: "POST",
        data: {
            year: $('#yearSelect').val(),
            division: $('#divisionSelect').val(),
            '<?= $this->security->get_csrf_token_name(); ?>': '<?= $this->security->get_csrf_hash(); ?>'
        },
        dataType: "json",
        success: function(response) {
            if (response.success) {
                executionData = response.data;
                renderExecutionTable(response.data);
            } else {
                console.error('Execution data load failed:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error loading execution data:', error);
        }
    });
}

function renderExecutionTable(data) {
    let html = '';
    if (data.length === 0) {
        html = `<tr><td colspan="7" class="text-center text-muted">No execution data found.</td></tr>`;
    } else {
        data.forEach((item, index) => {
            const executionRate = item.target > 0 ? ((item.completed / item.target) * 100).toFixed(1) : 0;
            const progressBarWidth = Math.min(executionRate, 100);
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.activity_name}</td>
                    <td>${item.target}</td>
                    <td>${item.created}</td>
                    <td>${item.completed}</td>
                    <td>${executionRate}%</td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${getProgressBarClass(executionRate)}" 
                                 role="progressbar" 
                                 style="width: ${progressBarWidth}%"
                                 aria-valuenow="${executionRate}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                ${executionRate}%
                            </div>
                        </div>
                    </td>
                </tr>`;
        });
    }
    $('#executionTableBody').html(html);
}

function getProgressBarClass(rate) {
    if (rate >= 100) return 'bg-success';
    if (rate >= 75) return 'bg-info';
    if (rate >= 50) return 'bg-warning';
    return 'bg-danger';
}

function loadUnitScores() {
    $.ajax({
        url: "<?= site_url('workplan/get_unit_scores') ?>",
        method: "POST",
        data: {
            year: $('#yearSelect').val(),
            division: $('#divisionSelect').val(),
            '<?= $this->security->get_csrf_token_name(); ?>': '<?= $this->security->get_csrf_hash(); ?>'
        },
        dataType: "json",
        success: function(response) {
            if (response.success) {
                renderUnitScoresTable(response.data);
            } else {
                console.error('Unit scores load failed:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error loading unit scores:', error);
        }
    });
}

function renderUnitScoresTable(data) {
    let html = '';
    if (data.length === 0) {
        html = `<tr><td colspan="11" class="text-center text-muted">No unit performance data found.</td></tr>`;
    } else {
        data.forEach((unit, index) => {
            const rank = index + 1;
            const performanceBadge = getPerformanceBadge(unit.overall_score);
            const rankIcon = getRankIcon(rank);
            
            html += `
                <tr>
                    <td>
                        <span class="badge ${getRankBadgeClass(rank)}">
                            ${rankIcon} ${rank}
                        </span>
                    </td>
                    <td><strong>${unit.unit_name}</strong></td>
                    <td>${unit.unit_lead}</td>
                    <td><span class="badge bg-primary">${unit.total_activities}</span></td>
                    <td>${unit.total_target}</td>
                    <td>${unit.sub_activities_created}</td>
                    <td>${unit.sub_activities_completed}</td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${getProgressBarClass(unit.execution_rate)}" 
                                 role="progressbar" 
                                 style="width: ${Math.min(unit.execution_rate, 100)}%"
                                 aria-valuenow="${unit.execution_rate}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                ${unit.execution_rate}%
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar ${getProgressBarClass(unit.target_achievement)}" 
                                 role="progressbar" 
                                 style="width: ${Math.min(unit.target_achievement, 100)}%"
                                 aria-valuenow="${unit.target_achievement}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                ${unit.target_achievement}%
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge ${getScoreBadgeClass(unit.overall_score)} fs-6">
                            ${unit.overall_score}%
                        </span>
                    </td>
                    <td>${performanceBadge}</td>
                </tr>`;
        });
    }
    $('#unitScoresTableBody').html(html);
}

function getRankIcon(rank) {
    switch(rank) {
        case 1: return '<i class="fa fa-trophy text-warning"></i>';
        case 2: return '<i class="fa fa-medal text-secondary"></i>';
        case 3: return '<i class="fa fa-award text-warning"></i>';
        default: return '<i class="fa fa-star text-muted"></i>';
    }
}

function getRankBadgeClass(rank) {
    switch(rank) {
        case 1: return 'bg-warning text-dark';
        case 2: return 'bg-secondary text-white';
        case 3: return 'bg-warning text-dark';
        default: return 'bg-light text-dark';
    }
}

function getScoreBadgeClass(score) {
    if (score >= 90) return 'bg-success';
    if (score >= 80) return 'bg-info';
    if (score >= 70) return 'bg-warning';
    if (score >= 60) return 'bg-warning text-dark';
    return 'bg-danger';
}

function getPerformanceBadge(score) {
    if (score >= 90) return '<span class="badge bg-success"><i class="fa fa-star me-1"></i>Excellent</span>';
    if (score >= 80) return '<span class="badge bg-info"><i class="fa fa-thumbs-up me-1"></i>Very Good</span>';
    if (score >= 70) return '<span class="badge bg-warning"><i class="fa fa-check me-1"></i>Good</span>';
    if (score >= 60) return '<span class="badge bg-warning text-dark"><i class="fa fa-exclamation me-1"></i>Fair</span>';
    return '<span class="badge bg-danger"><i class="fa fa-times me-1"></i>Needs Improvement</span>';
}

function deleteTask(id) {
    if (confirm('Are you sure you want to delete this activity?')) {
        $.get('<?= site_url('workplan/delete/') ?>' + id, function() {
            fetchTasks($('#searchBox').val(), $('#yearSelect').val(), $('#divisionSelect').val());
            show_notification('Workplan activity deleted successfully', 'success');
        });
    }
}

function edit(id) {
    $.get('<?= site_url("workplan/get_workplan_by_id/") ?>' + id, function(data) {
        let task = JSON.parse(data);
        $('#edit_id').val(task.id);
        $('#edit_intermediate_outcome').val(task.intermediate_outcome);
        $('#edit_broad_activity').val(task.broad_activity);
        $('#edit_output_indicator').val(task.output_indicator);
        $('#edit_cumulative_target').val(task.cumulative_target);
        $('#edit_activity_name').val(task.activity_name);
        $('#edit_division_id').val(task.division_id);
        $('#edit_year').val(task.year);
        $('#edit_has_budget').prop('checked', task.has_budget == 1);
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
}

// CSV Export
function convertToCSV(data) {
    if (!data.length) return '';
    const headers = Object.keys(data[0]);
    const csvRows = [headers.join(',')];

    data.forEach(row => {
        const values = headers.map(h => `"${(row[h] ?? '').toString().replace(/"/g, '""')}"`);
        csvRows.push(values.join(','));
    });

    return csvRows.join('\n');
}

function downloadCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Event Handlers
$('#exportCsvBtn').on('click', function () {
    if (!latestFetchedData.length) {
        show_notification('No data available to export.', 'warning');
        return;
    }

    const division = "<?= preg_replace('/[^a-zA-Z0-9_]/', '_', $this->session->userdata('user')->division_name ?? 'Division') ?>";
    const filename = `Workplan_Export_${division}.csv`;
    downloadCSV(latestFetchedData, filename);
});

$('#applyFilters').on('click', function() {
    fetchTasks($('#searchBox').val(), $('#yearSelect').val(), $('#divisionSelect').val());
});

$('#clearFilters').on('click', function() {
    $('#searchBox').val('');
    $('#yearSelect').val('<?= date('Y') ?>');
    $('#divisionSelect').val('');
    fetchTasks();
});

$('#exportData').on('click', function() {
    $('#exportCsvBtn').click();
});

// Tab change handlers
$('#execution-tab').on('click', function() {
    loadExecutionData();
});

$('#unit-scores-tab').on('click', function() {
    loadUnitScores();
});

// Filters
$('#searchBox, #yearSelect, #divisionSelect').on('input change', function() {
    // Debounced search
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        fetchTasks($('#searchBox').val(), $('#yearSelect').val(), $('#divisionSelect').val());
    }, 500);
});

$(document).ready(function() {
    fetchTasks();

    <?php if ($this->session->flashdata('msg')): ?>
        show_notification("<?= $this->session->flashdata('msg') ?>", "<?= $this->session->flashdata('type') ?>");
    <?php endif; ?>
});
</script>