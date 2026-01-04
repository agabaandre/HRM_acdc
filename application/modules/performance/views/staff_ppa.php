<div class="card">
  <div class="card-body">
  <?php $this->load->view('ppa_tabs')?>
    
    <!-- Period Filter -->
    <form method="get" class="row g-2 mb-3">
      <div class="col-md-4">
        <label for="period" class="form-label">Filter by Period:</label>
        <select name="period" id="period" class="form-control" onchange="this.form.submit()">
          <option value="">All Periods</option>
          <?php foreach ($periods as $p): ?>
            <option value="<?= str_replace('-', ' ', $p->performance_period) ?>"
              <?= isset($selected_period) && $selected_period == $p->performance_period ? 'selected' : '' ?>>
              <?= str_replace('-', ' ', $p->performance_period) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
    </form>
    
    <div class="table-responsive">
      
      <table id="ppa-table" class="table mydata table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Submission Date</th>
            <th>Period</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($plans)): ?>
            <tr>
              <td colspan="5" class="text-center">No performance plans found.</td>
            </tr>
          <?php else: ?>
          <?php $i = 1;
		 // dd($plans);
          foreach ($plans as $plan): ?>
            <tr data-status="<?= $plan['overall_status']; ?>">
              <td><?= $i++; ?></td>
              <td><?= date('d M Y', strtotime($plan['created_at'])) ?></td>
              <td><?= str_replace('-',' ',$plan['performance_period']); ?></td>
          
			  <td>
			  <?php
				 $staff_id = $plan['staff_id'];
         $status = $plan['overall_status'] ?? 'Pending';
         $badgeClass = 'bg-secondary';
         
         if ($status == 'Pending (Draft)') {
           $badgeClass = 'bg-warning text-dark';
         } elseif ($status == 'Approved') {
           $badgeClass = 'bg-success';
         } elseif ($status == 'Returned') {
           $badgeClass = 'bg-danger';
         } elseif ($status == 'Pending First Supervisor') {
           $badgeClass = 'bg-primary';
           $supervisor_id = $plan['supervisor_id'];
           echo '<span class="badge ' . $badgeClass . ' fs-6">Pending First Supervisor: ' . staff_name($supervisor_id) . '</span>';
         } elseif ($status == 'Pending Second Supervisor') {
           $badgeClass = 'bg-info';
           $supervisor_id2 = $plan['supervisor2_id'];
           echo '<span class="badge ' . $badgeClass . ' fs-6">Pending Second Supervisor: ' . staff_name($supervisor_id2) . '</span>';
         }
         
         // Only show badge if not already displayed above
         if (!in_array($status, ['Pending First Supervisor', 'Pending Second Supervisor'])) {
           echo '<span class="badge ' . $badgeClass . ' fs-6">' . htmlspecialchars($status) . '</span>';
         }
				?>
				
			</td>

        <td>
                <a href="<?php echo base_url()?>performance/view_ppa/<?=$plan['entry_id']; ?>/<?=$plan['staff_id']?>" class="btn btn-primary btn-sm " >
                  <i class="fa fa-eye"></i> Preview PPA


                  
				</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
