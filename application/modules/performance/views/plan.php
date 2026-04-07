<?php 
$session = $this->session->userdata('user');

if (!empty($ppa) && is_object($ppa)) {
  $readonly = ''; // Editable for creating new PPA
  $staff_id = $ppa->staff_id;
  $staff_contract_id = $ppa->staff_contract_id;
  //dd($staff_contract_id);
  $contract = Modules::run('performance/ppa_contract', $staff_contract_id);
  // HMVC used to return '' when the method returned null; still guard non-objects
  if (!is_object($contract) && !empty($ppa->staff_id)) {
    $contract = Modules::run('auth/contract_info', $ppa->staff_id);
  }
  $period_for_form = !empty($ppa->performance_period) ? $ppa->performance_period : (isset($performance_period) ? $performance_period : str_replace(' ', '-', current_period()));
} else {
  $staff_id = $session->staff_id;
  $contract = Modules::run('auth/contract_info', $staff_id);
  $staff_contract_id = is_object($contract) ? $contract->staff_contract_id : '';
  $period_for_form = isset($performance_period) ? $performance_period : str_replace(' ', '-', current_period());
}

// Ensure $contract is always an object for the rest of the view (avoid property reads on false/'')
$contract_missing = !is_object($contract);
if ($contract_missing) {
  $contract = (object) [
    'staff_contract_id' => $staff_contract_id ?? '',
    'fname' => '',
    'lname' => '',
    'SAPNO' => '',
    'job_name' => '',
    'initiation_date' => '',
    'division_id' => null,
    'first_supervisor' => null,
    'second_supervisor' => null,
    'funder_id' => null,
    'contract_type_id' => null,
  ];
}
$permissions = $session->permissions;
// End year of performance period for default timeline (e.g. January-2025-to-December-2025 -> 2025)
$period_end_year = date('Y');
if (!empty($period_for_form) && preg_match('/\d{4}/', $period_for_form, $m)) {
  $period_end_year = $m[0];
}
//dd($contract);
//dd($this->uri->segment(2));
$ppa_settings=ppa_settings();

//dd($ppa);

$readonly = '';

// Default: allow if it's a new PPA
if (!isset($ppa) || empty($ppa) || !is_object($ppa)) {
    $readonly = ''; // Editable for creating new PPA
} else {
    // Extract status
    $status = (int) @$ppa->draft_status;

    $isDraft = $status === 1;
    $isSubmitted = $status === 0;
    $isApproved = $status === 2;

    $isOwner = isset($ppa->staff_id) && $session->staff_id == $ppa->staff_id;
    $isSupervisor = in_array($session->staff_id, [(int) @$ppa->supervisor_id, (int) @$ppa->supervisor2_id]);

    // Determine readonly
    if (
        ($isApproved) || // Approved: no one can edit
        ($isSubmitted && !$isSupervisor) || // Submitted: only supervisor can edit
        ($isDraft && !$isOwner) // Draft: only owner can edit
    ) {
        $readonly = 'readonly disabled';
    }
}

/** True when logged-in user is 1st or 2nd PPA supervisor (for preview button). */
$isPpaApprover = false;
if (!empty($ppa) && is_object($ppa)) {
    $isPpaApprover = in_array((int) $session->staff_id, [
        (int) ($ppa->supervisor_id ?? 0),
        (int) ($ppa->supervisor2_id ?? 0),
    ], true);
}

@$showApprovalBtns = show_ppa_approval_action(@$ppa, @$approval_trail, $this->session->userdata('user'));
//sdd($showApprovalBtns);


$selected_skills = (!empty($ppa) && is_object($ppa) && is_string(@$ppa->required_skills)) ? json_decode($ppa->required_skills, true) : ((!empty($ppa) && is_object($ppa) && isset($ppa->required_skills)) ? $ppa->required_skills : []);
$objectives_raw = (!empty($ppa) && is_object($ppa)) ? (@$ppa->objectives ?? []) : [];


if (is_string($objectives_raw)) {
    $decoded = json_decode($objectives_raw, true);
} elseif (is_object($objectives_raw)) {
    $decoded = json_decode(json_encode($objectives_raw), true);
} elseif (is_array($objectives_raw)) {
    $decoded = $objectives_raw;
} else {
    $decoded = [];
}

