<?php
$staff = $this->session->userdata('user');
$profile_old = isset($profile_old_input) && is_array($profile_old_input) ? $profile_old_input : [];
$contract = isset($contract) ? $contract : Modules::run('auth/contract_info', $staff->staff_id);
$supervisor = isset($supervisor) ? $supervisor : null;
$second_supervisor = isset($second_supervisor) ? $second_supervisor : null;
$directorate = isset($directorate) ? $directorate : null;

$photo_url = (!empty($staff->photo) && is_file(FCPATH . 'uploads/staff/' . $staff->photo)) ? staff_secure_upload_url('photo', $staff->photo) : '';
$signature_url = (!empty($staff->signature) && is_file(FCPATH . 'uploads/staff/signature/' . $staff->signature)) ? staff_secure_upload_url('signature', $staff->signature) : '';
$photo_display = $photo_url !== '' ? $photo_url : base_url('assets/images/pp.png');
$signature_display = $signature_url !== '' ? $signature_url : base_url('assets/images/pp.png');

$passport_fn = isset($staff->passport_biodata_page) ? (string) $staff->passport_biodata_page : '';
$passport_path = FCPATH . 'uploads/staff/passport_biodata/' . $passport_fn;
$passport_url = ($passport_fn !== '' && is_file($passport_path)) ? staff_secure_upload_url('passport_biodata', $passport_fn) : '';
$passport_ext = $passport_fn !== '' ? strtolower(pathinfo($passport_fn, PATHINFO_EXTENSION)) : '';
$passport_image_preview = $passport_url !== '' && !in_array($passport_ext, ['pdf'], true);

$kin_types = isset($kin_relationship_types) && is_array($kin_relationship_types) ? $kin_relationship_types : [];
$kin_name_by_id = [];
foreach ($kin_types as $kt) {
  $kin_name_by_id[(int) $kt->kin_relationship_id] = $kt->relationship_name;
}
if (!function_exists('profile_form_value')) {
  /**
   * Prefer last POST (flash) so the form stays filled after validation errors.
   */
  function profile_form_value(array $old, $staff, string $key) {
    if (array_key_exists($key, $old)) {
      return $old[$key];
    }
    if (is_object($staff) && property_exists($staff, $key)) {
      return $staff->$key;
    }
    return '';
  }
}
if (!function_exists('profile_normalize_nok_row')) {
  function profile_normalize_nok_row($row) {
    $out = ['name' => '', 'relationship_id' => '', 'phone' => '', 'email' => ''];
    if (!is_array($row)) {
      return $out;
    }
    $out['name'] = $row['name'] ?? '';
    $out['relationship_id'] = $row['relationship_id'] ?? '';
    $out['phone'] = trim((string) ($row['phone'] ?? ''));
    $out['email'] = trim((string) ($row['email'] ?? ''));
    if ($out['phone'] === '' && $out['email'] === '' && !empty($row['contact'])) {
      $c = trim((string) $row['contact']);
      if ($c !== '' && strpos($c, '@') !== false) {
        $out['email'] = $c;
      } elseif ($c !== '') {
        $out['phone'] = $c;
      }
    }
    return $out;
  }
}
if (!empty($profile_old['next_of_kin']) && is_array($profile_old['next_of_kin'])) {
  $nok_list = array_values($profile_old['next_of_kin']);
  while (count($nok_list) < 2) {
    $nok_list[] = [];
  }
  $nok_list = array_slice($nok_list, 0, 2);
} else {
  $nok_list = json_decode(isset($staff->next_of_kin_json) ? $staff->next_of_kin_json : '[]', true);
  if (!is_array($nok_list)) {
    $nok_list = [];
  }
  $nok_list = array_values($nok_list);
  while (count($nok_list) < 2) {
    $nok_list[] = [];
  }
  $nok_list = array_slice($nok_list, 0, 2);
}
$nok_list[0] = profile_normalize_nok_row($nok_list[0] ?? []);
$nok_list[1] = profile_normalize_nok_row($nok_list[1] ?? []);

