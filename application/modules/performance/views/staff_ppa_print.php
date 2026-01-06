<?php
// performance/views/print_ppa_view.php
?>
<html>
<head>
  <style>
    * { box-sizing: border-box; }
    html, body { 
      margin: 0; 
      padding: 0; 
      color: #0f172a; 
    }
    
    body {
      font-size: 14px;
      font-family: "freesans", arial, sans-serif;
      background: #FFFFFF;
      margin: 40px;
      line-height: 1.8 !important;
      letter-spacing: 0.02em;
      word-spacing: 0.08em;
      margin-bottom: 1.2em;
    }

    .header img {
      width: 150px;
    }

    .section-title {
      font-size: 16px;
      font-weight: bold;
      margin-top: 25px;
      margin-bottom: 12px;
      border-bottom: 2px solid #e2e8f0;
      padding-bottom: 8px;
      color: #100f0f;
      letter-spacing: 0.3px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      background: #fff;
    }

    td, th {
      border: 1px solid #e2e8f0;
      padding: 10px;
      text-align: left;
      vertical-align: top;
    }

    th {
      background-color: #f9fafb;
      font-weight: 600;
      color: #0f172a;
    }

    .no-border td {
      border: none;
    }

    .objective-table th small {
      display: block;
      font-weight: normal !important;
      font-style: italic !important;
      font-size: 12px;
      color: #64748b;
      margin-top: 4px;
    }

    .objective-table td {
      word-wrap: break-word;
      word-break: break-word;
      overflow-wrap: break-word;
      white-space: normal;
      vertical-align: top;
      font-size: 16px !important;
      line-height: 1.6 !important;
    }

    .objective-table td br {
      line-height: 1.4;
    }

    .page-break {
      page-break-before: always;
    }

    /* Ensure objectives are readable in print */
    @media print {
      .objective-table td {
        font-size: 16px !important;
        line-height: 1.6 !important;
        padding: 12px !important;
      }
      
      .objective-table th {
        font-size: 14px !important;
        padding: 12px !important;
      }
    }

    small {
      font-weight: normal !important;
      font-size: 12px;
      color: #64748b;
    }

    b, strong {
      font-weight: 600;
      color: #0f172a;
    }

    .muted {
      color: #64748b;
      font-size: 12px;
    }
  </style>
</head>
<body>
<div style="width: 100%; text-align: center; padding-bottom: 15px; margin-bottom: 20px;">
<div style="width: 100%; padding-bottom: 10px;">
<div style="width: 100%; padding: 15px 0;">
  <!-- Top Row: Logo and Tagline -->
  <div style="display:flex; justify-content: space-between; align-items: center;">

    <!-- Left: Logo -->
    <div style="width: 60%; text-align: left; float:left;">
      <img src="<?= base_url('assets/images/AU_CDC_Logo-800.png') ?>" alt="Africa CDC Logo" style="height: 80px;">
    </div>

    <!-- Right: Tagline -->
    <div style="text-align: right; width: 35%; float:right; margin-top:10px;">
      <span style="font-size: 14px; color: #911C39; font-weight: 500; letter-spacing: 0.3px;">Safeguarding Africa's Health</span>
    </div>

  </div>

  <!-- Centered Title -->
  <div style="text-align: center; margin-top: 12px;">
    <h3 style="margin: 0; font-weight: bold; font-size: 18px; color: #100f0f; letter-spacing: 0.5px;">PERFORMANCE PLANNING AGREEMENT</h3>
  </div>
</div>


</div>




  
  <table class="form-table table-bordered">
  <thead>
    <tr style="background-color: #f9fafb;">
      <th colspan="4" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;">
	  <strong>A. Staff Details</strong>
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
    <td><?php  echo str_replace('-',' ',$ppa->performance_period); ?></td>
  </tr>
  <tr>
    <td><b>First Supervisor</b></td>
    <td><?= @staff_name($contract->first_supervisor) ?></td>
    <td><b>Second Supervisor</b></td>
    <td><?= @staff_name($contract->second_supervisor)  ?></td>
  </tr>
  <tr>
    <td><b>Funder</b></td>
    <td> <?php echo $this->db->query("SELECT * FROM `funders` where funder_id=$contract->funder_id")->row()->funder;?></td>
    <td><b>Contract Type</b></td>
    <td><?php echo $this->db->query("SELECT * FROM `contract_types` where contract_type_id=$contract->contract_type_id")->row()->contract_type;?></td>
  </tr>
</table>
  <table class="objective-table">
  <tr style="background-color: #f9fafb;">
      <td colspan="5" style="padding: 12px;">
	  <div style="text-align: left; font-weight: bold; color: #0f172a; margin-bottom: 6px;"><strong>B. Performance Objectives</strong></div>
       <p style="margin: 0; font-style: italic; color: #64748b; font-size: 12px;">Individual objectives should be derived from the Departmental Work Plan. There must be a cascading correlation between the two.</p>

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
      <tr style="background-color: #f9fafb;">
      <th colspan="3" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;">
        <strong>C. Competencies</strong>
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
      <td colspan="3" style="text-align: left; font-weight: 600; color: #0f172a; padding: 8px;">
        AU Values
      </td>
    </tr>
    <tr>
      <td colspan="3" style="text-align: left;">
        Respect for Diversity and Teamwork – Think Africa Above All – Transparency and Accountability – Integrity and Impartiality – 
        Efficiency and Professionalism – Information and Knowledge Sharing
      </td>
    </tr>
    <tr style="background-color: #f9fafb; font-weight: 600; text-align: center; color: #0f172a;">
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
      <tr style="background-color: #f9fafb;">
      <th colspan="3" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;">
      <strong>D. Personal Development Plan</strong>
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
  <table width="100%" border="1" cellspacing="0" cellpadding="8" style="border-collapse: collapse;">
   <thead>
      <tr style="background-color: #f9fafb;">
      <th colspan="3" style="text-align: left; font-weight: bold; color: #0f172a; padding: 12px;">
	    <strong>E. Staff and Supervisor Sign Off</strong>
      </th>
    </tr>
  </thead>
    <tr style="background-color: #f9fafb; font-weight: 600;">
      <td width="50%" style="text-align: center; color: #0f172a;">Staff</td>
      <td width="50%" style="text-align: center; color: #0f172a;">Supervisor</td>
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
      <tr style="background-color: #f9fafb; font-weight: 600; color: #0f172a;">
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

<?php if ($this->uri->segment(6)==1){?>
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
