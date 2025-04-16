<?php
$staff = $this->session->userdata('user');
$contract = Modules::run('auth/contract_info', $staff->staff_id);
$photo_url = base_url('uploads/staff/' . @$staff->photo);
$signature_url = base_url('uploads/staff/signature/' . @$staff->signature);
$photo_display = !empty($staff->photo) ? $photo_url : base_url('assets/images/pp.png');
$signature_display = (!empty($staff->signature) && file_exists(FCPATH . 'uploads/staff/signature/' . $staff->signature)) ? $signature_url : base_url('uploads/staff/signature.png');

//dd($staff);
?>

<!-- <div class="main-container container">
  <div class="page-header">
    <div>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url() ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Account</li>
      </ol>
    </div>
  </div> -->

  <div class="row">
    
    <!-- Left Column: Summary Card -->
    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
      <div class="card">
        <div class="card-body text-center">
          <img class="img-fluid rounded mb-3" style="width: 150px; height: 150px; object-fit: cover;" src="<?= $photo_display ?>" alt="Profile Image">
          <h4 class="fw-bold"><?= $staff->title .' '.$staff->fname . ' ' . $staff->lname ?></h4>
          <p class="text-muted"><?= @$contract->job_name ?></p>
          <p class="badge bg-success"><?= $staff->group_name ?></p>
          <p class="badge bg-dark"><?= $staff->contract_type ?></p>

          <hr>
          <h6 class="text-uppercase fw-semibold mb-3">Profile Details</h6>
            <ul class="list-unstyled text-start fs-6">
            <li class="mb-2">
                <i class="fa fa-envelope fa-md text-primary me-2"></i> <?= $staff->work_email ?>
            </li>
            <li class="mb-2">
                <i class="fa fa-phone fa-md text-primary me-2"></i> <?= $staff->tel_1 ?>
            </li>
            <li class="mb-2">
                <i class="fa fa-calendar fa-md text-primary me-2"></i> DOB: <?= date('M d, Y', strtotime($staff->date_of_birth)) ?>
            </li>
            <li class="mb-2">
                <i class="fa fa-globe fa-md text-primary me-2"></i> Nationality: <?= $staff->nationality ?>
            </li>
          
            <li class="mb-2">
                <i class="fa fa-id-card fa-md text-primary me-2"></i> SAP NO: <?= $staff->SAPNO ?>
            </li>
          
            <li class="mb-2">
                <i class="fa fa-map-marker-alt fa-md text-primary me-2"></i> Station: <?= $staff->duty_station_name ?>
            </li>
            <li class="mb-2">
                <i class="fa fa-building fa-md text-primary me-2"></i> Division: <?= $staff->division_name ?>
            </li>
            </ul>


          <hr>
          <div class="text-center">
            <img src="<?= $signature_display ?>" alt="Signature" style="height: 80px;">
            <p class="small mt-2 text-muted">Staff Signature</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Form -->
    <div class="col-xl-8 col-lg-6 col-md-6">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Edit My Details</h5>
        </div>
        <div class="card-body">
          <?= form_open_multipart(base_url('auth/update_profile'), ['id' => 'profile']) ?>
          <input type="hidden" name="staff_id" value="<?=$staff->staff_id?>">
          <input type="hidden" name="user_id" value="<?= $staff->user_id?>">

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Private Email</label>
              <input type="email" name="private_email" value="<?= $staff->private_email ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">WhatsApp</label>
              <input type="hidden" name="name" value="<?= $staff->title .' '.$staff->fname.' '.$staff->lname ?>" class="form-control">
              <input type="text" name="whatsapp" value="<?= $staff->whatsapp ?>" class="form-control">
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Alternative Number</label>
              <input type="text" name="tel_2" value="<?= $staff->tel_2 ?>" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Primary Phone</label>
              <input type="text" name="tel_1" value="<?= $staff->tel_1 ?>" class="form-control">
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
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Upload New Photo (Max 1MB)</label>
              <input type="file" name="photo" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Upload Signature (Max 1MB)</label>
              <input type="file" name="signature" class="form-control">
            </div>
          </div>

          <div class="text-end">
            <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i> Save Changes</button>
          </div>
          <?= form_close(); ?>
        </div>
      </div>
    </div>
  </div>
</div>
