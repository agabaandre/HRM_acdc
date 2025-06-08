<?php
$today = date('Y-m-d');

// Show Mid Term button if today is within the mid_term period
if (
    isset($ppa_settings->mid_term_start, $ppa_settings->mid_term_deadline) &&
    $today >= $ppa_settings->mid_term_start &&
    $today <= $ppa_settings->mid_term_deadline
): ?>
  <a href="<?= base_url("performance/midterm/midterm_review/{$ppa->entry_id}/{$staff_id}") ?>"
     class="btn btn-primary ms-2 btn-sm">
    <i class="fa fa-plus"></i> Mid Term
  </a>
<?php endif; ?>

<?php
// Show End Term button if today is within the end_term period
if (
    isset($ppa_settings->end_term_start, $ppa_settings->end_term_deadline) &&
    $today >= $ppa_settings->end_term_start &&
    $today <= $ppa_settings->end_term_deadline
): ?>
  <a href="<?= base_url("performance/endterm/end_term_review/{$ppa->entry_id}/{$staff_id}") ?>"
     class="btn btn-primary me-2 btn-sm">
    <i class="fa fa-plus"></i> End Term
  </a>
<?php endif; ?>
