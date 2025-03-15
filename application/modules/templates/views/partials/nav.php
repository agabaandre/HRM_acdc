	
         
		<div class="nav-container primary-menu">
			<div class="mobile-topbar-header">
				<div>

					<img src="<?php echo base_url() ?>assets/images/AU_CDC_Logo-800.png" width="150" style="">

				</div>

				<div class="toggle-icon ms-auto"><i class='bx bx-arrow-to-left'></i>
				</div>
			</div>
			<nav class="navbar navbar-expand-xl w-100">
				<ul class="navbar-nav justify-content-start flex-grow-1 gap-1">
					<?php if (in_array('76', $permissions)) : ?>
						<li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>dashboard" class="nav-link <?php echo activelink('dashboard', $this->uri->segment(1)) ?>">
								<div class="parent-icon"><i class="bx bx-category"></i>
								</div>
								<div class="menu-title">Staff Dashboard</div>
							</a>
						</li>
					<?php endif; ?>
					<li class="nav-item dropdown has-arrow">
						<a href="<?php echo base_url() ?>auth/profile" class="nav-link <?php echo activelink('staff', $this->uri->segment(1)) ?> has-arrow">
							<div class="parent-icon"><i class='fa fa-user'></i>
							</div>
							<div class="menu-title">Staff Profile</div>
						</a>
						<ul class="dropdown-menu">
							<?php if (in_array('72', $permissions)) : ?>
								<?php if (in_array('71', $permissions)) : ?>
									

									<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff/new"><i class="bx bx-right-arrow-alt"></i>Add New Staff</a>
									</li>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff/all_staff"><i class="bx bx-right-arrow-alt"></i>All Staff(Active, Due,Expired,Under Renewal)</a>
									</li>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff"><i class="bx bx-right-arrow-alt"></i>Current Staff List</a>
									</li>
									
									<li> <a class="dropdown-item" href="<?= base_url() ?>staff/contract_status/2"><i class="bx bx-right-arrow-alt"></i>Contracts Due</a>
									</li>
									<li> <a class="dropdown-item" href="<?= base_url() ?>staff/contract_status/3"><i class="bx bx-right-arrow-alt"></i>Contracts Expired</a>
									</li>
									<li> <a class="dropdown-item" href="<?= base_url() ?>staff/contract_status/4"><i class="bx bx-right-arrow-alt"></i>Former Staff</a>
									</li>
									<li> <a class="dropdown-item" href="<?= base_url() ?>staff/contract_status/7"><i class="bx bx-right-arrow-alt"></i>Under Renewal</a>
									</li>
								
								<?php endif; ?>
							<?php endif; ?>
							<?php if (in_array('41', $permissions)) : ?>

								<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff/staff_birthday"><i class="bx bx-right-arrow-alt"></i>Staff Birthdays</a>
								</li>
							<?php endif; ?>


						</ul>
					</li>
<?php if (($this->session->userdata('user')->staff_id != 0) && (in_array('83', $permissions))) : ?>
<li class="nav-item dropdown">
	<a href="<?php echo base_url() ?>attendance/person" class="nav-link  <?php echo activelink('attendance', $this->uri->segment(1)) ?>">
		<div class="parent-icon"><i class="fa fa-clock"></i>

		</div>
		<div class="menu-title">Attendance</div>
	</a>
	<ul class="dropdown-menu">
		<li> <a class="dropdown-item" href="<?php echo base_url() ?>attendance/upload"><i class="bx bx-right-arrow-alt"></i>Upload Data</a>
		</li>

		<li> <a class="dropdown-item" href="<?php echo base_url() ?>attendance/person"><i class="bx bx-right-arrow-alt"></i>Person Attendance</a>
		</li>

		<li> <a class="dropdown-item" href="<?php echo base_url() ?>attendance/status"><i class="bx bx-right-arrow-alt"></i>Time Logs</a>
		</li>
	
			<li> <a class="dropdown-item" href="<?php echo base_url() ?>attendance/time_sheet"><i class="bx bx-right-arrow-alt"></i>Time Sheet</a>
			</li>

	</ul>
