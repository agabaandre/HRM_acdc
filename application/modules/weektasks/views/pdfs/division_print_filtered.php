<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Filtered Division Task Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
        }
        h3 {
            color: rgba(52, 143, 65, 1);
            text-align: center;
            margin-bottom: 10px;
        }
        .staff-section {
            margin-bottom: 30px;
        }
        .staff-name {
            margin-bottom: 8px;
            font-weight: bold;
            border-bottom: 2px solid rgba(52, 143, 65, 1);
            padding: 5px 0;
            font-size: 13px;
            color: rgba(52, 143, 65, 1);
        }
        .tasks-table {
            width: 100%;
            border-collapse: collapse;
        }
        .tasks-table th, .tasks-table td {
            border: 1px solid #ddd;
            padding: 6px;
        }
        .tasks-table th {
            background-color: rgba(52, 143, 65, 1);
            color: white;
            text-align: left;
        }
        .no-tasks {
            color: #888;
            font-style: italic;
            text-align: center;
        }
        .report-meta {
            margin-bottom: 20px;
            text-align: center;
        }
        .report-meta strong {
            color: #333;
        }
        .filter-summary {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .filter-summary strong {
            color: rgba(52, 143, 65, 1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header img {
            height: 70px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="AU CDC Logo" style="height:100px;">
        <h3><?= $report_title ?? 'Filtered Division Task Report' ?></h3>
        <?php if (!empty($division)): ?>
            <small><?= $division->division_name ?></small>
        <?php endif; ?>
    </div>

    <?php if (!empty($filter_summary)): ?>
        <div class="filter-summary">
            <strong>Applied Filters:</strong> <?= $filter_summary ?>
        </div>
    <?php endif; ?>

    <div class="report-meta">
        <p>
            <strong>Report Generated:</strong> <?= date('M d, Y H:i') ?>&nbsp;&nbsp;
            <strong>Total Staff with Tasks:</strong> <?= count($division_tasks ?? []) ?>
        </p>
    </div>

    <?php if (!empty($division_tasks)): ?>
        <?php foreach ($division_tasks as $staff => $staff_tasks): ?>
            <div class="staff-section">
                <div class="staff-name"><?= $staff ?></div>
                <table class="tasks-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Activity</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Comments</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($staff_tasks)): $i = 1; foreach ($staff_tasks as $task): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= $task->activity_name ?></td>
                                <td><?= $task->start_date ?></td>
                                <td><?= $task->end_date ?></td>
                                <td><?= $task->comments ?></td>
                                <td><?= get_status_text($task->status) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="6" class="no-tasks">No tasks recorded for this staff member.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-tasks">No data found matching the applied filters.</p>
    <?php endif; ?>

</body>
</html>
