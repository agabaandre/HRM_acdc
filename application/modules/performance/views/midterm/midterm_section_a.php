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
        <?= staff_name(!empty($ppa->midterm_supervisor_1) ? $ppa->midterm_supervisor_1 : $contract->first_supervisor) ?>
        <?php if (!empty($ppa) && isset($ppa->draft_status) && (int)$ppa->draft_status !== 2): ?>
          <?php $this->load->view('performance/partials/change_supervisor_modal', ['ppa' => $ppa, 'type' => 'midterm']); ?>
        <?php endif; ?>
        <input type="hidden" name="supervisor_id" value="<?= !empty($ppa->midterm_supervisor_1) ? $ppa->midterm_supervisor_1 : $contract->first_supervisor ?>">
      </td>
      <th>Second Supervisor</th>
      <td>
        <?= staff_name(!empty($ppa->midterm_supervisor_2) ? $ppa->midterm_supervisor_2 : $contract->second_supervisor) ?>
        <input type="hidden" name="supervisor2_id" value="<?= !empty($ppa->midterm_supervisor_2) ? $ppa->midterm_supervisor_2 : $contract->second_supervisor ?>">
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
