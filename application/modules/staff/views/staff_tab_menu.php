<?php
$current_uri = $this->uri->segment(2); // e.g. 'all_staff', 'contract_status'
$contract_status = $this->uri->segment(3);
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$staff_id = $this->session->userdata('user')->staff_id;
?>
<div class="container-fluid">
    <ul class="nav nav-tabs mb-3" id="staffTabMenu" role="tablist">
        <?php if (in_array('72', $permissions)) : ?>
            <?php if (in_array('71', $permissions)) : ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'search') ? 'active' : '' ?>" href="<?= base_url('staff/search') ?>">
                        <i class="fas fa-search "></i> Quick Search
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'new') ? 'active' : '' ?>" href="<?= base_url('staff/new') ?>">
                        <i class="fa fa-user-plus"></i> Add New Staff
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'all_staff') ? 'active' : '' ?>" href="<?= base_url('staff/all_staff') ?>">
                        <i class="fa fa-users"></i> All Staff
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'index') ? 'active' : '' ?>" href="<?= base_url('staff/index') ?>">
                        <i class="fa fa-address-book"></i> Current Staff
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'contract_status' && $contract_status == '2') ? 'active' : '' ?>" href="<?= base_url('staff/contract_status/2') ?>">
                        <i class="fa fa-hourglass-half"></i> Contracts Due
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'contract_status' && $contract_status == '3') ? 'active' : '' ?>" href="<?= base_url('staff/contract_status/3') ?>">
                        <i class="fa fa-calendar-times"></i> Contracts Expired
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'contract_status' && $contract_status == '4') ? 'active' : '' ?>" href="<?= base_url('staff/contract_status/4') ?>">
                        <i class="fa fa-user-slash"></i> Former Staff
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?= ($current_uri == 'contract_status' && $contract_status == '7') ? 'active' : '' ?>" href="<?= base_url('staff/contract_status/7') ?>">
                        <i class="fa fa-sync-alt"></i> Under Renewal
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (in_array('41', $permissions)) : ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($current_uri == 'staff_birthday') ? 'active' : '' ?>" href="<?= base_url('staff/staff_birthday') ?>">
                    <i class="fa fa-birthday-cake"></i> Birthdays
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>