$objectives = [];
foreach ($decoded as $item) {
    $objectives[] = [
        'objective' => $item['objective'] ?? '',
        'timeline' => $item['timeline'] ?? '',
        'indicator' => $item['indicator'] ?? '',
        'weight' => $item['weight'] ?? ''
    ];
}
?>

<style>
input[type="text"],
input[type="number"] {
  border: 1px solidrgb(181, 178, 178)!important; 
  padding: 17px;
  box-shadow: none !important;
  border-radius:8px;
  background-color: transparent;
}
.is-invalid {
  border: 1px solid red !important;
}

  .form-table { width: 100%; border-collapse: collapse; }
  .form-table td { padding-left: 2px; }

  td { padding:6px;}

  .objective-table th, .objective-table td { text-align: left; padding: 0px; border: 1px solid #ccc; }

  /* Fixed layout so column % and colgroup are respected (Summernote cannot stretch cells) */
  .objective-table {
    table-layout: fixed;
    width: 100%;
  }
  .objective-table td {
    vertical-align: top;
    overflow: hidden;
  }

  /* Deliverables/KPI column — keep Summernote narrow */
  .objective-table td.ppa-deliverables-cell {
    max-width: 0;
  }
  .objective-table td.ppa-deliverables-cell .ppa-summernote + .note-editor {
    max-width: 100% !important;
    width: 100% !important;
    min-width: 0 !important;
    box-sizing: border-box;
  }
  .objective-table td.ppa-deliverables-cell .note-toolbar {
    flex-wrap: wrap;
  }

  /* Other Summernote cells (objective column) */
  .objective-table td .ppa-summernote + .note-editor {
    max-width: 100%;
    box-sizing: border-box;
  }
  .objective-table td .ppa-summernote + .note-editor .note-editable {
    min-height: 120px;
  }

  /* Summernote (PPA rich text) — Arial, 14px */
  .ppa-summernote + .note-editor .note-editable,
  .ppa-summernote + .note-editor .note-editable * {
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 14px !important;
  }
  #ppaApproverPreviewModal .preview-readonly-text.ppa-html-preview {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 14px;
    white-space: normal;
    word-break: break-word;
  }
  #ppaApproverPreviewModal .preview-readonly-text.ppa-html-preview p { margin: 0 0 0.5em 0; }
</style>
<?php $this->load->view('ppa_tabs')?>
<?php //$this->load->view('performance/partials/show_mid_endbtns.php')?>

<?php /* helper approval buttons hidden per request
if($showApprovalBtns!='show'){
  echo $showApprovalBtns;
}
*/ ?>

<?php if (!empty($contract_missing)): ?>
  <div class="alert alert-warning" role="alert">
    <strong>No staff contract on file.</strong> We could not load your active contract (HR / jobs data). You cannot submit a PPA until a valid contract is linked to your profile. Please contact HR.
  </div>
<?php endif; ?>

