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
//check if endterm exists

$endterm_exists = $this->per_mdl->isendterm_available($ppa_entryid);

// Get list of periods for endterm selection (passed from controller)
$periods = isset($periods) ? $periods : [];

// dd($staff_id);
//dd($endterm_exists);

?>
<div class="card">
  <div class="card-body">
  <?php $this->load->view('ppa_tabs')?>
  

                            <?php
                           
                            // Show End Term button if PPA exists, approved, user has midterm data, and end_term_start date has passed
                            $has_midterm_data = isset($has_midterm_data) ? $has_midterm_data : false;
                            $endterm_start_passed = isset($ppa_settings->end_term_start) && $today >= $ppa_settings->end_term_start;
                            if ($ppa_exists && $ppaIsapproved && $has_midterm_data && $endterm_start_passed): ?>
                               <!-- Endterm Creation Section -->
                               <div class="mb-3">
                                   <button type="button" class="btn btn-info btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#endtermModal">
                                       <i class="fa fa-plus"></i> Create Endterm
                                   </button>
                               </div>
                            <?php endif; ?>
                            
                            <!-- Endterm Period Selection Modal -->
                            <div class="modal fade" id="endtermModal" tabindex="-1" aria-labelledby="endtermModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="endtermModalLabel">Select Period for Endterm</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <?php echo form_open('performance/endterm/create_for_period', ['id' => 'endtermForm']); ?>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="endterm_period" class="form-label">Performance Period</label>
                                                    <select name="period" id="endterm_period" class="form-control" required>
                                                        <option value="">-- Select Period --</option>
                                                        <?php if (!empty($periods)): ?>
                                                            <?php 
                                                            $current_period_formatted = str_replace(' ', '-', current_period());
                                                            foreach ($periods as $period): 
                                                                $is_selected = ($period->performance_period == $current_period_formatted) ? 'selected' : '';
                                                            ?>
                                                                <option value="<?= $period->performance_period ?>" <?= $is_selected ?>>
                                                                    <?= str_replace('-', ' ', $period->performance_period) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <input type="hidden" name="staff_id" value="<?= $staff_id ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-info">Create Endterm</button>
                                            </div>
                                        <?php echo form_close(); ?>
                                    </div>
                                </div>
                            </div>
                      
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
          <?php if (!empty($endterm)): ?>
            <tr>
              <td>1</td>
              <td><?= !empty($endterm['endterm_created_at']) ? date('d M Y', strtotime($endterm['endterm_created_at'])) : '-' ?></td>
              <td><?= str_replace('-',' ',$endterm['performance_period']); ?></td>
              <td>
                <?php
                  $status = $endterm['endterm_status'] ?? 'Pending';

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
                      $supervisor_name = !empty($endterm['endterm_supervisor_1']) ? staff_name($endterm['endterm_supervisor_1']) : '';
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
                <a href="<?= base_url()?>performance/endterm/endterm_review/<?=$endterm['entry_id']?>/<?=$staff_id?>" class="btn btn-primary btn-sm">
                  <i class="fa fa-eye"></i> View Endterm
                </a>
              </td>
            </tr>
          <?php else: ?>
            <tr>
    <td colspan="5" class="text-center">
        No endterm review found for this period. Ensure your PPA was completed and approved. <br>
        Please contact HR for further support if needed.
    </td>
</tr>
  <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

