<?php
// performance/views/print_ppa_view.php
?>
<html>
<head>
  <style>
    body { font-family: Arial, sans-serif; font-size: 11px; margin: 10px; }
    .header img { width: 150px; }
    .section-title { font-size: 14px; font-weight: bold; margin-top: 20px; border-bottom: 1px solid #ccc; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    td, th { border: 1px solid #ccc; padding: 6px; text-align: left; }
    .no-border td { border: none; }
    .objective-table th small { display: block; font-weight: normal; font-style: italic; font-size: 10px; color: #555; }
    .page-break { page-break-before: always; }
  </style>
</head>
<body>
  <div class="header" style="text-align: center;">
    <img src="<?= FCPATH . 'assets/images/AU_CDC_Logo-800.png' ?>" alt="AU CDC Logo" style="height:80px;">
    <h3>Performance Planning and Appraisal (PPA) Form</h3>
  </div>

  <div class="section-title">A. Staff Details</div>
  <table class="form-table table-bordered">
  <tr>
    <td><b>Name</b></td>
    <td><?= $contract->fname.' '.$contract->lname ?></td>
    <td><b>SAP NO</b></td>
    <td><?= $contract->SAPNO ?></td>
  </tr>
  <tr>
    <td><b>Position</b></td>
    <td><?= $contract->job_name ?></td>
    <td><b>In this Position Since</b></td>
    <td><?= $contract->start_date ?></td>
  </tr>
  <tr>
    <td><b>Division/Directorate</b></td>
    <td><?= acdc_division($contract->division_id) ?></td>
    <td><b>Performance Period</b></td>
    <td><?= current_period(); ?></td>
  </tr>
  <tr>
    <td><b>First Supervisor</b></td>
    <td><?= staff_name(get_supervisor(current_contract($staff_id))->first_supervisor) ?></td>
    <td><b>Second Supervisor</b></td>
    <td><?= @staff_name(get_supervisor(current_contract($staff_id))->second_supervisor) ?></td>
  </tr>
</table>

  <div class="section-title">B. Performance Objectives</div>
  <p><i>Individual objectives should be derived from the Departmental Work Plan. There must be a cascading correlation between the two.</i></p>
  <table class="objective-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Objective <small>Statement of the result that needs to be achieved</small></th>
        <th>Timeline <small>Timeframe within which the result is to be achieved</small></th>
        <th>Deliverables and KPI’s <small>Evidence that the result has been achieved; KPIs measure effectiveness</small></th>
        <th>Weight <small>The total weight of all objectives should be 100%</small></th>
      </tr>
    </thead>
    <tbody>
      <?php
      $objectives = is_string($ppa->objectives) ? json_decode($ppa->objectives, true) : (array) json_decode(json_encode($ppa->objectives), true);
      $i = 1;
      foreach ($objectives as $obj): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= $obj['objective'] ?? '' ?></td>
          <td><?= $obj['timeline'] ?? '' ?></td>
          <td><?= $obj['indicator'] ?? '' ?></td>
          <td><?= $obj['weight'] ?? '' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (isset($ppa->training_recommended) && $ppa->training_recommended === 'Yes'): ?>
  <div class="section-title">C. Personal Development Plan</div>
  <table>
    <tr>
      <td><b>Is training recommended for this staff member?</b></td>
      <td><?= $ppa->training_recommended ?></td>
    </tr>
    <tr>
      <td><b>If yes, in what subject/skill area(s) is the training recommended?</b></td>
      <td>
        <?php
        $skills_map = [];
        foreach ($skills as $skill) {
            $skills_map[$skill->id] = $skill->skill;
        }
        $selected = is_string($ppa->required_skills) ? json_decode($ppa->required_skills, true) : (array)$ppa->required_skills;
        $skills_list = array_map(fn($id) => $skills_map[$id] ?? '', $selected);
        echo implode(', ', $skills_list);
        ?>
      </td>
    </tr>
    <tr>
      <td><b>Explain how the training will contribute to the staff member’s development and the department’s work:</b></td>
      <td><?= $ppa->training_contributions ?></td>
    </tr>
    <tr>
      <td><b>Recommended courses from L&D Catalogue:</b></td>
      <td><?= $ppa->recommended_trainings ?></td>
    </tr>
    <tr>
      <td><b>Additional course details (if not in the catalogue):</b></td>
      <td><?= $ppa->recommended_trainings_details ?></td>
    </tr>
  </table>
  <?php endif; ?>

  <div class="section-title">D. Sign Off</div>
  <table>
    <tr>
      <td><b>Staff Sign-off:</b></td>
      <td><?= $ppa->staff_sign_off ? 'Yes' : 'No' ?></td>
    </tr>
    <tr>
      <td><b>Date:</b></td>
      <td><?= date('d M Y', strtotime($ppa->created_at)) ?></td>
    </tr>
  </table>

  <div class="page-break"></div>
  <div class="section-title">E. Approval Trail</div>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Role</th>
        <th>Action</th>
        <th>Date</th>
        <th>Comment</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($approval_trail as $log): 
        $logged = Modules::run('auth/contract_info', $log->staff_id);
        if ($log->staff_id == $ppa->staff_id) $role = 'Staff';
        elseif ($log->staff_id == $ppa->supervisor_id) $role = 'First Supervisor';
        elseif ($ppa->supervisor2_id && $log->staff_id == $ppa->supervisor2_id) $role = 'Second Supervisor';
        else $role = 'Other';
      ?>
      <tr>
        <td><?= $logged->fname . ' ' . $logged->lname ?></td>
        <td><?= $role ?></td>
        <td><?= $log->action ?></td>
        <td><?= date('d M Y H:i', strtotime($log->created_at)) ?></td>
        <td><?= $log->comments ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

</body>
</html>
