<?php 
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$segment = $this->uri->segment(2);
?>

<div class="container">
  <ul class="nav nav-tabs mb-3" id="taskTabMenu" role="tablist">
    
    <?php if (in_array('79', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($segment == 'workplan') echo 'active'; ?>" href="<?= base_url('workplan'); ?>">
          <i class="bx bx-bar-chart-alt-2"></i> Workplan Plan
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('81', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($segment == 'activity') echo 'active'; ?>" href="<?= base_url('tasks/activity'); ?>">
          <i class="bx bx-task"></i> Sub-Activities
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('75', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($this->uri->segment(2) == 'weeklytasks') echo 'active'; ?>" href="<?= base_url('weeklytasks/weeklytasks'); ?>">
          <i class="bx bx-file"></i> Weekly Tasks
        </a>
      </li>
    <?php endif; ?>

    <?php if (in_array('79', $permissions)) : ?>
      <li class="nav-item" role="presentation">
        <a class="nav-link <?php if ($this->uri->segment(2) == 'calendar') echo 'active'; ?>" href="<?= base_url('weeklytasks/calendar'); ?>">
          <i class="bx bx-calendar"></i> Weely Task Calendar
        </a>
      </li>
    <?php endif; ?>



  </ul>
</div>
