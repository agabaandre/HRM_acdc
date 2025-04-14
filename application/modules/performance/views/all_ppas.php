<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fa fa-list-alt text-success me-2"></i>All Staff PPA Entries</h5>
    </div>
    <div class="card-body">

        <form method="get" class="row g-2 mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" name="staff_name" placeholder="Search by Name" value="<?= $this->input->get('staff_name') ?>">
            </div>
            <div class="col-md-3">
                <select name="draft_status" class="form-control">
                    <option value="">All Status</option>
                    <option value="1" <?= $this->input->get('draft_status') === '1' ? 'selected' : '' ?>>Draft</option>
                    <option value="0" <?= $this->input->get('draft_status') === '0' ? 'selected' : '' ?>>Submitted</option>
                    <option value="2" <?= $this->input->get('draft_status') === '2' ? 'selected' : '' ?>>Approved</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="period" class="form-control">
                    <option value="">All Periods</option>
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= $p->performance_period ?>"
                            <?= $this->input->get('period') == $p->performance_period ? 'selected' : '' ?>>
                            <?= str_replace('-', ' ', $p->performance_period) ?>
                        </option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="division_id" class="form-control">
                    <option value="">All Divisions</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= $d->division_id ?>" <?= $this->input->get('division_id') == $d->division_id ? 'selected' : '' ?>><?= $d->division_name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12 text-end">
                <button class="btn btn-primary"><i class="fa fa-filter me-1"></i>Filter</button>
            </div>
        </form>
        <div class="mb-3 text-end">
        <div class="mb-3 text-end">
    <a href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" 
       class="btn btn-outline-success btn-sm">
        <i class="fa fa-file-excel me-1"></i> Export to Excel
    </a>

    <a href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['export' => 'pdf2'])) ?>" 
       class="btn btn-outline-danger btn-sm">
        <i class="fa fa-file-pdf me-1"></i> Export to PDF <small class="text-muted">(Simple)</small>
    </a>

    <a href="<?= current_url() . '?' . http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>" 
       class="btn btn-outline-danger btn-sm">
        <i class="fa fa-file-pdf me-1"></i> Export to PDF <small class="text-muted">(HR Archive)</small>
    </a>
</div>

        </div>
        <?= $links ?? '' ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Division</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1;
                    foreach ($plans as $plan): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $plan['staff_name'] ?></td>
                            <td><?= $plan['division_name'] ?? '<i class="text-muted">N/A</i>' ?></td>
                            <td><?= str_replace('-', ' ', $plan['performance_period']) ?></td>
                            <td>
                                <?php
                                $status = $plan['overall_status'];
                                if (str_contains($status, 'Pending First')) {
                                    echo '<span class="badge bg-primary">First: ' . staff_name($plan['supervisor_id']) . '</span>';
                                } elseif (str_contains($status, 'Pending Second')) {
                                    echo '<span class="badge bg-warning">Second: ' . staff_name($plan['supervisor2_id']) . '</span>';
                                } elseif ($status === 'Returned') {
                                    echo '<span class="badge bg-danger">Returned</span>';
                                } elseif ($status === 'Approved') {
                                    echo '<span class="badge bg-success">Approved</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">' . $status . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?= base_url('performance/view_ppa/' . $plan['entry_id'] . '/' . $plan['staff_id']) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-eye"></i> Preview
                                </a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>