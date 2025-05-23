<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Division Weekly Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
        }
        h3 {
            color: #07579A;
            text-align: center;
            margin-bottom: 10px;
        }
        .staff-section {
            margin-bottom: 30px;
        }
        .staff-name {
            margin-bottom: 8px;
            font-weight: bold;
            border-bottom: 2px solid #fbb924;
            padding: 5px 0;
            font-size: 13px;
            color: #07579A;
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
            background-color: #119A48;
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
    </style>
</head>
<body>

    <h3>Division Weekly Report</h3>

    <div class="report-meta">
        <p>
            <strong>Week:</strong> <?= $week_label ?? 'N/A' ?>&nbsp;&nbsp;
            <strong>Date Range:</strong> <?= ($week_range['start'] ?? 'N/A') . ' - ' . ($week_range['end'] ?? 'N/A') ?>
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
                                <td><?= status_badge($task->status) ?></td>
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
        <p class="no-tasks">No data found for this division.</p>
    <?php endif; ?>

</body>
</html>