</li>
<?php endif; ?>
					<?php if (($this->session->userdata('user')->staff_id != 0) && (in_array('37', $permissions))) : ?>

						<li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>leave/status" class="nav-link  <?php echo activelink('leave', $this->uri->segment(1)) ?>">
								<div class="parent-icon"><i class="fa fa-plane-departure"></i>

								</div>
								<div class="menu-title">Leave Application</div>
							</a>
							<ul class="dropdown-menu">
								<li> <a class="dropdown-item" href="<?php echo base_url() ?>leave/request"><i class="bx bx-right-arrow-alt"></i>Apply</a>
								</li>

								<li> <a class="dropdown-item" href="<?php echo base_url() ?>leave/approve_leave"><i class="bx bx-right-arrow-alt"></i>Approve Leave</a>
								</li>

								<li> <a class="dropdown-item" href="<?php echo base_url() ?>leave/status"><i class="bx bx-right-arrow-alt"></i>My Leave Status</a>
								</li>
								<?php if (in_array('77', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>leave/status/all"><i class="bx bx-right-arrow-alt"></i>Leave Status</a>
									</li>
								<?php endif; ?>
							</ul>
						</li>
					<?php endif; ?>

					<?php if (($this->session->userdata('user')->staff_id != 0) && (in_array('74', $permissions))) : ?>
						<li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>performance" class="nav-link  <?php echo activelink('staff_report', $this->uri->segment(1)) ?>">
								<div class="parent-icon"><i class='fa fa-line-chart'></i>
								</div>
								<div class="menu-title">Performance Appraisal</div>
							</a>
							<ul class="dropdown-menu">
								<?php if (in_array('38', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>performance"><i class="bx bx-right-arrow-alt"></i>Submit Plan</a>
									</li>
								<?php endif; ?>
								<?php if (in_array('38', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>performance/myplans"><i class="bx bx-right-arrow-alt"></i>My Plans</a>
									</li>
								<?php endif; ?>
								<?php if (in_array('38', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>performance/approvals"><i class="bx bx-right-arrow-alt"></i>Approvals</a>
									</li>
								<?php endif; ?>

							</ul>
						</li>
					<?php endif; ?>
					<?php if  (in_array('77', $permissions)): ?>
						<li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>admanager/expired_accounts/3" class="nav-link  <?php echo activelink('staff_report', $this->uri->segment(1)) ?>">
								<div class="parent-icon"><i class='fa fa-line-chart'></i>
								</div>
								<div class="menu-title">Domain Controller</div>
							</a>
							<ul class="dropdown-menu">
								<?php if (in_array('77', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>admanager/expired_accounts/3"><i class="bx bx-right-arrow-alt"></i>Accounts to Disable</a>
									</li>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>admanager/report/3"><i class="bx bx-right-arrow-alt"></i>Disabled Accounts</a>
									</li>
								<?php endif; ?>
				
							</ul>
						</li>
					<?php endif; ?>

					<?php if  (in_array('78', $permissions)): ?>
						<li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>performance" class="nav-link  <?php echo activelink('staff_report', $this->uri->segment(1)) ?>">
								<div class="parent-icon"><i class='fa fa-bar-chart'></i>
								</div>
								<div class="menu-title">Planner</div>
							</a>
							<ul class="dropdown-menu">
							<!-- <?php if (in_array('79', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>taskplanner/calendar"><i class="bx bx-right-arrow-alt"></i>Calendar</a>
									</li>
								<?php endif; ?> -->
								<?php if (in_array('79', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>tasks/outputs"><i class="bx bx-right-arrow-alt"></i>Outputs</a>
									</li>
								<?php endif; ?>
								<?php if (in_array('81', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>tasks/activity"><i class="bx bx-right-arrow-alt"></i>Activities</a>
									</li>
								<?php endif; ?>
								<?php if (in_array('75', $permissions)) : ?>
									<!-- <li> <a class="dropdown-item" href="<?php echo base_url() ?>tasks/approve_activities"><i class="bx bx-right-arrow-alt"></i>Approve Activities</a>
									</li> -->
								<?php endif; ?>
								<?php if (in_array('75', $permissions)) : ?>
									<li> <a class="dropdown-item" href="<?php echo base_url() ?>tasks/view_reports"><i class="bx bx-right-arrow-alt"></i>Weekly Report</a>
									</li>
								<?php endif; ?>

							</ul>
						</li>
					<?php endif; ?>
					<?php if (in_array('15', $permissions)) : ?>

						<!-- <li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>reports" class="nav-link  <?php echo activelink('staff_report', $this->uri->segment(1)) ?>">
								<div class="parent-icon"><i class='fa fa-th'></i>
								</div>
								<div class="menu-title">Reports</div>
							</a>

						</li> -->
					<?php endif; ?>
					<?php if (in_array('15', $permissions)) : ?>

						<li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>settings" class="nav-link <?php echo activelink('settings', $this->uri->segment(1)) ?>">
								<div class="parent-icon"><i class="fa fa-cog"></i>
								</div>
								<div class="menu-title">Settings</div>
							</a>

						</li>
					<?php endif; ?>
					<?php if (in_array('17', $permissions)) : ?>

						<li class="nav-item dropdown">
							<a href="<?php echo base_url() ?>auth/users" class="nav-link <?php echo activelink('auth', $this->uri->segment(1), 'permissions') ?>">
								<div class="parent-icon"><i class='fa fa-users'></i>
								</div>
								<div class="menu-title">Users</div>
							</a>
							<ul class="dropdown-menu">
								<li> <a class="dropdown-item" href="<?php echo base_url() ?>auth/users"><i class="bx bx-right-arrow-alt"></i>Manage Users</a>
								</li>
								<li> <a class="dropdown-item" href="<?php echo base_url() ?>permissions"><i class="bx bx-right-arrow-alt"></i>User Permissions</a>
								</li>
								<li> <a class="dropdown-item" href="<?php echo base_url() ?>auth/logs"><i class="bx bx-right-arrow-alt"></i>Audit Logs</a>
								</li>
							
							
							</ul>
						


						</li>
					<?php endif; ?>
				</ul>
			</nav>
		</div>
		<!--end navigation-->
