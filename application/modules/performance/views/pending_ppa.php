<div class="card">
  <div class="card-body">
  <?php $this->load->view('ppa_tabs')?>
    <div class="table-responsive">
      <table id="ppa-table" class="table mydata table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Submission Date</th>
            <th>Period</th>
            <th>Type</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1;
		      //dd($plans);
          foreach ($approvals as $plan): ?>
            <tr data-status="<?= isset($plan['overall_status']) ? $plan['overall_status'] : 'Pending'; ?>">
              <td><?= $i++; ?></td>
              <td><?=$plan['staff_name'] ?></td>
              <td>
                <?php if (isset($plan['approval_type']) && $plan['approval_type'] === 'midterm' && !empty($plan['midterm_created_at'])): ?>
                  <?= date('d M Y', strtotime($plan['midterm_created_at'])) ?>
                <?php else: ?>
                  <?= date('d M Y', strtotime($plan['created_at'])) ?>
                <?php endif; ?>
              </td>
              <td><?= str_replace('-',' ',$plan['performance_period']); ?></td>
              <td>
                <?php if (isset($plan['approval_type']) && $plan['approval_type'] === 'midterm'): ?>
                  <span class="badge bg-warning text-dark">Midterm</span>
                <?php else: ?>
                  <span class="badge bg-primary">PPA</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $staff_id = $plan['staff_id'];
                  $status = isset($plan['overall_status']) ? $plan['overall_status'] : 'Pending';
                  $badgeClass = 'bg-secondary';
                  $badgeText = $status;
                  if ($status == 'Pending First Supervisor') {
                    $badgeClass = 'bg-primary';
                    $badgeText = 'Pending First Supervisor: ' . staff_name($plan['supervisor_id']);
                  } elseif ($status == 'Pending Second Supervisor') {
                    $badgeClass = 'bg-purple'; // Custom class, fallback to bg-info if not defined
                    $badgeText = 'Pending Second Supervisor: ' . staff_name($plan['supervisor2_id']);
                  } elseif (stripos($status, 'pending') !== false) {
                    $badgeClass = 'bg-warning text-dark';
                  } elseif (stripos($status, 'approved') !== false) {
                    $badgeClass = 'bg-success';
                  } elseif (stripos($status, 'returned') !== false) {
                    $badgeClass = 'bg-danger';
                  }
                ?>
                <span class="badge <?= $badgeClass ?> fs-6"> <?= $badgeText ?> </span>
              </td>
              <td>
                <?php if (isset($plan['approval_type']) && $plan['approval_type'] === 'midterm'): ?>
                  <a href="<?php echo base_url()?>performance/midterm/midterm_review/<?=$plan['entry_id']; ?>/<?=$plan['staff_id']?>" class="btn btn-warning btn-sm" >
                    <i class="fa fa-eye"></i> Review Midterm
                  </a>
                <?php else: ?>
                  <a href="<?php echo base_url()?>performance/view_ppa/<?=$plan['entry_id']; ?>/<?=$plan['staff_id']?>" class="btn btn-primary btn-sm" >
                    <i class="fa fa-eye"></i> Preview PPA
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