// Format dates
$dob = !empty($staff->date_of_birth) ? date('M d, Y', strtotime($staff->date_of_birth)) : 'N/A';
$contract_start = !empty($contract->start_date) ? date('M d, Y', strtotime($contract->start_date)) : 'N/A';
$contract_end = !empty($contract->end_date) ? date('M d, Y', strtotime($contract->end_date)) : 'N/A';
?>
<style>
  /* Sidebar: 5/12 width from md up (col-md-5); full width stacked on extra-small screens */
  @media (min-width: 768px) {
    .profile-sidebar-card {
      position: sticky;
      top: 1rem;
      max-height: calc(100vh - 2rem);
      overflow-y: auto;
    }
  }
  .profile-sidebar-card .text-break { word-break: break-word; overflow-wrap: anywhere; }
  .profile-doc-preview-box {
    margin-top: 0.5rem;
    padding: 0.4rem;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    max-width: 132px;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
  }
  .profile-doc-preview-box .profile-doc-preview-label {
    font-size: 0.7rem;
    color: #6c757d;
    margin-bottom: 0.35rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .02em;
  }
  .profile-doc-preview-box img {
    display: block;
    width: 100%;
    max-height: 88px;
    object-fit: contain;
    border-radius: 0.25rem;
    vertical-align: middle;
  }
  .profile-doc-preview-box a.img-link { display: block; line-height: 0; }
  .profile-doc-preview-sig img { max-height: 52px; }
</style>

