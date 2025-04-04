<?php
// performance/views/print_ppa_view.php
?>
<html>
<head>
  <style>
    body { font-family: serif; font-size: 12px; margin: 10px; }
    .header img { width: 150px; }
    .section-title { font-size: 14px; font-weight: bold; margin-top: 20px; border-bottom: 1px solid #ccc; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    td, th { border: 1px solid #ccc; padding: 3px; text-align: left; }
    .no-border td { border: none; }
    .objective-table th small { display: block; font-weight: normal !important; font-style: italic !important; font-size: 10px; color: #555; }
    .page-break { page-break-before: always; }
    small{
      font-weight: normal !important;
    }
  </style>
</head>
<body>
<div style="width: 100%; text-align: center; padding-bottom: 5px;">
<div style="width: 100%; padding-bottom: 5px;">
<div style="width: 100%; padding: 10px 0;">
  <!-- Top Row: Logo and Tagline -->
  <div style="display:flex; justify-content: space-between; align-items: center;">

    <!-- Left: Logo -->
    <div style="width: 60%; text-align: left; float:left;">
      <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="Africa CDC Logo" style="height: 80px;">
    </div>

    <!-- Right: Tagline -->
    <div style="text-align: right; width: 35%; float:right; margin-top:10px;">
      <span style="font-size: 14px; color: #911C39;">Safeguarding Africa’s Health</span>
    </div>

  </div>

  <!-- Centered Title -->
  <div style="text-align: center; margin-top: 5px;">
    <h3 style="margin: 0; font-weight: bold;">PERFORMANCE PLANNING AGREEMENT</h3>
  </div>
</div>


</div>




  
  <table class="form-table table-bordered">
  <thead>
    <tr style="background-color: #f2f2f2;">
      <th colspan="4" style="text-align: left; font-weight: bold;">
	  A. Staff Details
      </th>
    </tr>
  </thead>
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
    <td><?php if(!empty($ppa->performance_period)){ echo str_replace('-',' ',$ppa->performance_period
		); } else { echo current_period();} ?></td>
  </tr>
  <tr>
    <td><b>First Supervisor</b></td>
    <td><?= staff_name(get_supervisor(current_contract($staff_id))->first_supervisor) ?></td>
    <td><b>Second Supervisor</b></td>
    <td><?= @staff_name(get_supervisor(current_contract($staff_id))->second_supervisor) ?></td>
  </tr>
  <tr>
    <td><b>Funder</b></td>
    <td> <?php echo $this->db->query("SELECT * FROM `funders` where funder_id=$contract->funder_id")->row()->funder;?></td>
    <td><b>Contract Type</b></td>
    <td><?php echo $this->db->query("SELECT * FROM `contract_types` where contract_type_id=$contract->contract_type_id")->row()->contract_type;?></td>
  </tr>
</table>
  <table class="objective-table">
  <tr style="background-color: #f2f2f2;">
      <td colspan="5">
	  <div style="text-align: left; font-weight: bold;"> B. Performance Objectives</div>
       <p><i>Individual objectives should be derived from the Departmental Work Plan. There must be a cascading correlation between the two.</i></p>

      </td>
    </tr>
    <thead>
      <tr>
        <th>#</th>
        <th>Objective <small><br>Statement of the result that needs to be achieved</small></th>
        <th>Timeline <small><br>Timeframe within which the result is to be achieved</small></th>
        <th>Deliverables and KPI’s <small><br>Evidence that the result has been achieved; KPIs measure effectiveness</small></th>
        <th>Weight <small><br>The total weight of all objectives should be 100%</small></th>
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

  <table width="100%" border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
  <thead>
    <tr style="background-color: #f2f2f2;">
      <th colspan="3" style="text-align: left; font-weight: bold;">
        C. Competencies
      </th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td colspan="3" style="text-align: left;"><i>
        All staff members shall be assessed against <strong>AU Values</strong> and <strong>Core and Functional Competencies</strong>; 
        in addition to these, staff with managerial responsibilities will also be rated on the <strong>Leadership Competencies</strong>.</i>
      </td>
    </tr>
    <tr>
      <td colspan="3" style="text-align: left; font-weight: bold;">
        AU Values
      </td>
    </tr>
    <tr>
      <td colspan="3" style="text-align: left;">
        Respect for Diversity and Teamwork – Think Africa Above All – Transparency and Accountability – Integrity and Impartiality – 
        Efficiency and Professionalism – Information and Knowledge Sharing
      </td>
    </tr>
    <tr style="background-color: #f2f2f2; font-weight: bold; text-align: center;">
      <td>Core</td>
      <td>Functional</td>
      <td>Leadership</td>
    </tr>
    <tr>
      <td>Building Relationships</td>
      <td>Conceptual Thinking and Problem Solving</td>
      <td>Strategic Perspective</td>
    </tr>
    <tr>
      <td>Responsibility</td>
      <td>Job Knowledge</td>
      <td>Developing Others</td>
    </tr>
    <tr>
      <td>Learning Orientation</td>
      <td>Drive for Results</td>
      <td>Driving Change</td>
    </tr>
    <tr>
      <td>Communicating with Impact</td>
      <td>Innovative and Taking Initiative</td>
      <td>Managing Risk</td>
    </tr>
  </tbody>
</table>
<div class="page-break"></div>

  <?php if (isset($ppa->training_recommended) && $ppa->training_recommended === 'Yes'): ?>
  
  <table>
  <thead>
    <tr style="background-color: #f2f2f2;">
      <th colspan="3" style="text-align: left; font-weight: bold;">
      D. Personal Development Plan
      </th>
    </tr>
  </thead>
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
  <div style="margin-top: 20px;">
  <table width="100%" border="1" cellspacing="0" cellpadding="8" style="border-collapse: collapse; font-size: 10pt;">
  <thead>
    <tr style="background-color: #f2f2f2;">
      <th colspan="3" style="text-align: left; font-weight: bold;">
	    E. Staff and Supervisor Sign Off
      </th>
    </tr>
  </thead>
    <tr style="background-color: #f2f2f2; font-weight: bold;">
      <td width="50%" style="text-align: center;">Staff</td>
      <td width="50%" style="text-align: center;">Supervisor</td>
    </tr>
    <tr>
      <td>
        I hereby confirm that this PPA has been developed in consultation with my supervisor and that it is aligned with the departmental objectives.<br><br>
        I fully understand my performance objectives and what I am expected to deliver during this performance period.<br><br>
        I am also aware of the competencies that I will be assessed on for the same period.
      </td>
      <td>
        I hereby confirm that this PPA has been developed in consultation with the staff member and that it is aligned with the directorate/division objectives. The staff fully understands what is expected of them during the performance period and is also aware of the competencies that they will be assessed against.<br><br>
        I commit to providing supervision on the overall work of the staff member throughout the performance period to ensure the achievement of targeted results; and to providing ongoing feedback and raising and discussing with him/her areas requiring performance improvement, where applicable.
      </td>
    </tr>
    <tr>
      <td>
        <?php if (!empty(staff_details($ppa->staff_id)->signature)): ?>
          <img src="<?= base_url('uploads/staff/signature/' . staff_details($ppa->staff_id)->signature) ?>" style="width: 100px; height: 80px; text-decoration:underline;"><br>
        <?php else: ?>
          <p style="text-decoration:underline;"><?=staff_details($staff_id)->title.' '.staff_details($staff_id)->lname;?></p><br>
        <?php endif; ?>
    
       <b> Staff Signature</b>
      </td>
      <td>
        <?php if (!empty(staff_details($ppa->supervisor_id)->signature)): ?>
          <img src="<?= base_url('uploads/staff/signature/' . staff_details($ppa->supervisor_id)->signature) ?>" style="width: 100px; height: 80px; text-decoration:underline;"><br>
        <?php else: ?>
          <p style="text-decoration:underline;"><?=staff_details($ppa->supervisor_id)->title.' '.staff_details($ppa->supervisor_id)->lname;?></p><br>
        <?php endif; ?>
  
        <b>Supervisor Signature</b>
      </td>
    </tr>
    <tr>
      <td>
        <?= date('d/m/Y', strtotime($ppa->created_at)) ?><br>
       <b> Date</b>
      </td>
      <td>
        <?= date('d/m/Y', strtotime(get_last_ppa_approval_action($ppa->entry_id,$ppa->supervisor_id)->created_at) ?? $ppa->created_at) ?><br>
       <b> Date</b>
      </td>
    </tr>

    <!-- Optional Second Supervisor -->
    <?php if (!empty($ppa->supervisor2_id)): ?>
      <tr style="background-color: #f2f2f2; font-weight: bold;">
        <td colspan="2" style="text-align: center;">Second Supervisor
    

        </td>

       
      </tr>
      <tr>
      <td colspan="2">
      I hereby confirm that this PPA has been developed in consultation with the staff member and that it is aligned with the directorate/division objectives. The staff fully understands what is expected of them during the performance period and is also aware of the competencies that they will be assessed against.<br><br>
      I commit to providing supervision on the overall work of the staff member throughout the performance period to ensure the achievement of targeted results; and to providing ongoing feedback and raising and discussing with him/her areas requiring performance improvement, where applicable.
      </td>
    </tr>
      <tr>
        <td colspan="2" style="text-align: left;">
          <?php if (!empty(staff_details($ppa->supervisor2_id)->signature)): ?>
            <img src="<?= base_url('uploads/staff/signature/' . staff_details($ppa->supervisor2_id)->signature) ?>" style="width: 100px; height: 80px; text-decoration:underline;"><br>
          <?php else: ?>
            <p style="text-decoration:underline;"><?=staff_details($ppa->supervisor2_id)->title.' '.staff_details($ppa->supervisor2_id)->lname;?></p><br>
          <?php endif; ?>
       
          <b>Supervisor Signature</b>
        </td>
      </tr>
      <tr>
        <td colspan="2">
        <?= date('d/m/Y', strtotime(get_last_ppa_approval_action($ppa->entry_id,$ppa->supervisor2_id)->created_at) ?? $ppa->created_at) ?><br>
          <b>Date</b>
        </td>
      </tr>
    <?php endif; ?>
  </table>
</div>

<?php if ($this->uri->segment(5)==1){?>
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
  <?php }?>

</body>
</html>
