<?php
// Partial view for contracts table - loaded via AJAX
$i = 1;
$offset = isset($page) ? $page * 10 : 0;
?>

<div class="table-responsive">
  <table class="table table-striped table-bordered">
    <thead>
      <tr>
        <th>#</th>
        <th>Duty Station</th>
        <th>Division</th>
        <th>Other Associated Divisions</th>
        <th>Job</th>
        <th>Acting Job</th>
        <th>First Supervisor</th>
        <th>Second Supervisor</th>
        <th>Funder</th>
        <th>Contracting Institution</th>
        <th>Grade</th>
        <th>Type</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Comment</th>
        <th>Status</th>
        <th>Option</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($contracts)): ?>
        <?php foreach($contracts as $contract): ?>
          <tr>
            <td><?= $offset + $i++ ?></td>
            <td><?= $contract->duty_station_name ?? 'N/A' ?></td>
            <td><?= $contract->division_name ?? 'N/A' ?></td>
            <td>
              <?php
              if (!empty($contract->other_associated_divisions)) {
                $divisions = json_decode($contract->other_associated_divisions, true);
                if (is_array($divisions) && !empty($divisions)) {
                  $division_names = [];
                  foreach ($divisions as $div_id) {
                    $this->db->select('division_name');
                    $this->db->from('divisions');
                    $this->db->where('division_id', $div_id);
                    $div = $this->db->get()->row();
                    if ($div) {
                      $division_names[] = $div->division_name;
                    }
                  }
                  echo implode(', ', $division_names);
                } else {
                  echo 'N/A';
                }
              } else {
                echo 'N/A';
              }
              ?>
            </td>
            <td><?= @character_limiter($contract->job_name ?? '', 15) ?></td>
            <td><?= @character_limiter($contract->job_acting ?? '', 15) ?></td>
            <td><?= @staff_name($contract->first_supervisor) ?></td>
            <td><?= @staff_name($contract->second_supervisor) ?></td>
            <td><?= $contract->funder ?? 'N/A' ?></td>
            <td><?= $contract->contracting_institution ?? 'N/A' ?></td>
            <td><?= $contract->grade ?? 'N/A' ?></td>
            <td><?= $contract->contract_type ?? 'N/A' ?></td>
            <td><?= $contract->start_date ?? 'N/A' ?></td>
            <td><?= $contract->end_date ?? 'N/A' ?></td>
            <td><?= @character_limiter($contract->comments ?? '', 20) ?></td>
            <td><?= $contract->status ?? 'N/A' ?></td>
            <td class="text text-center">
              <a class="btn btn-sm btn-outline-primary" href="#" data-bs-toggle="modal" data-bs-target="#renew_contract<?=$contract->staff_contract_id?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="17" class="text-center">No contracts found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php
// Include edit modals for each contract
if (!empty($contracts)):
  foreach($contracts as $contract):
    // Load the modal partial
    $this->load->view('partials/edit_contract_modal_inline', ['contract' => $contract, 'this_staff' => $this_staff]);
  endforeach;
endif;
?>

