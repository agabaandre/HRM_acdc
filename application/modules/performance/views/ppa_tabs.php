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
<style>
    /* Tab styling matching workplan tabs */
    .nav-tabs .nav-link {
        border: none;
        color: #6c757d;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        transition: all 0.3s ease;
    }

    .nav-tabs .nav-link:hover {
        border: none;
        color: rgba(52, 143, 65, 1);
        background-color: rgba(52, 143, 65, 0.1);
    }

    .nav-tabs .nav-link.active {
        color: rgba(52, 143, 65, 1);
        background-color: rgba(52, 143, 65, 0.1);
        border: none;
        border-bottom: 3px solid rgba(52, 143, 65, 1);
    }
</style>
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
                <a class="nav-link <?php if (($this->uri->segment(3) == 'recent_midterm')|| ($this->uri->segment(3) == 'midterm_review')) echo 'active'; ?>" href="<?= base_url('performance/midterm/recent_midterm/' . $ppa_entryid . '/' . $this->session->userdata('user')->staff_id); ?>">
                    <i class="bx bx-file"></i> Current Midterm
                </a>
            </li>
        <?php endif; ?>

        <?php 
        // @$endterm_exists = $this->per_mdl->isendterm_available($ppa_entryid);
        if (in_array('38', $permissions) && $ppa_exists) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if (($this->uri->segment(3) == 'recent_endterm')|| ($this->uri->segment(3) == 'endterm_review')) echo 'active'; ?>" href="<?= base_url('performance/endterm/recent_endterm/' . $ppa_entryid . '/' . $this->session->userdata('user')->staff_id); ?>">
                    <i class="bx bx-file"></i> Current Endterm
                </a>
            </li>
        <?php endif; ?>


        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(2) == 'my_ppas') echo 'active'; ?>" href="<?= base_url('performance/my_ppas'); ?>">
                    <i class="bx bx-collection"></i> PPAs
                </a>
            </li>
        <?php endif; ?>
        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(3) == 'my_midterms') echo 'active'; ?>" href="<?= base_url('performance/midterm/my_midterms'); ?>">
                    <i class="bx bx-collection"></i> Mid Term Reviews 
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(3) == 'my_endterms') echo 'active'; ?>" href="<?= base_url('performance/endterm/my_endterms'); ?>">
                    <i class="bx bx-collection"></i> End Term Reviews 
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
                <a class="nav-link <?php if ($this->uri->segment(3) == 'approved_by_me' && $this->uri->segment(2) == 'midterm') echo 'active'; ?>" href="<?= base_url('performance/midterm/approved_by_me'); ?>">
                    <i class="bx bx-check-double"></i> Approved Midterms
                </a>
            </li>
        <?php endif; ?>

        <?php if (in_array('38', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if ($this->uri->segment(3) == 'approved_by_me' && $this->uri->segment(2) == 'endterm') echo 'active'; ?>" href="<?= base_url('performance/endterm/approved_by_me'); ?>">
                    <i class="bx bx-check-double"></i> Approved Endterms
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>

