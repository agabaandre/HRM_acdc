<div class="container mt-4">
<?php $this->load->view('tasks_tabs')?>
    <!-- Toolbar Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row align-items-end g-3">

                <!-- Create Button -->
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

                <!-- Search -->
                <div class="col-md-4">
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
    <?= form_open_multipart('workplan/upload_workplan', [
    'id' => 'uploadForm',
    'class' => 'mb-4'
        ]) ?>

        <div class="input-group shadow-sm">
            <input type="file" name="file" class="form-control" required>
            <button class="btn btn-success btn-sm" type="submit">
                <i class="fa fa-upload me-1"></i> Upload Workplan
            </button>
        </div>
    </form>

    <!-- Workplan Table -->
    <div class="table-responsive">
           <!-- Pagination -->
    <nav style="float:left;">
        <ul class="pagination justify-content-center mt-3" id="paginationContainer"></ul>
    </nav>
        <table class="table table-bordered align-middle text-wrap">
            <thead class="table-dark text-center">
                <tr>
                    <th>Year</th>
                    <th>Division</th>
                    <th>Intermediate Outcome</th>
                    <th>Broad Activity</th>
                    <th>Output Indicator</th>
                    <th>Target</th>
                    <th>Activity Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="taskTableBody" class="text-center"></tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav style="float:left;">
        <ul class="pagination justify-content-center mt-3" id="paginationContainer"></ul>
    </nav>

</div>

<!-- Modals -->
<?php $this->load->view('modals/edit_workplan'); ?>
<?php $this->load->view('modals/add_workplan'); ?>

<script>
function show_notification(message, msgtype) {
    Lobibox.notify(msgtype, {
        pauseDelayOnHover: true,
        continueDelayOnInactiveTab: false,
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
            html = `<tr><td colspan="8" class="text-center text-muted">No records found.</td></tr>`;
        } else {
            pageItems.forEach(item => {
                html += `
                    <tr>
                        <td>${item.year}</td>
                        <td>${item.division_name}</td>
                        <td>${item.intermediate_outcome}</td>
                        <td>${item.broad_activity}</td>
                        <td>${item.output_indicator}</td>
                        <td>${item.cumulative_target}</td>
                        <td>${item.activity_name}</td>
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
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
}

// Event bindings
// Utility function to get CSRF token name and value
function getCSRFToken() {
  return {
    name: '<?= $this->security->get_csrf_token_name(); ?>',
    value: $('input[name="<?= $this->security->get_csrf_token_name(); ?>"]').val()
  };
}

// Edit form submission with CSRF
$('#editForm').on('submit', function(e) {
    e.preventDefault();

    var formData = $(this).serializeArray();
    var csrf = getCSRFToken();
    formData.push({ name: csrf.name, value: csrf.value });

    $.post("<?= site_url('workplan/update_task') ?>", formData, function(response) {
        $('#editModal').modal('hide');
        fetchTasks($('#searchBox').val(), $('#yearSelect').val());
        show_notification('Workplan activity updated successfully', 'success');

        // Optionally update token if you're using csrf_regenerate = TRUE
        if (response.new_csrf_hash) {
            $('input[name="' + csrf.name + '"]').val(response.new_csrf_hash);
        }
    });
});

// Create form submission with CSRF
$('#createForm').on('submit', function(e) {
    e.preventDefault();

    var formData = $(this).serializeArray();
    var csrf = getCSRFToken();
    formData.push({ name: csrf.name, value: csrf.value });

    $.post("<?= site_url('workplan/create_task') ?>", formData, function(response) {
        $('#createModal').modal('hide');
        $('#createForm')[0].reset();
        fetchTasks($('#searchBox').val(), $('#yearSelect').val());
        show_notification('New workplan activity added successfully', 'success');

        if (response.new_csrf_hash) {
            $('input[name="' + csrf.name + '"]').val(response.new_csrf_hash);
        }
    });
});


$('#searchBox, #yearSelect').on('input change', function() {
    fetchTasks($('#searchBox').val(), $('#yearSelect').val());
});

$(document).ready(function() {
    fetchTasks();

    <?php if ($this->session->flashdata('msg')): ?>
        show_notification("<?= $this->session->flashdata('type') ?>", "<?= $this->session->flashdata('msg') ?>");
    <?php endif; ?>
});
</script>
