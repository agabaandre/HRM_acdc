<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Filtered Combined Division Task Report</title>
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
            color: rgba(52, 143, 65, 1); 
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
            color: rgba(52, 143, 65, 1); 
        }
        .tasks-table th, .tasks-table td {
            border: 1px solid #999; 
            padding: 8px; 
            text-align: left;
        }
        .tasks-table th { 
            background-color: rgba(52, 143, 65, 1); 
            color: #fff; 
        }
        .no-tasks { 
            text-align: center; 
            color: #888; 
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
    </style>
</head>
<body>

<div class="header">
    <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="AU CDC Logo" style="height:100px;">
    <h3><?= $report_title ?? 'Filtered Combined Division Task Report' ?></h3>
    <?php if (!empty($division)): ?>
        <small><?= $division->division_name ?></small>
    <?php endif; ?>
</div>

<?php if (!empty($filter_summary)): ?>
    <div class="filter-summary">
        <strong>Applied Filters:</strong> <?= $filter_summary ?>
    </div>
<?php endif; ?>

<table class="info-table">
    <tr>
        <td><strong>Division:</strong> <?= !empty($tasks) ? $tasks[0]->division_name : 'N/A' ?></td>
        <td><strong>Report Generated:</strong> <?= date('M d, Y H:i') ?></td>
    </tr>
    <tr>
        <td><strong>Total Tasks:</strong> <?= count($tasks ?? []) ?></td>
        <td><strong>Report Type:</strong> Combined Division Tasks</td>
    </tr>
</table>

<table class="tasks-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Individual Task</th>
            <th>Sub-Activity</th>
            <th>Workplan Activity</th>
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
                <td><?= $task->parent_activity ?? '—' ?></td>
                <td><?= $task->workplan_activity ?? '—' ?></td>
                <td><?= $task->start_date ?></td>
                <td><?= $task->end_date ?></td>
                <td><?= $task->comments ?></td>
                <td><?= get_status_text($task->status) ?></td>
            </tr>
        <?php endforeach; else: ?>
            <tr>
                <td colspan="8" class="no-tasks">No tasks found matching the applied filters.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
