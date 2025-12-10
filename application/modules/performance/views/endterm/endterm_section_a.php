<h4 class="mt-4">A. Personal Details</h4>
<div class="table-responsive">
  <table class="table table-bordered">
    <tr>
      <th>Name</th>
      <td><?= $contract->fname . ' ' . $contract->lname ?></td>
      <th>Personnel Number (SAP NO)</th>
      <td><?= $contract->SAPNO ?></td>
    </tr>
    <tr>
      <th>Position</th>
      <td><?= $contract->job_name ?></td>
      <th>In this Position Since</th>
      <td><?= $contract->initiation_date ?></td>
    </tr>
    <tr>
      <th>Directorate/Department</th>
      <td><?= acdc_division($contract->division_id) ?></td>
      <th>Performance Period</th>
      <td><?= !empty($ppa->performance_period) ? $ppa->performance_period : current_period(); ?></td>
    </tr>
    <tr>
      <th>Direct Supervisor</th>
      <td>
        <?php if (!empty($isReturnedForResubmit) && empty($endreadonly)): ?>
          <!-- Editable dropdown when resubmitting after return -->
          <?php 
          // Use existing endterm supervisor if set, otherwise use contract supervisor
          $current_supervisor_1 = !empty($ppa->endterm_supervisor_1) ? $ppa->endterm_supervisor_1 : $contract->first_supervisor;
          $supervisor_lists = Modules::run('lists/supervisor');
          ?>
          <select class="form-control" name="supervisor_id" id="supervisor_id" required>
            <option value="">Select First Supervisor</option>
            <?php foreach ($supervisor_lists as $list): ?>
              <option value="<?= $list->staff_id ?>" <?= ($list->staff_id == $current_supervisor_1) ? 'selected' : '' ?>>
                <?= $list->lname . ' ' . $list->fname ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">You can update the supervisor if it was incorrect</small>
        <?php else: ?>
          <!-- Read-only display -->
          <?= staff_name(!empty($ppa->endterm_supervisor_1) ? $ppa->endterm_supervisor_1 : $contract->first_supervisor) ?>
          <input type="hidden" name="supervisor_id" value="<?= !empty($ppa->endterm_supervisor_1) ? $ppa->endterm_supervisor_1 : $contract->first_supervisor ?>">
        <?php endif; ?>
      </td>
      <th>Second Supervisor</th>
      <td>
        <?php if (!empty($isReturnedForResubmit) && empty($endreadonly)): ?>
          <!-- Editable dropdown when resubmitting after return -->
          <?php 
          // Use existing endterm supervisor if set, otherwise use contract supervisor
          $current_supervisor_2 = !empty($ppa->endterm_supervisor_2) ? $ppa->endterm_supervisor_2 : $contract->second_supervisor;
          if (empty($supervisor_lists)) {
            $supervisor_lists = Modules::run('lists/supervisor');
          }
          ?>
          <select class="form-control" name="supervisor2_id" id="supervisor2_id">
            <option value="">Select Second Supervisor (Optional)</option>
            <?php foreach ($supervisor_lists as $list): ?>
              <option value="<?= $list->staff_id ?>" <?= ($list->staff_id == $current_supervisor_2) ? 'selected' : '' ?>>
                <?= $list->lname . ' ' . $list->fname ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">You can update the supervisor if it was incorrect</small>
        <?php else: ?>
          <!-- Read-only display -->
          <?php 
          $second_supervisor = !empty($ppa->endterm_supervisor_2) ? $ppa->endterm_supervisor_2 : $contract->second_supervisor;
          echo !empty($second_supervisor) ? staff_name($second_supervisor) : 'N/A';
          ?>
          <input type="hidden" name="supervisor2_id" value="<?= !empty($ppa->endterm_supervisor_2) ? $ppa->endterm_supervisor_2 : (!empty($contract->second_supervisor) ? $contract->second_supervisor : '') ?>">
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th>Funder</th>
      <td>
        <?php
        echo $this->db->query("SELECT funder FROM funders WHERE funder_id = $contract->funder_id")->row()->funder;
        ?>
      </td>
      <th>Contract Type</th>
      <td>
        <?php
        echo $this->db->query("SELECT contract_type FROM contract_types WHERE contract_type_id = $contract->contract_type_id")->row()->contract_type;
        ?>
      </td>
    </tr>
  </table>
</div>

