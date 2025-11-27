<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>All Staff Endterm Entries</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #aaa; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        .badge { display: inline-block; padding: 2px 6px; font-size: 10px; border-radius: 4px; }
        .badge-primary { background-color: #007bff; color: white; }
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: black; }
        .badge-secondary { background-color: #6c757d; color: white; }
        .section-title { font-weight: bold; margin-top: 20px; margin-bottom: 10px; font-size: 14px; }
    </style>
</head>
<body>

<h2>All Staff Endterm Entries</h2>
<p><strong>Printed On:</strong> <?= date('d M Y, h:i A') ?></p>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Division</th>
            <th>Submission Date</th>
            <th>Period</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    <?php $i = 1;
    foreach ($plans as $plan): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= $plan['staff_name'] ?></td>
            <td><?= $plan['division_name'] ?? 'N/A' ?></td>
            <td><?= date('d M Y', strtotime($plan['created_at'])) ?></td>
            <td><?= str_replace('-', ' ', $plan['performance_period']) ?></td>
            <td>
                <?php
                $status = $plan['overall_status'];
                if (str_contains($status, 'Pending First')) {
                    echo '<span class="badge badge-primary">First: ' . staff_name($plan['supervisor_id']) . '</span>';
                } elseif (str_contains($status, 'Pending Second')) {
                    echo '<span class="badge badge-warning">Second: ' . staff_name($plan['supervisor2_id']) . '</span>';
                } elseif ($status === 'Returned') {
                    echo '<span class="badge badge-danger">Returned</span>';
                } elseif ($status === 'Approved') {
                    echo '<span class="badge badge-success">Approved</span>';
                } else {
                    echo '<span class="badge badge-secondary">' . $status . '</span>';
                }
                ?>
            </td>
        </tr>
        <!-- OBJECTIVES -->
        <tr>
            <td colspan="6">
                <div class="section-title">Endterm Objectives:</div>
                <?php
                // First try endterm objectives, then midterm, then original PPA
                $objectives = null;
                if (!empty($plan['endterm_objectives'])) {
                    $objectives = json_decode($plan['endterm_objectives'], true);
                } elseif (!empty($plan['midterm_objectives'])) {
                    $objectives = json_decode($plan['midterm_objectives'], true);
                } else {
                    $objectives = json_decode($plan['objectives'] ?? '[]', true);
                }
                if ($objectives):
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Objective</th>
                            <th>Timeline</th>
                            <th>Indicator</th>
                            <th>Weight (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($objectives as $key => $obj): ?>
                        <tr>
                            <td><?= $key ?></td>
                            <td><?= $obj['objective'] ?></td>
                            <td><?= $obj['timeline'] ?></td>
                            <td><?= $obj['indicator'] ?></td>
                            <td><?= $obj['weight'] ?></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <em>No objectives listed.</em>
                <?php endif ?>
            </td>
        </tr>
        <!-- TRAININGS -->
        <tr>
            <td colspan="6">
                <div class="section-title">Training Recommendations:</div>
                <p><strong>Recommended:</strong> <?= $plan['endterm_training_recommended'] ?? $plan['training_recommended'] ?></p>
                <?php if (!empty($plan['endterm_training_contributions'] ?? $plan['training_contributions'])): ?>
                    <p><strong>Contribution:</strong> <?= $plan['endterm_training_contributions'] ?? $plan['training_contributions'] ?></p>
                <?php endif ?>
                <?php if (!empty($plan['endterm_recommended_trainings_details'] ?? $plan['recommended_trainings_details'])): ?>
                    <p><strong>Details:</strong> <?= $plan['endterm_recommended_trainings_details'] ?? $plan['recommended_trainings_details'] ?></p>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>

</body>
</html>

