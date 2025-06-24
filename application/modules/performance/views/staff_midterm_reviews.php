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
          <?php if (!empty($plans)): $i = 1; foreach ($plans as $midterm): ?>
            <tr>
              <td><?= $i++; ?></td>
              <td><?= !empty($midterm['midterm_created_at']) ? date('d M Y', strtotime($midterm['midterm_created_at'])) : '-' ?></td>
              <td><?= str_replace('-',' ',$midterm['performance_period']); ?></td>
              <td>
                <?php
                  $status = $midterm['midterm_status'] ?? 'Pending';
                  $badgeClass = 'bg-secondary';
                  $pendingText = $status;

                  if ($status == 'Draft') $badgeClass = 'bg-warning text-dark';
                  elseif ($status == 'Approved') $badgeClass = 'bg-success';
                  elseif ($status == 'Returned') $badgeClass = 'bg-danger';
                  elseif ($status == 'Pending Supervisor' || $status == 'Pending') {
                      $badgeClass = 'bg-primary';
                      $pendingSupervisor = '';
                      $sup1 = $midterm['supervisor_id'] ?? null;
                      $sup2 = $midterm['supervisor2_id'] ?? null;
                      $sup1_action = $sup1 ? $this->db->select('action')->where('entry_id', $midterm['entry_id'])->where('staff_id', $sup1)->order_by('id', 'DESC')->limit(1)->get('ppa_approval_trail_midterm')->row('action') : null;
                      $sup2_action = $sup2 ? $this->db->select('action')->where('entry_id', $midterm['entry_id'])->where('staff_id', $sup2)->order_by('id', 'DESC')->limit(1)->get('ppa_approval_trail_midterm')->row('action') : null;
                      if ($sup1 && (!$sup1_action || $sup1_action != 'Approved')) {
                          $pendingSupervisor = staff_name($sup1);
                      } elseif ($sup2 && $sup1_action == 'Approved' && (!$sup2_action || $sup2_action != 'Approved')) {
                          $pendingSupervisor = staff_name($sup2);
                      }
                      if ($pendingSupervisor) {
                          $pendingText .= ': ' . $pendingSupervisor;
                      }
                  }
                  echo '<span class="badge '.$badgeClass.' fs-6">'.$pendingText.'</span>';
                ?>
              </td>
              <td>
                <a href="<?= base_url()?>performance/midterm/midterm_review/<?=$midterm['entry_id']?>" class="btn btn-primary btn-sm">
                  <i class="fa fa-eye"></i> Review
                </a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center">No midterm reviews found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div> 