<div class="container-fluid py-3 px-4">
  
<div class="card shadow-sm border-0">
    <div class="card-body">
    <h5><?=$title?></h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle table-bordered table-striped mydata" id="staffStatsTable">
          <thead class="table-light text-center">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>SAPNO</th>
              <th>Status</th>
              <th>First Supervisor</th>
              <th>Second Supervisor</th>
              <th>Employee Consent</th>
              <?php if ($type === 'with_pdp'): ?>
                <th>Recommended Trainings</th>
              <?php endif; ?>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $counter = 1; ?>
            <?php foreach ($staff_list as $staff): ?>
              <tr>
                <td class="text-center"><?= $counter++ ?></td>
                <td><?= $staff->fname . ' ' . $staff->lname ?></td>
                <td><?= $staff->SAPNO ?></td>
                <td><?= $staff->status ?></td>
                <td class="text-center">
                  <?php
                  $first_sup_status = $staff->first_supervisor_status ?? 'N/A';
                  $badge_class = 'bg-secondary';
                  $status_text = $first_sup_status;
                  
                  if ($first_sup_status === 'Approved') {
                      $badge_class = 'bg-success';
                  } elseif ($first_sup_status === 'Returned') {
                      $badge_class = 'bg-danger';
                  } elseif ($first_sup_status === 'Pending') {
                      $badge_class = 'bg-warning text-dark';
                      // Add supervisor name if available
                      if (!empty($staff->first_supervisor_name)) {
                          $status_text = 'Pending: ' . $staff->first_supervisor_name;
                      }
                  }
                  ?>
                  <span class="badge <?= $badge_class ?>" title="<?= htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') ?>"><?= $status_text ?></span>
                </td>
                <td class="text-center">
                  <?php
                  $second_sup_status = $staff->second_supervisor_status ?? 'N/A';
                  $badge_class = 'bg-secondary';
                  $status_text = $second_sup_status;
                  
                  if ($second_sup_status === 'Approved') {
                      $badge_class = 'bg-success';
                  } elseif ($second_sup_status === 'Returned') {
                      $badge_class = 'bg-danger';
                  } elseif ($second_sup_status === 'Pending') {
                      $badge_class = 'bg-warning text-dark';
                      // Add supervisor name if available
                      if (!empty($staff->second_supervisor_name)) {
                          $status_text = 'Pending: ' . $staff->second_supervisor_name;
                      }
                  } elseif ($second_sup_status === 'N/A') {
                      $badge_class = 'bg-light text-dark';
                  }
                  ?>
                  <span class="badge <?= $badge_class ?>" title="<?= htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') ?>"><?= $status_text ?></span>
                </td>
                <td class="text-center">
                  <?php
                  $consent_status = $staff->employee_consent ?? 'N/A';
                  $badge_class = 'bg-secondary';
                  if ($consent_status === 'Consented') {
                      $badge_class = 'bg-success';
                  } elseif ($consent_status === 'Pending') {
                      $badge_class = 'bg-warning text-dark';
                  }
                  ?>
                  <span class="badge <?= $badge_class ?>"><?= $consent_status ?></span>
                </td>
                <?php if ($type === 'with_pdp'): ?>
                  <td>
                    <?php if (!empty($staff->training_skills)): ?>
                      <ul class="mb-0">
                        <?php foreach ($staff->training_skills as $skill): ?>
                          <li><?= htmlspecialchars($skill, ENT_QUOTES, 'UTF-8') ?>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      <span class="text-muted">None</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td class="text-center">
                  <?php if (!empty($staff->entry_id)): ?>
                    <a href="<?= base_url('performance/endterm/endterm_review/' . $staff->entry_id . '/' . $staff->staff_id) ?>"
                       class="btn btn-sm btn-outline-primary" target="_blank">
                      <i class="fa fa-eye"></i> View Endterm
                    </a>
                    
                    
                  


                  <?php else: ?>
                    <span class="text-muted">No Endterm</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
