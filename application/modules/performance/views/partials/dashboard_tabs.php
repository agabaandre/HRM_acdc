<?php
// Determine current dashboard based on URI
$current_uri = uri_string();
$current_dashboard = '';

if (strpos($current_uri, 'performance/endterm/ppa_dashboard') !== false) {
    $current_dashboard = 'endterm';
} elseif (strpos($current_uri, 'performance/midterm/ppa_dashboard') !== false) {
    $current_dashboard = 'midterm';
} elseif (strpos($current_uri, 'performance/ppa_dashboard') !== false) {
    $current_dashboard = 'ppa';
} elseif (strpos($current_uri, 'dashboard') !== false && strpos($current_uri, 'performance') === false) {
    $current_dashboard = 'main';
}
?>

<!-- Dashboard Navigation Tabs -->
<div class="row mb-4">
  <div class="col-12">
    <ul class="nav nav-tabs" style="border-bottom: 2px solid #e0e0e0; background-color: #fff; border-radius: 8px 8px 0 0; padding: 0 10px;">
      <li class="nav-item">
        <a class="nav-link <?= $current_dashboard === 'main' ? 'active' : '' ?>" 
           href="<?= base_url('dashboard') ?>" 
           style="<?= $current_dashboard === 'main' ? 'color: #119A48; border-bottom: 3px solid #119A48; font-weight: 600; padding: 14px 24px; background-color: transparent;' : 'color: #6c757d; border-bottom: 3px solid transparent; padding: 14px 24px; transition: all 0.3s ease;' ?>">
          <i class="fa fa-home me-2"></i>Main Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $current_dashboard === 'ppa' ? 'active' : '' ?>" 
           href="<?= base_url('performance/ppa_dashboard') ?>" 
           style="<?= $current_dashboard === 'ppa' ? 'color: #119A48; border-bottom: 3px solid #119A48; font-weight: 600; padding: 14px 24px; background-color: transparent;' : 'color: #6c757d; border-bottom: 3px solid transparent; padding: 14px 24px; transition: all 0.3s ease;' ?>">
          <i class="fa fa-chart-pie me-2"></i>PPA Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $current_dashboard === 'midterm' ? 'active' : '' ?>" 
           href="<?= base_url('performance/midterm/ppa_dashboard') ?>" 
           style="<?= $current_dashboard === 'midterm' ? 'color: #119A48; border-bottom: 3px solid #119A48; font-weight: 600; padding: 14px 24px; background-color: transparent;' : 'color: #6c757d; border-bottom: 3px solid transparent; padding: 14px 24px; transition: all 0.3s ease;' ?>">
          <i class="fa fa-chart-bar me-2"></i>Midterm Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $current_dashboard === 'endterm' ? 'active' : '' ?>" 
           href="<?= base_url('performance/endterm/ppa_dashboard') ?>" 
           style="<?= $current_dashboard === 'endterm' ? 'color: #119A48; border-bottom: 3px solid #119A48; font-weight: 600; padding: 14px 24px; background-color: transparent;' : 'color: #6c757d; border-bottom: 3px solid transparent; padding: 14px 24px; transition: all 0.3s ease;' ?>">
          <i class="fa fa-chart-line me-2"></i>Endterm Dashboard
        </a>
      </li>
    </ul>
  </div>
</div>

<style>
  .nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
  }
  .nav-tabs .nav-link:hover:not(.active) {
    color: #119A48 !important;
    border-bottom-color: #a3e6b9 !important;
    background-color: #f8f9fa;
  }
  .nav-tabs .nav-link.active {
    color: #119A48 !important;
    border-bottom-color: #119A48 !important;
    background-color: transparent;
  }
  @media print {
    .nav-tabs {
      display: none !important;
    }
  }
</style>