<div class="container-fluid">
  <div class="row g-3">
    
    <!-- Left column: profile summary + employment + supervisors (5 cols from md up) -->
    <div class="col-12 col-md-5 mb-md-0">
      <div class="card shadow-sm h-100 profile-sidebar-card">
        <div class="card-body text-center px-3 px-lg-3 py-3">
          <img class="img-fluid rounded-circle mb-3 border border-3" 
               style="width: 150px; height: 150px; object-fit: cover; border-color: #119a48 !important;" 
               src="<?= $photo_display ?>" 
               alt="Profile Image">
          <h4 class="fw-bold mb-1"><?= $staff->title .' '.$staff->fname . ' ' . $staff->lname ?></h4>
          <p class="text-muted mb-2"><?= !empty($contract->job_name) ? $contract->job_name : 'N/A' ?></p>
          <?php if (!empty($contract->job_acting_name)): ?>
            <p class="text-info mb-2"><small><i class="fas fa-user-tie"></i> Acting: <?= $contract->job_acting_name ?></small></p>
          <?php endif; ?>
          <div class="mb-3">
            <span class="badge bg-success me-1"><?= $staff->group_name ?></span>
            <?php if (!empty($contract->contract_type_name)): ?>
              <span class="badge bg-dark"><?= $contract->contract_type_name ?></span>
            <?php endif; ?>
            <?php if (!empty($contract->grade_name)): ?>
              <span class="badge bg-info"><?= $contract->grade_name ?></span>
            <?php endif; ?>
          </div>

          <hr>
          
          <!-- Personal Information -->
          <h6 class="text-uppercase fw-semibold mb-3 text-start">Personal Information</h6>
          <ul class="list-unstyled text-start fs-6 mb-4">
            <li class="mb-2">
              <i class="fas fa-calendar fa-md text-primary me-2"></i> 
              <strong>Date of Birth:</strong> <?= $dob ?>
            </li>
            <li class="mb-2">
              <i class="fas fa-globe fa-md text-primary me-2"></i> 
              <strong>Nationality:</strong> <?= !empty($staff->nationality) ? $staff->nationality : 'N/A' ?>
            </li>
            <li class="mb-2">
              <i class="fas fa-venus-mars fa-md text-primary me-2"></i> 
              <strong>Gender:</strong> <?= !empty($staff->gender) ? ucfirst($staff->gender) : 'N/A' ?>
            </li>
            <?php if (!empty($staff->SAPNO)): ?>
            <li class="mb-2">
              <i class="fas fa-id-card fa-md text-primary me-2"></i> 
              <strong>SAP Number:</strong> <?= $staff->SAPNO ?>
            </li>
            <?php endif; ?>
            </ul>

          <?php
          $sidebar_has_residential = trim((string) ($staff->residential_address_duty_station ?? '')) !== '';
          $sidebar_has_dependants = isset($staff->number_of_dependants) && $staff->number_of_dependants !== null && $staff->number_of_dependants !== '';
          $show_sidebar_address_section = $sidebar_has_residential || $sidebar_has_dependants;

          $sidebar_nok_rows = [];
          $nok_decode_sidebar = json_decode(isset($staff->next_of_kin_json) ? $staff->next_of_kin_json : '[]', true);
          if (is_array($nok_decode_sidebar)) {
            foreach ($nok_decode_sidebar as $nok_i => $nk_raw) {
              if (!is_array($nk_raw)) {
                continue;
              }
              $nk_norm = profile_normalize_nok_row($nk_raw);
              $rn = (int) ($nk_raw['relationship_id'] ?? 0);
              $has_name = trim((string) ($nk_norm['name'] ?? '')) !== '';
              $has_contact = ($nk_norm['phone'] ?? '') !== '' || ($nk_norm['email'] ?? '') !== '';
              if ($has_name || $has_contact) {
                $sidebar_nok_rows[] = [
                  'index' => (int) $nok_i,
                  'row' => $nk_norm,
                  'relationship_id' => $rn,
                ];
              }
            }
          }
          $show_sidebar_nok_section = count($sidebar_nok_rows) > 0;
          ?>

          <?php if ($show_sidebar_address_section): ?>
          <hr>
          <h6 class="text-uppercase fw-semibold mb-3 text-start">Address &amp; dependants</h6>
          <ul class="list-unstyled text-start fs-6 mb-4">
            <?php if ($sidebar_has_residential): ?>
            <li class="mb-2 text-start">
              <i class="fas fa-home fa-md text-primary me-2"></i>
              <strong>Residential address (at duty station):</strong><br>
              <span class="ms-4 text-break"><?= nl2br(htmlspecialchars($staff->residential_address_duty_station)) ?></span>
            </li>
            <?php endif; ?>
            <?php if ($sidebar_has_dependants): ?>
            <li class="mb-2 text-start">
              <i class="fas fa-users fa-md text-primary me-2"></i>
              <strong>Number of dependants:</strong><br>
              <span class="ms-4"><?= (int) $staff->number_of_dependants ?></span>
            </li>
            <?php endif; ?>
          </ul>
          <?php endif; ?>

          <?php if ($show_sidebar_nok_section): ?>
          <hr>
          <h6 class="text-uppercase fw-semibold mb-3 text-start">Next of kin</h6>
          <ul class="list-unstyled text-start fs-6 mb-4">
            <?php foreach ($sidebar_nok_rows as $snok): ?>
              <?php
                $nk = $snok['row'];
                $idx = $snok['index'];
                $rn = $snok['relationship_id'];
                $rlabel = $kin_name_by_id[$rn] ?? ($rn > 0 ? ('#' . $rn) : '');
              ?>
            <li class="mb-2 text-start">
              <i class="fas fa-user-friends fa-md text-primary me-2"></i>
              <strong>Next of kin <?= $idx + 1 ?>:</strong>
              <?php if (trim((string) ($nk['name'] ?? '')) !== ''): ?>
                <?= htmlspecialchars($nk['name']) ?>
              <?php endif; ?>
              <?php if ($rlabel !== ''): ?>
                <span class="text-muted">(<?= htmlspecialchars($rlabel) ?>)</span>
              <?php endif; ?>
              <br>
              <?php if (($nk['phone'] ?? '') !== ''): ?>
              <span class="ms-4 d-block"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($nk['phone']) ?></span>
              <?php endif; ?>
              <?php if (($nk['email'] ?? '') !== ''): ?>
              <span class="ms-4 d-block"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($nk['email']) ?></span>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>

          <hr>

          <!-- Contact Information -->
          <h6 class="text-uppercase fw-semibold mb-3 text-start">Contact Information</h6>
          <ul class="list-unstyled text-start fs-6 mb-4">
            <li class="mb-2">
              <i class="fas fa-envelope fa-md text-primary me-2"></i> 
              <strong>Work Email:</strong><br>
              <span class="ms-4"><?= $staff->work_email ?></span>
            </li>
            <?php if (!empty($staff->private_email)): ?>
            <li class="mb-2">
              <i class="fas fa-envelope-open fa-md text-secondary me-2"></i> 
              <strong>Private Email:</strong><br>
              <span class="ms-4"><?= $staff->private_email ?></span>
            </li>
            <?php endif; ?>
            <?php if (!empty($staff->tel_1)): ?>
            <li class="mb-2">
              <i class="fas fa-phone fa-md text-primary me-2"></i> 
              <strong>Primary Phone:</strong><br>
              <span class="ms-4"><?= $staff->tel_1 ?></span>
            </li>
            <?php endif; ?>
            <?php if (!empty($staff->whatsapp)): ?>
            <li class="mb-2">
              <i class="fab fa-whatsapp fa-md text-success me-2"></i> 
              <strong>WhatsApp:</strong><br>
              <span class="ms-4"><?= $staff->whatsapp ?></span>
            </li>
            <?php endif; ?>
            <?php if (!empty($staff->tel_2)): ?>
            <li class="mb-2">
              <i class="fas fa-phone-alt fa-md text-secondary me-2"></i> 
              <strong>Alternative Number:</strong><br>
              <span class="ms-4"><?= $staff->tel_2 ?></span>
            </li>
            <?php endif; ?>
          </ul>

          <hr>

          <!-- Passport biodata (before signature) — used for travel purposes -->
          <?php if ($passport_url !== ''): ?>
          <div class="text-center mb-3">
            <p class="small text-muted mb-1 fw-semibold">Passport biodata (for travel purposes)</p>
            <p class="small text-muted mb-2">Photo page with your details, for official travel.</p>
            <a href="<?= $passport_url ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-passport me-1"></i> View passport biodata file
            </a>
          </div>
          <?php else: ?>
          <div class="text-center mb-3">
            <p class="small text-muted mb-1 fw-semibold">Passport biodata (for travel purposes)</p>
            <p class="small text-muted mb-0"><span class="text-secondary">Not uploaded</span> — please add an image of your passport biodata page for travel.</p>
          </div>
          <?php endif; ?>

          <!-- Staff Signature -->
          <div class="text-center">
            <img src="<?= $signature_display ?>" alt="Signature" style="max-height: 80px; max-width: 200px;">
            <p class="small mt-2 text-muted">Staff Signature</p>
          </div>

          <hr class="my-3">

          <!-- Employment Information (same styling as Contact / Personal) -->
          <h6 class="text-uppercase fw-semibold mb-3 text-start">Employment Information</h6>
          <ul class="list-unstyled text-start fs-6 mb-4">
            <li class="mb-2">
              <i class="fas fa-sitemap fa-md text-primary me-2"></i>
              <strong>Division:</strong><br>
              <span class="ms-4 text-break"><?= !empty($contract->division_name) ? htmlspecialchars($contract->division_name) : 'N/A' ?></span>
            </li>
            <?php if ($directorate): ?>
            <li class="mb-2">
              <i class="fas fa-code-branch fa-md text-primary me-2"></i>
              <strong>Directorate:</strong><br>
              <span class="ms-4 text-break"><?= htmlspecialchars($directorate->directorate_name) ?></span>
            </li>
            <?php endif; ?>
            <li class="mb-2">
              <i class="fas fa-map-marker-alt fa-md text-primary me-2"></i>
              <strong>Duty Station:</strong><br>
              <span class="ms-4 text-break"><?= !empty($contract->duty_station_name) ? htmlspecialchars($contract->duty_station_name) : 'N/A' ?></span>
            </li>
            <?php if (!empty($staff->physical_location)): ?>
            <li class="mb-2">
              <i class="fas fa-map-pin fa-md text-primary me-2"></i>
              <strong>Physical Location:</strong><br>
              <span class="ms-4 text-break"><?= htmlspecialchars($staff->physical_location) ?></span>
            </li>
            <?php endif; ?>
            <li class="mb-2">
              <i class="fas fa-briefcase fa-md text-primary me-2"></i>
              <strong>Job Title:</strong><br>
              <span class="ms-4 text-break"><?= !empty($contract->job_name) ? htmlspecialchars($contract->job_name) : 'N/A' ?></span>
            </li>
            <?php if (!empty($contract->job_acting_name)): ?>
            <li class="mb-2">
              <i class="fas fa-user-tie fa-md text-primary me-2"></i>
              <strong>Acting Position:</strong><br>
              <span class="ms-4 text-break"><?= htmlspecialchars($contract->job_acting_name) ?></span>
            </li>
            <?php endif; ?>
            <?php if (!empty($contract->grade_name)): ?>
            <li class="mb-2">
              <i class="fas fa-layer-group fa-md text-primary me-2"></i>
              <strong>Grade:</strong><br>
              <span class="ms-4 text-break"><?= htmlspecialchars($contract->grade_name) ?></span>
            </li>
            <?php endif; ?>
            <li class="mb-2">
              <i class="fas fa-file-signature fa-md text-primary me-2"></i>
              <strong>Contract Type:</strong><br>
              <span class="ms-4 text-break"><?= !empty($contract->contract_type_name) ? htmlspecialchars($contract->contract_type_name) : 'N/A' ?></span>
            </li>
            <?php if (!empty($contract->contracting_institution_name)): ?>
            <li class="mb-2">
              <i class="fas fa-university fa-md text-primary me-2"></i>
              <strong>Contracting Institution:</strong><br>
              <span class="ms-4 text-break"><?= htmlspecialchars($contract->contracting_institution_name) ?></span>
            </li>
            <?php endif; ?>
            <?php if (!empty($contract->funder_name)): ?>
            <li class="mb-2">
              <i class="fas fa-hand-holding-usd fa-md text-primary me-2"></i>
              <strong>Funder:</strong><br>
              <span class="ms-4 text-break"><?= htmlspecialchars($contract->funder_name) ?></span>
            </li>
            <?php endif; ?>
            <li class="mb-2">
              <i class="fas fa-calendar-check fa-md text-primary me-2"></i>
              <strong>Contract Start Date:</strong><br>
              <span class="ms-4"><?= htmlspecialchars($contract_start) ?></span>
            </li>
            <li class="mb-2">
              <i class="fas fa-calendar-alt fa-md text-primary me-2"></i>
              <strong>Contract End Date:</strong><br>
              <span class="ms-4"><?= htmlspecialchars($contract_end) ?></span>
            </li>
            <?php if (!empty($contract->status_name)): ?>
            <li class="mb-2">
              <i class="fas fa-info-circle fa-md text-primary me-2"></i>
              <strong>Contract Status:</strong><br>
              <span class="ms-4">
                <span class="badge bg-<?= $contract->status_name == 'Active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($contract->status_name) ?></span>
              </span>
            </li>
            <?php endif; ?>
          </ul>

          <?php if ($supervisor || $second_supervisor): ?>
          <hr class="my-3">
          <h6 class="text-uppercase fw-semibold mb-3 text-start">Supervisor Information</h6>
          <ul class="list-unstyled text-start fs-6 mb-0">
            <?php if ($supervisor): ?>
            <li class="mb-3">
              <i class="fas fa-user-tie fa-md text-primary me-2"></i>
              <strong>Primary Supervisor:</strong><br>
              <div class="ms-4 d-flex align-items-center text-start mt-1">
                <?php if (!empty($supervisor->photo) && file_exists(FCPATH . 'uploads/staff/' . $supervisor->photo)): ?>
                  <img src="<?= htmlspecialchars(staff_secure_upload_url('photo', $supervisor->photo)) ?>"
                       class="rounded-circle me-2 flex-shrink-0"
                       style="width: 40px; height: 40px; object-fit: cover;"
                       alt="Primary supervisor">
                <?php endif; ?>
                <div class="min-w-0">
                  <div class="fw-semibold text-break"><?= htmlspecialchars($supervisor->title . ' ' . $supervisor->fname . ' ' . $supervisor->lname) ?></div>
                  <small class="text-muted text-break d-block"><?= htmlspecialchars($supervisor->work_email) ?></small>
                </div>
              </div>
            </li>
            <?php endif; ?>
            <?php if ($second_supervisor): ?>
            <li class="mb-0">
              <i class="fas fa-user-tie fa-md text-secondary me-2"></i>
              <strong>Secondary Supervisor:</strong><br>
              <div class="ms-4 d-flex align-items-center text-start mt-1">
                <?php if (!empty($second_supervisor->photo) && file_exists(FCPATH . 'uploads/staff/' . $second_supervisor->photo)): ?>
                  <img src="<?= htmlspecialchars(staff_secure_upload_url('photo', $second_supervisor->photo)) ?>"
                       class="rounded-circle me-2 flex-shrink-0"
                       style="width: 40px; height: 40px; object-fit: cover;"
                       alt="Secondary supervisor">
                <?php endif; ?>
                <div class="min-w-0">
                  <div class="fw-semibold text-break"><?= htmlspecialchars($second_supervisor->title . ' ' . $second_supervisor->fname . ' ' . $second_supervisor->lname) ?></div>
                  <small class="text-muted text-break d-block"><?= htmlspecialchars($second_supervisor->work_email) ?></small>
                </div>
              </div>
            </li>
            <?php endif; ?>
          </ul>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- Right column: edit form (7 cols from md up) -->
    <div class="col-12 col-md-7">
      
      <!-- Edit Personal Details Card -->
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit My Details</h5>
        </div>
        <div class="card-body">
          <?= form_open_multipart(base_url('auth/update_profile'), ['id' => 'profile']) ?>
          <input type="hidden" name="staff_id" value="<?=$staff->staff_id?>">
          <input type="hidden" name="user_id" value="<?= $staff->user_id?>">
          <input type="hidden" name="name" value="<?= htmlspecialchars(array_key_exists('name', $profile_old) ? (string) $profile_old['name'] : ($staff->title . ' ' . $staff->fname . ' ' . $staff->lname)) ?>">

          <!-- Section: Contact & language -->
          <div class="profile-form-section border rounded-3 p-3 p-md-4 mb-4 bg-light bg-opacity-50">
            <h6 class="text-uppercase text-secondary fw-bold small mb-3 pb-2 border-bottom">
              <i class="fas fa-address-book me-2 text-success"></i>Contact &amp; language
            </h6>
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Private Email <span class="text-danger">*</span></label>
                <input type="email" name="private_email" value="<?= htmlspecialchars((string) profile_form_value($profile_old, $staff, 'private_email')) ?>" class="form-control" required>
                <small class="text-muted">Your personal email address</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">WhatsApp Number</label>
                <input type="text" name="whatsapp" value="<?= htmlspecialchars((string) profile_form_value($profile_old, $staff, 'whatsapp')) ?>" class="form-control" placeholder="+1234567890">
                <small class="text-muted">Include country code (e.g., +1234567890)</small>
              </div>
            </div>
            <div class="row mb-3">
              <div class="col-md-6">
                <label class="form-label">Primary Phone <span class="text-danger">*</span></label>
                <input type="text" name="tel_1" value="<?= htmlspecialchars((string) profile_form_value($profile_old, $staff, 'tel_1')) ?>" class="form-control" required>
                <small class="text-muted">Your primary contact number</small>
              </div>
              <div class="col-md-6">
                <label class="form-label">Alternative Number</label>
                <input type="text" name="tel_2" value="<?= htmlspecialchars((string) profile_form_value($profile_old, $staff, 'tel_2')) ?>" class="form-control">
                <small class="text-muted">Secondary contact number (optional)</small>
              </div>
            </div>
            <div class="mb-0">
              <label class="form-label">Preferred Language</label>
              <?php $langs = ['en' => 'English', 'fr' => 'French', 'sw' => 'Swahili', 'ar' => 'Arabic']; ?>
              <select name="langauge" class="form-select">
                <?php
                $lang_cur = (string) profile_form_value($profile_old, $staff, 'langauge');
                ?>
                <?php foreach ($langs as $k => $v): ?>
                  <option value="<?= $k ?>" <?= ($lang_cur === (string) $k) ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
              <small class="text-muted">Select your preferred language for the system</small>
            </div>
          </div>

          <!-- Section: Documents -->
          <div class="profile-form-section border rounded-3 p-3 p-md-4 mb-4 bg-light bg-opacity-50">
            <h6 class="text-uppercase text-secondary fw-bold small mb-3 pb-2 border-bottom">
              <i class="fas fa-file-image me-2 text-success"></i>Photo, passport (travel) &amp; signature
            </h6>
            <div class="row g-3 mb-0">
              <div class="col-md-4">
                <label class="form-label">Upload New Photo</label>
                <input type="file" name="photo" class="form-control" accept="image/*">
                <small class="text-muted">Max 1MB. Square image (150×150px) recommended.</small>
                <?php if ($photo_url !== ''): ?>
                <div class="profile-doc-preview-box">
                  <div class="profile-doc-preview-label">Current photo</div>
                  <a href="<?= htmlspecialchars($photo_url) ?>" target="_blank" rel="noopener" class="img-link">
                    <img src="<?= htmlspecialchars($photo_url) ?>" alt="Current profile photo" loading="lazy">
                  </a>
                </div>
                <?php endif; ?>
              </div>
              <div class="col-md-4">
                <label class="form-label">Passport biodata page <span class="text-muted fw-normal">(for travel purposes)</span></label>
                <input type="file" name="passport_biodata" class="form-control" accept="image/*">
                <small class="text-muted">Used for official travel arrangements. Image only (JPG, PNG, or GIF), max 4MB.</small>
                <?php if ($passport_url !== ''): ?>
                <div class="profile-doc-preview-box">
                  <div class="profile-doc-preview-label">Current file</div>
                  <?php if ($passport_image_preview): ?>
                  <a href="<?= htmlspecialchars($passport_url) ?>" target="_blank" rel="noopener" class="img-link">
                    <img src="<?= htmlspecialchars($passport_url) ?>" alt="Passport biodata preview" loading="lazy">
                  </a>
                  <?php else: ?>
                  <a href="<?= htmlspecialchars($passport_url) ?>" target="_blank" rel="noopener" class="small">Open PDF / file</a>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </div>
              <div class="col-md-4">
                <label class="form-label">Upload Signature</label>
                <input type="file" name="signature" class="form-control" accept="image/*">
                <small class="text-muted">Max 1MB. PNG with transparent background recommended.</small>
                <?php if ($signature_url !== ''): ?>
                <div class="profile-doc-preview-box profile-doc-preview-sig">
                  <div class="profile-doc-preview-label">Current signature</div>
                  <a href="<?= htmlspecialchars($signature_url) ?>" target="_blank" rel="noopener" class="img-link">
                    <img src="<?= htmlspecialchars($signature_url) ?>" alt="Current signature" loading="lazy" style="background: linear-gradient(45deg, #f1f3f5 25%, transparent 25%), linear-gradient(-45deg, #f1f3f5 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f1f3f5 75%), linear-gradient(-45deg, transparent 75%, #f1f3f5 75%); background-size: 8px 8px; background-position: 0 0, 0 4px, 4px -4px, -4px 0;">
                  </a>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Section: Address & household -->
          <div class="profile-form-section border rounded-3 p-3 p-md-4 mb-4 bg-light bg-opacity-50">
            <h6 class="text-uppercase text-secondary fw-bold small mb-3 pb-2 border-bottom">
              <i class="fas fa-home me-2 text-success"></i>Address &amp; dependants
            </h6>
            <div class="mb-3">
              <label class="form-label">Residential address (at duty station) <span class="text-danger">*</span></label>
              <textarea name="residential_address_duty_station" class="form-control" rows="3" placeholder="Street, building, city" required><?= htmlspecialchars((string) profile_form_value($profile_old, $staff, 'residential_address_duty_station')) ?></textarea>
            </div>
            <div class="row mb-0">
              <div class="col-md-4">
                <label class="form-label">Number of dependants <span class="text-danger">*</span></label>
                <input type="number" name="number_of_dependants" class="form-control" min="0" step="1"
                       value="<?php
                       if (array_key_exists('number_of_dependants', $profile_old)) {
                         echo htmlspecialchars((string) $profile_old['number_of_dependants']);
                       } else {
                         echo (isset($staff->number_of_dependants) && $staff->number_of_dependants !== null && $staff->number_of_dependants !== '')
                           ? htmlspecialchars((string) (int) $staff->number_of_dependants) : '';
                       }
                       ?>"
                       placeholder="e.g. 0" required>
              </div>
            </div>
            <p class="small text-muted mb-0 mt-2">Use <strong>0</strong> if you have no dependants.</p>
          </div>

          <!-- Section: Next of kin -->
          <div class="profile-form-section border rounded-3 p-3 p-md-4 mb-4 bg-light bg-opacity-50">
            <h6 class="text-uppercase text-secondary fw-bold small mb-3 pb-2 border-bottom">
              <i class="fas fa-user-friends me-2 text-success"></i>Next of kin
            </h6>
            <p class="small text-muted mb-3">The <strong>first</strong> next of kin is required (name, relationship, phone, and email). You may add a <strong>second</strong> contact optionally. Relationship types are managed under <strong>Settings → Next of kin relationships</strong>.</p>
            <?php foreach ([0 => 'First next of kin', 1 => 'Second next of kin (optional)'] as $idx => $label): ?>
              <?php $row = $nok_list[$idx] ?? profile_normalize_nok_row([]); ?>
              <?php $req = ($idx === 0); ?>
              <div class="border rounded p-3 mb-3 bg-white">
                <div class="fw-semibold mb-2"><?= htmlspecialchars($label) ?><?php if ($req): ?> <span class="text-danger">*</span><?php endif; ?></div>
                <div class="row g-2">
                  <div class="col-md-6 col-lg-4">
                    <label class="form-label small">Full name<?php if ($req): ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input type="text" name="next_of_kin[<?= $idx ?>][name]" class="form-control form-control-sm" value="<?= htmlspecialchars($row['name'] ?? '') ?>" <?= $req ? 'required' : '' ?>>
                  </div>
                  <div class="col-md-6 col-lg-4">
                    <label class="form-label small">Relationship<?php if ($req): ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <select name="next_of_kin[<?= $idx ?>][relationship_id]" class="form-select form-select-sm" <?= $req ? 'required' : '' ?>>
                      <option value="">— Select —</option>
                      <?php foreach ($kin_types as $kt): ?>
                        <option value="<?= (int) $kt->kin_relationship_id ?>" <?= ((int)($row['relationship_id'] ?? 0) === (int) $kt->kin_relationship_id) ? 'selected' : '' ?>><?= htmlspecialchars($kt->relationship_name) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="w-100"></div>
                  <div class="col-md-6">
                    <label class="form-label small">Phone number<?php if ($req): ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input type="text" name="next_of_kin[<?= $idx ?>][phone]" class="form-control form-control-sm" value="<?= htmlspecialchars($row['phone'] ?? '') ?>" <?= $req ? 'required' : '' ?> placeholder="+251…">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Email<?php if ($req): ?> <span class="text-danger">*</span><?php endif; ?></label>
                    <input type="email" name="next_of_kin[<?= $idx ?>][email]" class="form-control form-control-sm" value="<?= htmlspecialchars($row['email'] ?? '') ?>" <?= $req ? 'required' : '' ?> placeholder="name@example.com">
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> Only personal contact information and preferences can be edited. Employment information is managed by HR.
          </div>

          <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">
              <i class="fas fa-save me-2"></i> Save Changes
            </button>
          </div>
          <?= form_close(); ?>
        </div>
      </div>

    </div>
  </div>
</div>
