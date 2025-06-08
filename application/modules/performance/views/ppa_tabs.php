<?php 
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$staff_id = $this->session->userdata('user')->staff_id;
$current_period = str_replace(' ','-',current_period());
$ppa_entryid = md5($staff_id . '_' . str_replace(' ', '', $current_period));

//dd();
@$ppa_exists = $this->per_mdl->get_staff_plan_id($ppa_entryid);
// dd($staff_id);
?>
<div class="container">
    <ul class="nav nav-tabs mb-3" id="ppaTabMenu" role="tablist">
        <?php if (in_array('38', $permissions) && !$ppa_exists) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == '') echo 'active'; ?>" href="<?= base_url('performance'); ?>">
                    <i class="bx bx-plus-circle"></i> Create PPA
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions) && $ppa_exists) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'recent_ppa') echo 'active'; ?>" href="<?= base_url('performance/recent_ppa/' . $ppa_entryid . '/' . $this->session->userdata('user')->staff_id); ?>">
                    <i class="bx bx-file"></i> My Current PPA
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'midterm') echo 'active'; ?>" href="<?= base_url('performance/midterm'); ?>">
                    <i class="bx bx-collection"></i> Mid Term Review
                </a>
            </li>
        <?php endif; ?>


        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'my_ppas') echo 'active'; ?>" href="<?= base_url('performance/my_ppas'); ?>">
                    <i class="bx bx-collection"></i> My PPAs
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions)) : ?>
            <?php $pendingcount = count($this->per_mdl->get_pending_ppa($this->session->userdata('user')->staff_id)); ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'pending_approval') echo 'active'; ?>" href="<?= base_url('performance/pending_approval'); ?>">
                    <i class="bx bx-time-five"></i> Pending Action
                    <?php if ($pendingcount > 0): ?>
                        <span class="badge bg-danger"><?= $pendingcount ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'approved_by_me') echo 'active'; ?>" href="<?= base_url('performance/approved_by_me'); ?>">
                    <i class="bx bx-check-double"></i> All Approved PPAs
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>

