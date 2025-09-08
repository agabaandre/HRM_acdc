<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bx bx-edit me-2"></i>Generate Division Short Names
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($divisions_without_short_names) && !empty($divisions_without_short_names)): ?>
                        <div class="alert alert-info">
                            <h6><i class="bx bx-info-circle me-2"></i>Found <?= count($divisions_without_short_names) ?> divisions without short names</h6>
                            <p class="mb-0">Click "Preview Short Names" to see what short names will be generated, then click "Generate Short Names" to update the database.</p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <button type="button" class="btn btn-info" id="previewBtn">
                                    <i class="bx bx-show me-1"></i>Preview Short Names
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <form method="POST" style="display: inline;">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to generate short names for all divisions? This action cannot be undone.')">
                                        <i class="bx bx-check me-1"></i>Generate Short Names
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Preview Table -->
                        <div id="previewTable" style="display: none;">
                            <h6 class="mb-3">Preview of Generated Short Names:</h6>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Division Name</th>
                                            <th>Proposed Short Name</th>
                                        </tr>
                                    </thead>
                                    <tbody id="previewTableBody">
                                        <!-- Data will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Current Divisions Table -->
                        <h6 class="mb-3">Divisions Without Short Names:</h6>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Division Name</th>
                                        <th>Current Short Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($divisions_without_short_names as $division): ?>
                                        <tr>
                                            <td><?= $division->id ?></td>
                                            <td><?= $division->division_name ?></td>
                                            <td>
                                                <span class="badge bg-warning">Not Set</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <h6><i class="bx bx-check-circle me-2"></i>All divisions have short names!</h6>
                            <p class="mb-0">No divisions need short name generation.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#previewBtn').click(function() {
        var $btn = $(this);
        var $table = $('#previewTable');
        var $tableBody = $('#previewTableBody');
        
        $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i>Loading...');
        
        $.ajax({
            url: '<?= base_url('settings/preview_short_names') ?>',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                $tableBody.empty();
                
                if (data.length > 0) {
                    $.each(data, function(index, division) {
                        var row = '<tr>' +
                            '<td>' + division.id + '</td>' +
                            '<td>' + division.name + '</td>' +
                            '<td><span class="badge bg-primary">' + division.proposed_short_name + '</span></td>' +
                            '</tr>';
                        $tableBody.append(row);
                    });
                    $table.show();
                } else {
                    $tableBody.append('<tr><td colspan="3" class="text-center">No data available</td></tr>');
                    $table.show();
                }
            },
            error: function() {
                alert('Error loading preview data');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="bx bx-show me-1"></i>Preview Short Names');
            }
        });
    });
});
</script>

<style>
.card-header {
    border-bottom: none;
    font-weight: 600;
}

.card-header i {
    font-size: 1.1rem;
}

.badge {
    font-size: 0.75rem;
    padding: 0.375rem 0.75rem;
}

.table th {
    font-weight: 600;
    color: #495057;
}

.alert h6 {
    margin-bottom: 0.5rem;
}

.alert p {
    margin-bottom: 0;
}
</style>
