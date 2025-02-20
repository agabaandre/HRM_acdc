<div class="container mt-5">
    <h2 class="mb-4">My Activities</h2>
    <table class="table table-bordered table-striped">
        <thead class="thead-dark">
            <tr>
                <th>Activity Name</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($activities as $activity): ?>
            <tr>
                <td><?php echo $activity->activity_name; ?></td>
                <td><?php echo $activity->start_date; ?></td>
                <td><?php echo $activity->end_date; ?></td>
                <td>
                    <a href="<?php echo site_url('taskplanner/submit_report/' . $activity->activity_id); ?>" class="btn btn-sm btn-primary">Submit Report</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>