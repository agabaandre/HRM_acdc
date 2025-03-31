<div class="card">
    <div class="card-body">

        <div class="table-responsive">
            <table id="approved-table" class="table mydata table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Staff</th>
                        <th>Period</th>
                        <th>Approval Date</th>
                        <th>Comments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1;
                    foreach ($plans as $plan): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= $plan['staff_name'] ?></td>
                            <td><?= str_replace('-',' ',$plan['performance_period']); ?></td>
                            <td><?= date('d M Y', strtotime($plan['approval_date'])) ?></td>
                            <td><?= $plan['comments'] ?></td>
                            <td>
                                <a href="<?= base_url('performance/view_ppa/' . $plan['entry_id']).'/'.$plan['staff_id']; ?>"
                                    class="btn btn-sm btn-primary">
                                    <i class="fa fa-eye"></i> View PPA
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>
    </div>
</div>