<div class="nav-container primary-menu">
			<div class="mobile-topbar-header">
				<div>
					<img src="<?php echo base_url()?>assets/images/au_emblem.png" class="logo-icon" alt="logo icon">
				</div>
				<div>
					<h5 class="logo-text">Staff Portal</h5>
				</div>
				<div class="toggle-icon ms-auto"><i class='bx bx-arrow-to-left'></i>
				</div>
			</div>
			<nav class="navbar navbar-expand-xl w-100">
				<ul class="navbar-nav justify-content-start flex-grow-1 gap-1">

                <!-- Dashboard -->
                <?php if (in_array('76', $permissions)) : ?>
                    <li class="nav-item">
                        <a href="<?= base_url('dashboard') ?>" class="nav-link <?= activelink('dashboard', $this->uri->segment(1)) ?>">
                            <div class="parent-icon"><i class="bx bx-category"></i></div>
                            <div class="menu-title">Staff Dashboard</div>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Staff Profile -->
                <?php if (in_array('72', $permissions) || in_array('41', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('staff', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-user"></i></div>
                        <div class="menu-title">Staff Profile</div>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (in_array('72', $permissions)) : ?>
                            <?php if (in_array('71', $permissions)) : ?>
								<li><a class="dropdown-item" href="<?= base_url('staff/search') ?>"><i class="bx bx-right-arrow-alt"></i>Quick Search</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/new') ?>"><i class="bx bx-right-arrow-alt"></i>Add New Staff</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/all_staff') ?>"><i class="bx bx-right-arrow-alt"></i>All Staff</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/index') ?>"><i class="bx bx-right-arrow-alt"></i>Current Staff List</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/2') ?>"><i class="bx bx-right-arrow-alt"></i>Contracts Due</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/3') ?>"><i class="bx bx-right-arrow-alt"></i>Contracts Expired</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/4') ?>"><i class="bx bx-right-arrow-alt"></i>Former Staff</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('staff/contract_status/7') ?>"><i class="bx bx-right-arrow-alt"></i>Under Renewal</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (in_array('41', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('staff/staff_birthday') ?>"><i class="bx bx-right-arrow-alt"></i>Staff Birthdays</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Attendance -->
                <?php if ($this->session->userdata('user')->staff_id != 0 && in_array('83', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('attendance', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-clock"></i></div>
                        <div class="menu-title">Attendance</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('attendance/upload') ?>"><i class="bx bx-right-arrow-alt"></i>Upload Data</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('attendance/person') ?>"><i class="bx bx-right-arrow-alt"></i>Person Attendance</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('attendance/status') ?>"><i class="bx bx-right-arrow-alt"></i>Time Logs</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('attendance/time_sheet') ?>"><i class="bx bx-right-arrow-alt"></i>Time Sheet</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Leave -->
                <?php if ($this->session->userdata('user')->staff_id != 0 && in_array('37', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('leave', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-plane-departure"></i></div>
                        <div class="menu-title">Leave Application</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('leave/request') ?>"><i class="bx bx-right-arrow-alt"></i>Apply</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('leave/approve_leave') ?>"><i class="bx bx-right-arrow-alt"></i>Approve Leave</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('leave/status') ?>"><i class="bx bx-right-arrow-alt"></i>My Leave Status</a></li>
                        <?php if (in_array('77', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('leave/status/all') ?>"><i class="bx bx-right-arrow-alt"></i>Leave Status</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Performance -->
                <?php if ($this->session->userdata('user')->staff_id != 0 && in_array('74', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('performance', $this->uri->segment(1)) ?>" href="<?php echo base_url()?>/performance/ppa_dashboard" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-line-chart"></i></div>
                        <div class="menu-title">Performance</div>
                    </a>
                    <ul class="dropdown-menu">
					   <?php if (in_array('38', $permissions) && !$ppa_exists) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('performance') ?>"><i class="bx bx-right-arrow-alt"></i>Create PPA</a></li>
                        <?php endif; ?>

   
                            <li><a class="dropdown-item" href="<?= base_url('performance/ppa_dashboard') ?>"><i class="bx bx-right-arrow-alt"></i>PPA Dashboard</a></li>
                  

                        <?php if (in_array('38', $permissions) && $ppa_exists) : ?>
                            <li><a class="dropdown-item" href="<?= base_url("performance/recent_ppa/{$ppa_entryid}/" . $this->session->userdata('user')->staff_id) ?>"><i class="bx bx-right-arrow-alt"></i>My Current PPA</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="<?= base_url('performance/my_ppas') ?>"><i class="bx bx-right-arrow-alt"></i>My PPAs</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('performance/pending_approval') ?>"><i class="bx bx-right-arrow-alt"></i>Pending Action <span class="badge bg-danger ms-1"><?= count($this->per_mdl->get_pending_ppa($this->session->userdata('user')->staff_id)) ?></span></a></li>
                        <li><a class="dropdown-item" href="<?= base_url('performance/approved_by_me') ?>"><i class="bx bx-right-arrow-alt"></i>All Approved PPAs</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Weekly Task Planner -->
                <?php if (in_array('78', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('weektasks', $this->uri->segment(1)) ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-bar-chart"></i></div>
                        <div class="menu-title">Weekly Task Planner</div>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if (in_array('79', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('workplan') ?>"><i class="bx bx-right-arrow-alt"></i>Workplan</a></li>
                        <?php endif; ?>
                        <?php if (in_array('81', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('tasks/activity') ?>"><i class="bx bx-right-arrow-alt"></i>Sub Activities</a></li>
                        <?php endif; ?>
                        <?php if (in_array('75', $permissions)) : ?>
                            <li><a class="dropdown-item" href="<?= base_url('weektasks/tasks') ?>"><i class="bx bx-right-arrow-alt"></i>Weekly Tasks</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Domain Controller -->
                <?php if (in_array('77', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-shield-alt"></i></div>
                        <div class="menu-title">Domain Controller</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('admanager/expired_accounts/3') ?>"><i class="bx bx-right-arrow-alt"></i>Accounts to Disable</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('admanager/report/3') ?>"><i class="bx bx-right-arrow-alt"></i>Disabled Accounts</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Settings -->
                <?php if (in_array('15', $permissions)) : ?>
                <li class="nav-item">
                    <a href="<?= base_url('settings') ?>" class="nav-link <?= activelink('settings', $this->uri->segment(1)) ?>">
                        <div class="parent-icon"><i class="fa fa-cog"></i></div>
                        <div class="menu-title">Settings</div>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Users -->
                <?php if (in_array('17', $permissions)) : ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= activelink('auth', $this->uri->segment(1), 'permissions') ?>" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-users"></i></div>
                        <div class="menu-title">Users</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('auth/users') ?>"><i class="bx bx-right-arrow-alt"></i>Manage Users</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('permissions') ?>"><i class="bx bx-right-arrow-alt"></i>User Permissions</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('auth/logs') ?>"><i class="bx bx-right-arrow-alt"></i>Audit Logs</a></li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>
        </div>
    </nav>
</div>
