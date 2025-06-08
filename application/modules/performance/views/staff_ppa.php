<div class="card">
  <div class="card-body">
  <?php $this->load->view('ppa_tabs')?>
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

         if ($plan['overall_status'] == 'Pending First Supervisor') {
          $supervisor_id = $plan['supervisor_id'];
          echo '<span class="badge bg-primary fs-6">Pending First Supervisor: ' . staff_name($supervisor_id) . '</span>';
        } elseif ($plan['overall_status'] == 'Pending Second Supervisor') {
          $supervisor_id2 = $plan['supervisor2_id'];
          echo '<span class="badge bg-primary fs-6">Pending Second Supervisor: ' . staff_name($supervisor2_id) . '</span>';
        } else {
          echo '<span class="badge bg-success fs-6">' . $plan['overall_status'] . '</span>';
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
        </tbody>
      </table>
    </div>
  </div>
</div>

