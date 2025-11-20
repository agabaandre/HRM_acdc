<?php
$staff = $this->session->userdata('user');
$contract = isset($contract) ? $contract : Modules::run('auth/contract_info', $staff->staff_id);
$supervisor = isset($supervisor) ? $supervisor : null;
$second_supervisor = isset($second_supervisor) ? $second_supervisor : null;
$directorate = isset($directorate) ? $directorate : null;

$photo_url = base_url('uploads/staff/' . @$staff->photo);
$signature_url = base_url('uploads/staff/signature/' . @$staff->signature);
$photo_display = !empty($staff->photo) && file_exists(FCPATH . 'uploads/staff/' . $staff->photo) ? $photo_url : base_url('assets/images/pp.png');
$signature_display = (!empty($staff->signature) && file_exists(FCPATH . 'uploads/staff/signature/' . $staff->signature)) ? $signature_url : base_url('uploads/staff/signature.png');

// Format dates
$dob = !empty($staff->date_of_birth) ? date('M d, Y', strtotime($staff->date_of_birth)) : 'N/A';
$contract_start = !empty($contract->start_date) ? date('M d, Y', strtotime($contract->start_date)) : 'N/A';
$contract_end = !empty($contract->end_date) ? date('M d, Y', strtotime($contract->end_date)) : 'N/A';
?>

