<?php 
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$staff_id = $this->session->userdata('user')->staff_id;
$current_period = str_replace(' ','-',current_period());
$ppa_entryid = md5($staff_id . '_' . str_replace(' ', '', $current_period));
$ppa_settings=ppa_settings();

//dd();
@$ppa_exists = $this->per_mdl->get_staff_plan_id($ppa_entryid);

$today = date('Y-m-d');
//check if the ppa is approved

@$ppaIsapproved = $this->per_mdl->isapproved($ppa_entryid);
//check if midterm exists

$midterm_exists = $this->per_mdl->ismidterm_available($ppa_entryid);

// dd($staff_id);
//dd($midterm_exists);

?>
<div class="card">
  <div class="card-body">
  <?php $this->load->view('ppa_tabs')?>
  

                            <?php
                           
                            // Show Mid Term button if today is within the mid_term period
                            if (
                                isset($ppa_settings->mid_term_start, $ppa_settings->mid_term_deadline) &&
                                $today >= $ppa_settings->mid_term_start &&
                                $today <= $ppa_settings->mid_term_deadline && $ppa_exists && $ppaIsapproved && !$midterm_exists
                            ): ?>
                               <a class="btn btn-primary btn-sm mb-2" href="<?= base_url("performance/midterm/midterm_review/{$ppa_entryid}/" . $this->session->userdata('user')->staff_id) ?>"><i class="fa fa-plus"></i>Create Midterm</a>
                            <?php endif; ?>
                      
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
          <?php if (!empty($midterm)): ?>
            <tr>
              <td>1</td>
              <td><?= !empty($midterm['midterm_created_at']) ? date('d M Y', strtotime($midterm['midterm_created_at'])) : '-' ?></td>
              <td><?= str_replace('-',' ',$midterm['performance_period']); ?></td>
              <td>
                <?php
                  $status = $midterm['midterm_status'] ?? 'Pending';

                  // Determine badge class based on status
                  $badgeClass = 'bg-secondary';
                  if ($status == 'Draft') {
                      $badgeClass = 'bg-warning text-dark';
                  } elseif ($status == 'Approved') {
                      $badgeClass = 'bg-success';
                  } elseif ($status == 'Returned') {
                      $badgeClass = 'bg-danger';
                  } elseif ($status == 'Pending' || $status == 'Pending Supervisor') {
                      $badgeClass = 'bg-primary';
                  }

                  // Display status text
                  if ($status == 'Pending' || $status == 'Pending Supervisor') {
                      // Show supervisor name only when pending
                      $supervisor_name = !empty($midterm['midterm_supervisor_1']) ? staff_name($midterm['midterm_supervisor_1']) : '';
                      echo '<span class="badge ' . $badgeClass . '">Pending First Supervisor' . ($supervisor_name ? ': ' . $supervisor_name : '') . '</span>';
                  } elseif ($status == 'Draft') {
                      echo '<span class="badge ' . $badgeClass . '">Draft</span>';
                  } elseif ($status == 'Approved') {
                      echo '<span class="badge ' . $badgeClass . '">Approved</span>';
                  } elseif ($status == 'Returned') {
                      echo '<span class="badge ' . $badgeClass . '">Returned</span>';
                  } else {
                      echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($status) . '</span>';
                  }
                ?>

                
              </td>
              <td>
                <a href="<?= base_url()?>performance/midterm/midterm_review/<?=$midterm['entry_id']?>" class="btn btn-primary btn-sm">
                  <i class="fa fa-eye"></i> View Midterm
                </a>
              </td>
            </tr>
          <?php else: ?>
            <tr>
    <td colspan="5" class="text-center">
        No midterm review found for this period. Ensure your PPA was completed and approved. <br>
        Please contact HR for further support if needed.
    </td>
</tr>
  <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