<?php if (!empty($ppa) && is_object($ppa) && !empty($ppa->entry_id)): ?>
  <div class="mb-3">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <a href="<?= base_url('performance/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id) ?>"
         class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
        <i class="fa fa-print"></i> Print (No Trail)
      </a>
      <a href="<?= base_url('performance/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id . '/1') ?>"
         class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
        <i class="fa fa-print"></i> Print (With Trail)
      </a>
      <button type="button" class="btn btn-outline-success btn-sm" id="sharePpaBtn">
        <i class="fa fa-share-alt"></i> Share
      </button>
      <?php
        $mailSubjectPpa = trim(staff_name($ppa->staff_id) . ' PPA ' . str_replace('-', ' ', (string)($ppa->performance_period ?? '')) . ' ' . (((int)($ppa->draft_status ?? -1) === 2) ? 'Final' : 'Draft'));
        $mailBodyPpa = 'Please follow the link to view my PPA ' . ((((int)($ppa->draft_status ?? -1) === 2) ? 'Final' : 'Draft')) . ".\n\n" . base_url('performance/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id);
      ?>
      <a class="btn btn-outline-success btn-sm" id="mailPpaBtn" href="mailto:?subject=<?= rawurlencode($mailSubjectPpa) ?>&body=<?= rawurlencode($mailBodyPpa) ?>">
        <i class="fa fa-envelope"></i> Mail
      </a>
      <?php if (!empty($isPpaApprover)): ?>
      <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ppaApproverPreviewModal" id="ppaApproverPreviewBtn">
        <i class="fa fa-eye"></i> Preview
      </button>
      <?php endif; ?>
    </div>
    <small class="text-muted d-block mt-1">Shows &ldquo;DRAFT&rdquo; or &ldquo;PENDING APPROVAL&rdquo; watermark unless the PPA is fully approved.</small>
  </div>
<?php endif; ?>

<?php if (!empty($ppa) && is_object($ppa) && !empty($ppa->entry_id)): ?>
<script>
  (function() {
    const shareBtn = document.getElementById('sharePpaBtn');
    if (!shareBtn) return;

    const employeeName = <?= json_encode(trim((string) staff_name($ppa->staff_id))) ?>;
    const financialYear = <?= json_encode(str_replace('-', ' ', (string) ($ppa->performance_period ?? ''))) ?>;
    const shareUrl = <?= json_encode(base_url('performance/print_ppa/' . $ppa->entry_id . '/' . $ppa->staff_id . '/' . $ppa->staff_contract_id)) ?>;
    const isApproved = <?= ((int) (@$ppa->draft_status ?? -1) === 2) ? 'true' : 'false' ?>;
    const versionLabel = isApproved ? 'Final' : 'Draft';
    const subject = `${employeeName} PPA ${financialYear} ${versionLabel}`.trim();
    const body = `Please follow the link to view my PPA ${versionLabel}.\n\n${shareUrl}`;
    const mailtoLink = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

    shareBtn.addEventListener('click', async function () {
      if (navigator.share) {
        try {
          await navigator.share({
            title: subject,
            text: body,
            url: shareUrl
          });
          return;
        } catch (err) {
          // User cancelled share dialog or share failed; fallback to email compose.
        }
      }
      window.location.href = mailtoLink;
    });

  })();
</script>
<?php endif; ?>

<?php echo form_open_multipart(base_url('performance/save_ppa'), ['id' => 'staff_ppa']); ?>

<input type="hidden" name="staff_id" value="<?=$staff_id?>">
<input type="hidden" name="staff_contract_id" value="<?=$staff_contract_id?>">
<input type="hidden" name="performance_period" value="<?= htmlspecialchars($period_for_form ?? '') ?>">
<div id="ppaPreviewSource">
<h4>A. Staff Details</h4>
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
    <td><b>Initiatition Date</b></td>
    <td><?= $contract->initiation_date ?></td>
  </tr>
  <tr>
    <td><b>Division/Directorate</b></td>
    <td><?= acdc_division($contract->division_id) ?></td>
    <td><b>Performance Period</b></td>
    <td><?= !empty($period_for_form) ? str_replace('-', ' ', $period_for_form) : current_period() ?></td>
  </tr>
  <tr>
    <td><b>First Supervisor</b></td>
    <td colspan="1">
      <?= staff_name((!empty($ppa) && is_object($ppa) && !empty($ppa->supervisor_id)) ? $ppa->supervisor_id : $contract->first_supervisor) ?>
      <?php if (!empty($ppa) && is_object($ppa) && isset($ppa->draft_status) && (int)$ppa->draft_status !== 2): ?>
        <?php $this->load->view('performance/partials/change_supervisor_modal', ['ppa' => $ppa, 'type' => 'ppa', 'entry_id' => isset($entry_id) ? $entry_id : ($ppa->entry_id ?? '')]); ?>
      <?php endif; ?>
      <input type="hidden" name="supervisor_id"
        value="<?= (!empty($ppa) && is_object($ppa) && !empty($ppa->supervisor_id)) ? $ppa->supervisor_id : ($contract->first_supervisor ?? '') ?>">
    </td>
    <td><b>Second Supervisor</b></td>
    <td colspan="">
      <?= @staff_name((!empty($ppa) && is_object($ppa) && !empty($ppa->supervisor2_id)) ? $ppa->supervisor2_id : ($contract->second_supervisor ?? '')) ?>
        <input type="hidden" name="supervisor2_id"
        value="<?= (!empty($ppa) && is_object($ppa) && !empty($ppa->supervisor2_id)) ? $ppa->supervisor2_id : ($contract->second_supervisor ?? '') ?>">
    </td>
  </tr>
  <tr>
    <td><b>Funder</b></td>
    <td colspan="1">
     <?php
     $funder_label = '—';
     if (!empty($contract->funder_id)) {
       $fr = $this->db->query('SELECT funder FROM funders WHERE funder_id = ?', [(int) $contract->funder_id])->row();
       $funder_label = $fr ? $fr->funder : '—';
     }
     echo htmlspecialchars($funder_label);
     ?>
    </td>
    <td><b>Contract Type</b></td>
    <td colspan="1">
     <?php
     $ctype_label = '—';
     if (!empty($contract->contract_type_id)) {
       $ct = $this->db->query('SELECT contract_type FROM contract_types WHERE contract_type_id = ?', [(int) $contract->contract_type_id])->row();
       $ctype_label = $ct ? $ct->contract_type : '—';
     }
     echo htmlspecialchars($ctype_label);
     ?>
    </td>
  </tr>
</table>

<hr>

<h4>B. Performance Objectives</h4>
<small>Individual objectives should be derived from the Departmental Work Plan. There must be a cascading correlation between the two</small>
<div class="table-responsive"> 
  <table class="table objective-table table-bordered">
    <colgroup>
      <col class="ppa-col-num" style="width: 5%;">
      <col class="ppa-col-objective" style="width: 35%;">
      <col class="ppa-col-timeline" style="width: 10%;">
      <col class="ppa-col-deliverables" style="width: 35%;">
      <col class="ppa-col-weight" style="width: 5%;">
    </colgroup>
    <thead class="table-light">
      <tr>
        <th class="text-wrap">#</th>
        <th class="text-wrap">Objective<br><small class="fw-light d-block text-wrap">Statement of the result that needs to be achieved</small></th>
        <th class="text-wrap">Timeline<br><small class="fw-light d-block text-wrap">Timeframe within which the result is to be achieved</small></th>
        <th class="text-wrap">Deliverables and KPI’s<br><small class="fw-light d-block text-wrap">Deliverables - the evidence that the result has been achieved; KPI’s give an indication of how well the result was achieved</small></th>
        <th class="text-wrap">Weight<br><small class="fw-light d-block text-wrap">The total weight of all objectives should be 100%</small></th>
      </tr>
    </thead>
    <tbody id="objectives-table-body">
    <?php for ($i = 1; $i <= 5; $i++): 
          $val = $objectives[$i - 1] ?? ['objective'=>'', 'timeline'=>'', 'indicator'=>'', 'weight'=>''];
          $isRequired = $i <= 3 ? 'required' : ''; // Only required for the first 3
        ?>
          <tr>
            <td><?= $i ?></td>
            <td><textarea name="objectives[<?= $i ?>][objective]" class="form-control objective-input ppa-summernote" rows="4" <?= $readonly ?> <?= $isRequired ?>><?= $val['objective'] ?></textarea></td>
            <td>
              <input type="text" 
                    name="objectives[<?= $i ?>][timeline]" 
                    class="form-control current_datepicker objective-input" 
                    <?= $readonly ?> 
                    value="<?php
                        if (empty($val['timeline'])&&($i<=3)) {
                          echo $period_end_year . '-12-31';
                        } else {
                          echo $val['timeline'];
                        }
                    ?>" 
                    <?= $isRequired ?>>
            </td>
            <td class="ppa-deliverables-cell"><textarea name="objectives[<?= $i ?>][indicator]" class="form-control objective-input ppa-summernote" rows="4" <?= $readonly ?> <?= $isRequired ?>><?= $val['indicator'] ?></textarea></td>
            <td><input type="number" name="objectives[<?= $i ?>][weight]" class="form-control objective-input" <?= $readonly ?> value="<?php if(empty($val['weight'])&&($i<=3)){ echo 0;}else{ echo $val['weight']; } ?>" <?= $isRequired ?>></td>
          </tr>
    <?php endfor; ?>

    </tbody>
  </table>
</div>

<hr>
<?php //if (@$ppa->draft_status == 0){?>
<h4>C. Personal Development Plan</h4>

<table class="form-table table-bordered" style="width:100%;">
  <tr>
    <td style="width: 30%;"><label class="form-label">Is training recommended for this staff member?</label></td>
    <td>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="training_recommended" id="training_yes" value="Yes" onchange="toggleTrainingSection(true)" <?= $readonly ?> <?= ($ppa->training_recommended ?? '') == 'Yes' ? 'checked' : '' ?>>
        <label class="form-check-label" for="training_yes">Yes</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="radio" name="training_recommended" id="training_no" value="No" onchange="toggleTrainingSection(false)" <?= $readonly ?> <?= ($ppa->training_recommended ?? '') == 'No' ? 'checked' : '' ?>>
        <label class="form-check-label" for="training_no">No</label>
      </div>
    </td>
  </tr>
</table>
<?php //} ?>

<section class="required_trainings" id="training-section" style="display: <?= ($ppa->training_recommended ?? '') == 'Yes' ? 'block' : 'none' ?>; margin-top: 15px;">
  <table class="form-table table-bordered" style="width:100%;">
    <tr>
      <td style="width: 30%;"><label for="skill-area" class="form-label">If yes, in what subject/ skill area(s) is the training recommended for this staff member?</label></td>
      <td>
        <select class="form-control select2" name="required_skills[]" multiple <?= $readonly ?>>
          <?php foreach ($skills as $skill): ?>
            <option value="<?= $skill->id ?>" <?= in_array($skill->id, $selected_skills) ? 'selected' : '' ?>><?= $skill->skill ?></option>
          <?php endforeach; ?>
        </select>
        <small>Select one or more skill areas.</small>
      </td>
    </tr>
    <tr>
      <td><label for="training-contribution" class="form-label"> Explain how the training will contribute to the staff member’s development and the department’s work.</label></td>
      <td>
        <textarea id="training-contribution" class="form-control ppa-summernote" rows="3" name="training_contributions" <?= $readonly ?>><?= $ppa->training_contributions ?? '' ?></textarea>
      </td>
    </tr>
    <tr>
      <td><label class="form-label">Selection of courses in line with training needs</label></td>
      <td>
      <small>Separate multiple courses using a semicolon (;).	With reference to the current AUC Learning and Development (L&D) Catalogue, please list the recommended course(s) for this staff member:</small>
        <textarea id="training_courses_catalog" class="form-control ppa-summernote" rows="3" name="recommended_trainings" <?= $readonly ?>><?= $ppa->recommended_trainings ?? '' ?></textarea>
 
        <small>Where applicable, please provide details of highly <b>recommendable course(s)</b> for this staff member that are not listed in the AUC L&D Catalogue</small>
        <textarea id="training_courses_extra" class="form-control ppa-summernote" rows="3" name="recommended_trainings_details" <?= $readonly ?>><?= $ppa->recommended_trainings_details ?? '' ?></textarea>
        
      </td>
    </tr>
  </table>
</section>

<hr>
</div><!-- /#ppaPreviewSource — excludes staff submission, supervisor approval, and signing UI -->

<table class="form-table">
  <!-- <tr>
    <td colspan="4">
      <p>
        I hereby confirm that this PPA has been developed in consultation with my supervisor
        and that it is aligned with the departmental objectives.
        I fully understand my performance objectives and what I am expected to deliver during this performance period.
        I am also aware of the competencies that I will be assessed on for the same period.
      </p>
      <input type="checkbox" id="staff_sign_off" name="staff_sign_off" value="1" <?= $readonly ?> <?= ($ppa->staff_sign_off ?? 0) ? 'checked' : '' ?> required>
      <label for="staff_sign_off">Confirm</label>
    </td>
  </tr> -->
 

  <tr>
  <td colspan="4" class="text-center">

    <?php if (!$readonly):?>
      <?php if(intval($ppa_settings->allow_employee_comments)==1):?>
      <br>
      <label>Comments for Approval</label>
      <textarea name="comments" class="form-control" rows="3" placeholder="Enter approval comments..."></textarea>
      <br>
        <?php endif; ?>
  
      <br>
      <!-- Staff Submission Buttons -->
      <?php if ((empty(@$ppa->staff_id))||(@$ppa->staff_id == $this->session->userdata('user')->staff_id)){?>
      <button type="submit" name="submit_action" value="draft" class="btn btn-warning px-5"><i class="fas fa-save me-1"></i> Save Draft</button>
      <br><br>
      <button type="submit" name="submit_action" value="submit" class="btn btn-success px-5"><i class="fas fa-paper-plane me-1"></i> Submit</button>
      <?php } ?>
      <br><br>

    <?php endif; ?>
    

    <?php echo form_close(); ?>

<?php if (!empty($isPpaApprover)): ?>
<div class="modal fade" id="ppaApproverPreviewModal" tabindex="-1" aria-labelledby="ppaApproverPreviewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ppaApproverPreviewModalLabel">PPA — preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3 ppa-preview-modal-body" id="ppaPreviewModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<style>
  #ppaApproverPreviewModal .preview-readonly-text { white-space: pre-wrap; word-break: break-word; min-height: 1.2em; }
</style>
<script>
(function () {
  function flattenPreviewToText(root) {
    root.querySelectorAll('input[type="hidden"]').forEach(function (n) { n.remove(); });
    root.querySelectorAll('textarea').forEach(function (el) {
      var d = document.createElement('div');
      d.className = el.classList.contains('ppa-summernote') ? 'preview-readonly-text ppa-html-preview' : 'preview-readonly-text';
      if (el.classList.contains('ppa-summernote')) {
        d.innerHTML = (el.value || '').trim() ? el.value : '<span class="text-muted">—</span>';
      } else {
        d.textContent = el.value || '';
      }
      el.parentNode.replaceChild(d, el);
    });
    root.querySelectorAll('select').forEach(function (el) {
      var d = document.createElement('div');
      d.className = 'preview-readonly-text';
      var t = [];
      if (el.selectedOptions) for (var i = 0; i < el.selectedOptions.length; i++) t.push(el.selectedOptions[i].text);
      d.textContent = t.length ? t.join(', ') : '—';
      el.parentNode.replaceChild(d, el);
    });
    root.querySelectorAll('input').forEach(function (el) {
      var type = (el.type || 'text').toLowerCase();
      if (type === 'hidden') return;
      if (type === 'button' || type === 'submit' || type === 'reset') { el.remove(); return; }
      if (type === 'checkbox') {
        var s = document.createElement('span');
        s.className = 'preview-readonly-text';
        s.textContent = el.checked ? 'Yes' : '—';
        el.parentNode.replaceChild(s, el);
        return;
      }
      if (type === 'radio') {
        if (el.checked) {
          var s = document.createElement('span');
          s.className = 'preview-readonly-text';
          var lab = el.nextElementSibling;
          s.textContent = (lab && lab.tagName === 'LABEL') ? lab.textContent.trim() : (el.value || '');
          el.parentNode.replaceChild(s, el);
          if (lab && lab.tagName === 'LABEL') lab.remove();
        } else {
          var lab2 = el.nextElementSibling;
          el.remove();
          if (lab2 && lab2.tagName === 'LABEL') lab2.remove();
        }
        return;
      }
      var s = document.createElement('span');
      s.className = 'preview-readonly-text';
      s.textContent = el.value || '—';
      el.parentNode.replaceChild(s, el);
    });
    root.querySelectorAll('button').forEach(function (el) { el.remove(); });
  }

  var modalEl = document.getElementById('ppaApproverPreviewModal');
  var srcEl = document.getElementById('ppaPreviewSource');
  var bodyEl = document.getElementById('ppaPreviewModalBody');
  if (!modalEl || !srcEl || !bodyEl) return;

  modalEl.addEventListener('show.bs.modal', function () {
    if (typeof jQuery !== 'undefined') {
      jQuery('#ppaPreviewSource .ppa-summernote').each(function () {
        var $t = jQuery(this);
        if ($t.next('.note-editor').length) {
          $t.val($t.summernote('code'));
        }
      });
    }
    bodyEl.innerHTML = '';
    var hint = document.createElement('p');
    hint.className = 'text-muted small mb-2';
    hint.textContent = 'Sections A–C only (staff details, objectives, PDP). Submission, approval, and sign-off are not shown here.';
    bodyEl.appendChild(hint);
    var clone = srcEl.cloneNode(true);
    clone.removeAttribute('id');
    clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
    flattenPreviewToText(clone);
    bodyEl.appendChild(clone);
  });
})();
</script>
<?php endif; ?>

<!-- Set performance period years for JavaScript validation and flatpickr min/max (B. Performance Objectives timelines) -->
<script>
  // Extract years from performance period (e.g., "January-2025-to-December-2025" -> [2025]) so timelines stay within period
  <?php if (!empty($period_for_form)): ?>
    const performancePeriod = <?= json_encode($period_for_form) ?>;
    const yearMatches = performancePeriod.match(/\d{4}/g);
    window.performancePeriodYears = yearMatches ? [...new Set(yearMatches.map(y => parseInt(y)))] : [];
  <?php else: ?>
    window.performancePeriodYears = [];
  <?php endif; ?>
</script>

<?php 
  //dd($showApprovalBtns); 
  $status = ((intval(@$ppa_settings->allow_supervisor_return) === 1) && (in_array('83', $permissions)));
  if ((($showApprovalBtns ==='show')||(in_array('83', $permissions)))){ 
    // Check if $ppa exists and is not false
    if (!empty($ppa) && is_object($ppa) && isset($ppa->entry_id)) { ?>
  <?php echo form_open('performance/approve_ppa/' . $ppa->entry_id, [
      'method' => 'post',
      'id'     => 'approvalForm_' . $ppa->entry_id
  ]); ?>
  <?php if((intval($ppa_settings->allow_employee_comments)==1)||(@$status)){?>
    <div class="mb-3">
      <label for="comments">Comments for Approval/Return</label>
      <textarea id="comments" name="comments" class="form-control" rows="3" required></textarea>
    </div>
    <?php } ?>

    <input type="hidden" name="action" id="approval_action" value="">

    <?php
    $ppa_supervisor_can_act = !empty($ppa) && is_object($ppa)
      && ((int) @$ppa->draft_status !== 2)
      && ((@$ppa->supervisor_id == $session->staff_id) || (@$ppa->supervisor2_id == $session->staff_id));
    ?>
    <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-3">
      <?php if ($ppa_supervisor_can_act): ?>
      <button type="submit" form="staff_ppa" name="submit_action" value="submit" class="btn btn-success px-5">
        <i class="fas fa-save me-1"></i> Save Changes (If Any)
      </button>
      <button type="submit" class="btn btn-success px-5" onclick="document.getElementById('approval_action').value = 'approve';">
        <i class="fas fa-check me-1"></i> Approve
      </button>
      <?php endif; ?>
      <?php if ((@$status) && !empty($ppa) && is_object($ppa) && isset($ppa->entry_id)) { ?>
      <button type="button" class="btn btn-danger px-5" data-bs-toggle="modal" data-bs-target="#confirmReturnModal_<?= $ppa->entry_id ?>">
        <i class="fas fa-reply me-1"></i> Return
      </button>
      <?php } ?>
    </div>
  </form>
<?php } // End check for $ppa existence ?>
<!-- Return Confirmation Modal -->
<?php if (!empty($ppa) && is_object($ppa) && isset($ppa->entry_id)): ?>
<div class="modal fade" id="confirmReturnModal_<?= $ppa->entry_id ?>" tabindex="-1" aria-labelledby="confirmReturnModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header bg-warning text-dark border-0">
        <h5 class="modal-title d-flex align-items-center" id="confirmReturnModalLabel">
          <i class="fas fa-exclamation-triangle me-2 fs-4 text-danger"></i> Confirm Return
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body text-center">
    
        <p class="fs-5 fw-semibold mb-3">
          Are you sure you want to return this PPA to the staff for revision?
        </p>
        <p class="text-muted">
          Please ensure your comments clearly explain the reason for return.
        
        </p>
      </div>
      
      <div class="modal-footer border-0 d-flex justify-content-center">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i> Cancel
        </button>
        <button type="button" class="btn btn-danger px-4" onclick="submitReturnAction('<?= $ppa->entry_id ?>')">
          <i class="fas fa-reply me-1"></i> Yes, Return
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; // End check for $ppa existence in modal ?>


<?php } ?>


  </td>
</tr>

</table>



<hr>
<h4>Approval Trail</h4>
<table class="table table-bordered">
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
  <?php if (!empty($approval_trail)): ?>
    <?php foreach ($approval_trail as $log): 
      $logged = Modules::run('auth/contract_info', $log->staff_id);
      if (!is_object($logged)) {
        $logged = staff_details($log->staff_id);
      }

      // Determine role
      if ($log->staff_id == $ppa->staff_id) {
          $role = 'Staff';
      } elseif ($log->staff_id == $ppa->supervisor_id) {
          $role = 'First Supervisor';
      } elseif ($ppa->supervisor2_id && $log->staff_id == $ppa->supervisor2_id) {
          $role = 'Second Supervisor';
      } else {
          $role = 'Other';
      }
    ?>
      <tr>
        <td><?php echo is_object($logged) ? htmlspecialchars(trim(($logged->title ?? '') . ' ' . ($logged->fname ?? '') . ' ' . ($logged->lname ?? '') . ' ' . ($logged->oname ?? ''))) : htmlspecialchars(staff_name($log->staff_id)); ?></td>
        <td><?= $role; ?></td>
        <td><?= $log->action; ?></td>
        <td><?= date('d M Y H:i', strtotime($log->created_at)); ?></td>
        <td><?= $log->comments; ?></td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
    <tr>
      <td colspan="5" class="text-center">No approval activity yet.</td>
    </tr>
  <?php endif; ?>
</tbody>

</table>

<script>
  function submitReturnAction(entryId) {
    const form = document.getElementById('approvalForm_' + entryId);
    if (form) {
      form.querySelector('#approval_action').value = 'return';
      form.submit();
    }
  }
</script>

<script>
$(function () {
  window.initPpaSummernote = function () {
    $('.ppa-summernote').each(function () {
      var $ta = $(this);
      if ($ta.next('.note-editor').length) {
        return;
      }
      var readOnly = $ta.prop('readonly') || $ta.prop('disabled');
      $ta.removeAttr('readonly').removeAttr('disabled');
      $ta.summernote({
        placeholder: 'Type here…',
        tabsize: 2,
        height: 170,
        dialogsInBody: true,
        fontNames: ['Arial', 'Arial Black', 'Helvetica', 'sans-serif'],
        fontNamesIgnoreCheck: ['Arial', 'Arial Black', 'Helvetica', 'sans-serif'],
        toolbar: [
          ['style', ['bold', 'italic', 'underline', 'clear']],
          ['para', ['ul', 'ol', 'paragraph']],
          ['insert', ['link']],
          ['view', ['fullscreen', 'codeview']]
        ],
        callbacks: {
          onInit: function () {
            $(this).next('.note-editor').find('.note-editable').css({
              fontFamily: 'Arial, Helvetica, sans-serif',
              fontSize: '14px'
            });
          }
        }
      });
      if (readOnly) {
        $ta.summernote('disable');
      }
    });
  };
  setTimeout(window.initPpaSummernote, 0);
  $('#staff_ppa').on('submit', function () {
    $('.ppa-summernote').each(function () {
      var $ta = $(this);
      if ($ta.next('.note-editor').length) {
        $ta.val($ta.summernote('code'));
      }
    });
  });
  setTimeout(function () {
    var _toggleTraining = window.toggleTrainingSection;
    if (typeof _toggleTraining === 'function' && !_toggleTraining._ppaWrapped) {
      window.toggleTrainingSection = function (show) {
        _toggleTraining(show);
        if (show && typeof window.initPpaSummernote === 'function') {
          setTimeout(function () {
            $('#training-section .ppa-summernote').each(function () {
              var $t = $(this);
              if ($t.next('.note-editor').length) {
                try {
                  $t.summernote('destroy');
                } catch (e) { /* ignore */ }
              }
            });
            window.initPpaSummernote();
          }, 80);
        }
      };
      window.toggleTrainingSection._ppaWrapped = true;
    }
  }, 0);
});
</script>
