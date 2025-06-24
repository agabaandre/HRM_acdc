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
                    <i class="bx bx-file"></i> Current PPA
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions) && $ppa_exists) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(3) == 'recent_midterm') echo 'active'; ?>" href="<?= base_url('performance/midterm/recent_midterm/' . $ppa_entryid . '/' . $this->session->userdata('user')->staff_id); ?>">
                    <i class="bx bx-file"></i> Current Midterm
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
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(3) == 'my_midterms') echo 'active'; ?>" href="<?= base_url('performance/midterm/my_midterms'); ?>">
                    <i class="bx bx-collection"></i> My Mid Term Reviews 
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions)) : ?>
            <?php 
                // Use controller-provided variable if available, else fallback to model call
                if (isset($pendingcount)) {
                    $pendingcount_val = $pendingcount;
                } else {
                    $pendingcount_val = 0;
                    if (method_exists($this->per_mdl, 'get_all_pending_approvals')) {
                        $pending = $this->per_mdl->get_all_pending_approvals($this->session->userdata('user')->staff_id);
                        $pendingcount_val = is_array($pending) ? count($pending) : 0;
                    } elseif (method_exists($this->per_mdl, 'get_pending_ppa')) {
                        $pending = $this->per_mdl->get_pending_ppa($this->session->userdata('user')->staff_id);
                        $pendingcount_val = is_array($pending) ? count($pending) : 0;
                    }
                }
            ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'pending_approval') echo 'active'; ?>" href="<?= base_url('performance/pending_approval'); ?>">
                    <i class="bx bx-time-five"></i> Pending Action
                    <?php if ($pendingcount_val > 0): ?>
                        <span class="badge bg-danger"><?= $pendingcount_val ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'approved_by_me') echo 'active'; ?>" href="<?= base_url('performance/approved_by_me'); ?>">
                    <i class="bx bx-check-double"></i> Approved PPAs
                </a>
            </li>
        <?php endif; ?>
        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(3) == 'approved_by_me') echo 'active'; ?>" href="<?= base_url('performance/midterm/approved_by_me'); ?>">
                    <i class="bx bx-check-double"></i> Approved Midterms
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>

