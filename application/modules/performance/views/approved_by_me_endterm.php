<div class="card">
    <div class="card-body">
    <?php $this->load->view('ppa_tabs')?>
        <div class="table-responsive">
            <table class="table mydata table-striped table-bordered">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Staff Name</th>
                        <th>Submission Date</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Approval Date</th>
                        <th>Comments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($plans)): $i = 1; foreach ($plans as $endterm): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= $endterm['staff_name']; ?></td>
                            <td><?= !empty($endterm['endterm_created_at']) ? date('d M Y', strtotime($endterm['endterm_created_at'])) : '-' ?></td>
                            <td><?= str_replace('-',' ',$endterm['performance_period']); ?></td>
                            <td>
                                <?php
                                    $status = $endterm['endterm_status'] ?? 'Pending';
                                    $badgeClass = 'bg-secondary';
                                    if ($status == 'Draft') $badgeClass = 'bg-warning text-dark';
                                    elseif ($status == 'Approved') $badgeClass = 'bg-success';
                                    elseif ($status == 'Returned') $badgeClass = 'bg-danger';
                                    elseif ($status == 'Pending Supervisor') $badgeClass = 'bg-primary';
                                    echo '<span class="badge '.$badgeClass.' fs-6">'.$status.'</span>';
                                ?>
                            </td>
                            <td><?= !empty($endterm['approval_date']) ? date('d M Y', strtotime($endterm['approval_date'])) : '-' ?></td>
                            <td><?= htmlspecialchars($endterm['comments'] ?? '') ?></td>
                            <td>
                                <a href="<?= base_url()?>performance/endterm/endterm_review/<?=$endterm['entry_id']?>/<?=$endterm['staff_id']?>" class="btn btn-primary btn-sm">
                                    <i class="fa fa-eye"></i> Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="8" class="text-center">No endterms approved by you found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

