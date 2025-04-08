<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly Task Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h3 {
            color: #07579A;
            margin: 5px 0;
        }
        .header img {
            height: 70px;
            margin-bottom: 10px;
        }
        .info-table, .tasks-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 5px;
        }
        .info-table td strong {
            color: #07579A;
        }
        .tasks-table th, .tasks-table td {
            border: 1px solid #999;
            padding: 8px;
            text-align: left;
        }
        .tasks-table th {
            background-color: #07579A;
            color: #fff;
        }
        .no-tasks {
            text-align: center;
            color: #888;
        }
    </style>
</head>
<body>

    <div class="header">
        <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="AU CDC Logo">
        <?php if (!empty($staff)): ?>
            <h3><?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?></h3>
        <?php else: ?>
            <h3>Staff Name Not Available</h3>
        <?php endif; ?>
        <small>Weekly Task Report</small>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Division:</strong> <?= !empty($staff) ? $staff->division_name : 'N/A' ?></td>
            <td><strong>Job Title:</strong> <?= !empty($staff) ? $staff->job_name : 'N/A' ?></td>
        </tr>
        <tr>
            <td><strong>Week:</strong> <?= $week_label ?? 'N/A' ?></td>
            <td><strong>Date Range:</strong> <?= ($week_range['start'] ?? 'N/A') . ' - ' . ($week_range['end'] ?? 'N/A') ?></td>
        </tr>
    </table>

    <table class="tasks-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Activity Name</th>
                <th>Start</th>
                <th>End</th>
                <th>Comments</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($tasks)): $i = 1; foreach ($tasks as $task): ?>
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
                    <td colspan="6" class="no-tasks">No tasks found for this week.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
