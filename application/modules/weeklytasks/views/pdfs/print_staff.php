<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 11px;
    }
    .header {
        text-align: center;
        margin-bottom: 20px;
    }
    h3 {
        color: #07579A;
        margin: 0;
    }
    .info-table, .tasks-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    .info-table td {
        padding: 5px;
    }
    .tasks-table th, .tasks-table td {
        border: 1px solid #ddd;
        padding: 8px;
    }
    .tasks-table th {
        background-color: #07579A;
        color: #fff;
    }
</style>

<div class="header">
    <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" height="80">
    <h3><?= $staff->title . ' ' . $staff->fname . ' ' . $staff->lname ?></h3>
    <small>Weekly Task Report</small>
</div>

<table class="info-table">
    <tr>
        <td><strong>Division:</strong> <?= $staff->division_name ?></td>
        <td><strong>Job Title:</strong> <?= $staff->job_name ?></td>
    </tr>
    <tr>
        <td><strong>Week:</strong> <?= $week_label ?></td>
        <td><strong>Date Range:</strong> <?= $week_range ?></td>
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
        <?php $i = 1; foreach ($tasks as $task): ?>
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
