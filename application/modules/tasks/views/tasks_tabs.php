<?php 
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$segment = $this->uri->segment(2);
?>

<div class="container">
  <ul class="nav nav-tabs mb-3" id="taskTabMenu" role="tablist">
    
    <?php if (in_array('79', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($segment == 'outputs') echo 'active'; ?>" href="<?= base_url('tasks/outputs'); ?>">
          <i class="bx bx-bar-chart-alt-2"></i> Outputs
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('81', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($segment == 'activity') echo 'active'; ?>" href="<?= base_url('tasks/activity'); ?>">
          <i class="bx bx-task"></i> Activities
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('75', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($segment == 'view_reports') echo 'active'; ?>" href="<?= base_url('tasks/view_reports'); ?>">
          <i class="bx bx-file"></i> Weekly Report
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('79', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($segment == 'calendar') echo 'active'; ?>" href="<?= base_url('taskplanner/calendar'); ?>">
          <i class="bx bx-calendar"></i> Calendar
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('75', $permissions)) : ?>
      <!-- Uncomment this if "Approve Activities" needs to be shown -->
      <!--
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($segment == 'approve_activities') echo 'active'; ?>" href="<?= base_url('tasks/approve_activities'); ?>">
          <i class="bx bx-check-circle"></i> Approve Activities
        </a>
      </li>
      -->
    <?php endif; ?>

  </ul>
</div>
