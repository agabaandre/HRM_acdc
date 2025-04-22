<div class="container-fluid mt-4">
    <?php $this->load->view('tasks_tabs') ?>

    <!-- Toolbar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-end g-3">

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
            </div>
        </div>
    </div>

    <!-- Upload Form -->
    <?= form_open_multipart('workplan/upload_workplan', ['id' => 'uploadForm', 'class' => 'mb-4']) ?>
        <div class="input-group shadow-sm">
            <input type="file" name="file" class="form-control" required>
            <button class="btn btn-success btn-sm" type="submit">
                <i class="fa fa-upload me-1"></i> Upload Workplan
            </button>
        </div>
    <?= form_close() ?>

    <!-- Workplan Table -->
    <div class="table-responsive">
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

<?php $this->load->view('modals/edit_workplan'); ?>
<?php $this->load->view('modals/add_workplan'); ?>

<!-- Scripts -->
<script>
let latestFetchedData = [];

function show_notification(message, type) {
    Lobibox.notify(type, {
        pauseDelayOnHover: true,
        position: 'top right',
        icon: 'bx bx-check-circle',
        msg: message
    });
}

function fetchTasks(query = '', year = '') {
    $.ajax({
        url: "<?= site_url('workplan/get_workplan_ajax') ?>",
        method: "GET",
        data: { q: query, year: year },
        dataType: "json",
        success: function(data) {
            latestFetchedData = data;
            renderPaginatedTable(data);
        }
    });
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

function deleteTask(id) {
    if (confirm('Are you sure you want to delete this activity?')) {
        $.get('<?= site_url('workplan/delete/') ?>' + id, function() {
            fetchTasks($('#searchBox').val(), $('#yearSelect').val());
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

$('#exportCsvBtn').on('click', function () {
    if (!latestFetchedData.length) {
        show_notification('No data available to export.', 'warning');
        return;
    }

    const division = "<?= preg_replace('/[^a-zA-Z0-9_]/', '_', $this->session->userdata('user')->division_name ?? 'Division') ?>";
    const filename = `Workplan_Export_${division}.csv`;
    downloadCSV(latestFetchedData, filename);
});

// Filters
$('#searchBox, #yearSelect').on('input change', function() {
    fetchTasks($('#searchBox').val(), $('#yearSelect').val());
});

$(document).ready(function() {
    fetchTasks();

    <?php if ($this->session->flashdata('msg')): ?>
        show_notification("<?= $this->session->flashdata('msg') ?>", "<?= $this->session->flashdata('type') ?>");
    <?php endif; ?>
});
</script>