<div class="container-fluid">
  <div class="row">
    
    <!-- Left Column: Profile Summary Card -->
    <div class="col-xl-4 col-lg-5 col-md-12 mb-4">
      <div class="card shadow-sm">
        <div class="card-body text-center">
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

          <hr>

          <!-- Staff Signature -->
          <div class="text-center">
            <img src="<?= $signature_display ?>" alt="Signature" style="max-height: 80px; max-width: 200px;">
            <p class="small mt-2 text-muted">Staff Signature</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Detailed Information and Edit Form -->
    <div class="col-xl-8 col-lg-7 col-md-12">
      
      <!-- Edit Personal Details Card -->
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit My Details</h5>
        </div>
        <div class="card-body">
          <?= form_open_multipart(base_url('auth/update_profile'), ['id' => 'profile']) ?>
          <input type="hidden" name="staff_id" value="<?=$staff->staff_id?>">
          <input type="hidden" name="user_id" value="<?= $staff->user_id?>">
          <input type="hidden" name="name" value="<?= $staff->title .' '.$staff->fname.' '.$staff->lname ?>">

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Private Email <span class="text-danger">*</span></label>
              <input type="email" name="private_email" value="<?= $staff->private_email ?>" class="form-control" required>
              <small class="text-muted">Your personal email address</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp Number</label>
              <input type="text" name="whatsapp" value="<?= $staff->whatsapp ?>" class="form-control" placeholder="+1234567890">
              <small class="text-muted">Include country code (e.g., +1234567890)</small>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Primary Phone <span class="text-danger">*</span></label>
              <input type="text" name="tel_1" value="<?= $staff->tel_1 ?>" class="form-control" required>
              <small class="text-muted">Your primary contact number</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Alternative Number</label>
              <input type="text" name="tel_2" value="<?= $staff->tel_2 ?>" class="form-control">
              <small class="text-muted">Secondary contact number (optional)</small>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Preferred Language</label>
            <?php $langs = ['en' => 'English', 'fr' => 'French', 'sw' => 'Swahili', 'ar' => 'Arabic']; ?>
            <select name="langauge" class="form-select">
              <?php foreach ($langs as $k => $v): ?>
                <option value="<?= $k ?>" <?= $staff->langauge == $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Select your preferred language for the system</small>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Upload New Photo</label>
              <input type="file" name="photo" class="form-control" accept="image/*">
              <small class="text-muted">Max size: 1MB. Recommended: Square image (150x150px)</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Upload Signature</label>
              <input type="file" name="signature" class="form-control" accept="image/*">
              <small class="text-muted">Max size: 1MB. Recommended: PNG with transparent background</small>
            </div>
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
      
      <!-- Employment Information Card -->
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Employment Information</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Division</label>
              <div class="form-control-plaintext"><?= !empty($contract->division_name) ? $contract->division_name : 'N/A' ?></div>
            </div>
            <?php if ($directorate): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Directorate</label>
              <div class="form-control-plaintext"><?= $directorate->directorate_name ?></div>
            </div>
            <?php endif; ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Duty Station</label>
              <div class="form-control-plaintext"><?= !empty($contract->duty_station_name) ? $contract->duty_station_name : 'N/A' ?></div>
            </div>
            <?php if (!empty($staff->physical_location)): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Physical Location</label>
              <div class="form-control-plaintext"><?= $staff->physical_location ?></div>
            </div>
            <?php endif; ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Job Title</label>
              <div class="form-control-plaintext"><?= !empty($contract->job_name) ? $contract->job_name : 'N/A' ?></div>
            </div>
            <?php if (!empty($contract->job_acting_name)): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Acting Position</label>
              <div class="form-control-plaintext"><?= $contract->job_acting_name ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($contract->grade_name)): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Grade</label>
              <div class="form-control-plaintext"><?= $contract->grade_name ?></div>
            </div>
            <?php endif; ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Contract Type</label>
              <div class="form-control-plaintext"><?= !empty($contract->contract_type_name) ? $contract->contract_type_name : 'N/A' ?></div>
            </div>
            <?php if (!empty($contract->contracting_institution_name)): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Contracting Institution</label>
              <div class="form-control-plaintext"><?= $contract->contracting_institution_name ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($contract->funder_name)): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Funder</label>
              <div class="form-control-plaintext"><?= $contract->funder_name ?></div>
            </div>
            <?php endif; ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Contract Start Date</label>
              <div class="form-control-plaintext"><?= $contract_start ?></div>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Contract End Date</label>
              <div class="form-control-plaintext"><?= $contract_end ?></div>
            </div>
            <?php if (!empty($contract->status_name)): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Contract Status</label>
              <div class="form-control-plaintext">
                <span class="badge bg-<?= $contract->status_name == 'Active' ? 'success' : 'secondary' ?>">
                  <?= $contract->status_name ?>
                </span>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Supervisor Information Card -->
      <?php if ($supervisor || $second_supervisor): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>Supervisor Information</h5>
        </div>
        <div class="card-body">
          <div class="row">
            <?php if ($supervisor): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Primary Supervisor</label>
              <div class="d-flex align-items-center">
                <?php if (!empty($supervisor->photo) && file_exists(FCPATH . 'uploads/staff/' . $supervisor->photo)): ?>
                  <img src="<?= base_url('uploads/staff/' . $supervisor->photo) ?>" 
                       class="rounded-circle me-2" 
                       style="width: 40px; height: 40px; object-fit: cover;" 
                       alt="Supervisor">
                <?php endif; ?>
                <div>
                  <div class="fw-semibold"><?= $supervisor->title .' '.$supervisor->fname . ' ' . $supervisor->lname ?></div>
                  <small class="text-muted"><?= $supervisor->work_email ?></small>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($second_supervisor): ?>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold text-muted">Secondary Supervisor</label>
              <div class="d-flex align-items-center">
                <?php if (!empty($second_supervisor->photo) && file_exists(FCPATH . 'uploads/staff/' . $second_supervisor->photo)): ?>
                  <img src="<?= base_url('uploads/staff/' . $second_supervisor->photo) ?>" 
                       class="rounded-circle me-2" 
                       style="width: 40px; height: 40px; object-fit: cover;" 
                       alt="Supervisor">
                <?php endif; ?>
                <div>
                  <div class="fw-semibold"><?= $second_supervisor->title .' '.$second_supervisor->fname . ' ' . $second_supervisor->lname ?></div>
                  <small class="text-muted"><?= $second_supervisor->work_email ?></small>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
