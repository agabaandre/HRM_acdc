<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
    }
    h3 {
        color: #07579A;
        text-align: center;
        margin-bottom: 10px;
    }
    .staff-section {
        margin-bottom: 25px;
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
    }
    .staff-name {
        margin-bottom: 5px;
        font-weight: bold;
        border-bottom: 1px solid #fbb924;
        padding: 5px 0;
    }
</style>

<h3>Division Weekly Report</h3>
<p><strong>Week:</strong> <?= $week_label ?> &nbsp;&nbsp; <strong>Date Range:</strong> <?= $week_range ?></p>

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
            <?php $i = 1; foreach ($staff_tasks as $task): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= $task->activity_name ?></td>
                <td><?= $task->start_date ?></td>
                <td><?= $task->end_date ?></td>
                <td><?= $task->comments ?></td>
                <td><?= status_badge($task->status) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
