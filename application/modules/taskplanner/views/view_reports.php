<div class="container mt-5">
    <h2 class="mb-4">My Reports</h2>
    <table class="table table-bordered table-striped">
        <thead class="thead-dark">
            <tr>
                <th>Activity Name</th>
                <th>Report Date</th>
                <th>Description</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reports as $report): ?>
            <tr>
                <td><?php echo $report->activity_name; ?></td>
                <td><?php echo $report->report_date; ?></td>
                <td><?php echo $report->description; ?></td>
                <td>
                    <span class="badge badge-<?php echo $report->status === 'approved' ? 'success' : ($report->status === 'rejected' ? 'danger' : 'warning'); ?>">
                        <?php echo ucfirst($report->status); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>