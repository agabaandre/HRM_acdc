		<!--navigation-->
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

					<li class="nav-item dropdown">
						<a href="<?php echo base_url() ?>dashboard" class="nav-link <?php echo activelink('dashboard', $this->uri->segment(1)) ?>">
							<div class="parent-icon"><i class="bx bx-category"></i>
							</div>
							<div class="menu-title">Staff Dashboard</div>
						</a>
					</li>


					<li class="nav-item dropdown has-arrow">
						<a href="#" class="nav-link <?php echo activelink('staff', $this->uri->segment(1)) ?> has-arrow">
							<div class="parent-icon"><i class='bx bx-user'></i>
							</div>
							<div class="menu-title">Staff</div>
						</a>
						<ul class="dropdown-menu">
							<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff"><i class="bx bx-right-arrow-alt"></i>Staff List</a>
							</li>
							<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff/new"><i class="bx bx-right-arrow-alt"></i>Add New Staff</a>
							</li>
							<li> <a class="dropdown-item" href="<?= base_url() ?>staff/contract_status/2"><i class="bx bx-right-arrow-alt"></i>Contracts Due</a>
							</li>
							<li> <a class="dropdown-item" href="<?= base_url() ?>staff/contract_status/3"><i class="bx bx-right-arrow-alt"></i>Contracts Expired</a>
							</li>
							<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff/staff_birthday"><i class="bx bx-right-arrow-alt"></i>Staff Birthdays</a>
							</li>
							<li> <a class="dropdown-item" href="<?php echo base_url() ?>staff/contract_status/4"><i class="bx bx-right-arrow-alt"></i>Former Staff</a>
							</li>
						</ul>
					</li>

					<li class="nav-item dropdown">
						<a href="<?php echo base_url() ?>leave/request" class="nav-link  <?php echo activelink('leave', $this->uri->segment(1)) ?>">
							<div class="parent-icon"><i class='bx bx-user'></i>
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
						</ul>
					</li>
					<li class="nav-item dropdown">
						<a href="<?php echo base_url() ?>leave" class="nav-link  <?php echo activelink('staff_report', $this->uri->segment(1)) ?>">
							<div class="parent-icon"><i class='bx bx-user'></i>
							</div>
							<div class="menu-title">Performance Appraisal</div>
						</a>
						<ul class="dropdown-menu">
							<li> <a class="dropdown-item" href="#"><i class="bx bx-right-arrow-alt"></i>Performance Plan</a>
							</li>
							<li> <a class="dropdown-item" href="#"><i class="bx bx-right-arrow-alt"></i>Performance Appraisal</a>
							</li>
						</ul>
					</li>
					<li class="nav-item dropdown">
						<a href="#" class="nav-link  <?php echo activelink('tasks', $this->uri->segment(1)) ?>">
							<div class="parent-icon"><i class='bx bx-user'></i>
							</div>
							<div class="menu-title">Task Analysis</div>
						</a>
						<ul class="dropdown-menu">
							<li> <a class="dropdown-item" href="#"><i class="bx bx-right-arrow-alt"></i>Weekly Tasks</a>
							</li>
						</ul>
					</li>

					<li class="nav-item dropdown">
						<a href="#" class="nav-link  <?php echo activelink('staff_report', $this->uri->segment(1)) ?>">
							<div class="parent-icon"><i class='bx bx-user'></i>
							</div>
							<div class="menu-title">Reports</div>
						</a>
						<ul class="dropdown-menu">
							<li> <a class="dropdown-item" href="#"><i class="bx bx-right-arrow-alt"></i>Staff Report</a>
							</li>
						</ul>
					</li>

					<li class="nav-item dropdown">
						<a href="" class="nav-link <?php echo activelink('system_settings', $this->uri->segment(1)) ?>">
							<div class="parent-icon"><i class="bx bx-line-chart"></i>
							</div>
							<div class="menu-title">Settings</div>
						</a>

					</li>
					<li class="nav-item dropdown">
						<a href="#" class="nav-link <?php echo activelink('change_password', $this->uri->segment(1)) ?>">
							<div class="parent-icon"><i class='bx bx-bookmark-heart'></i>
							</div>
							<div class="menu-title">Change Password</div>
						</a>

					</li>
				</ul>
			</nav>
		</div>
		<!--end navigation-->