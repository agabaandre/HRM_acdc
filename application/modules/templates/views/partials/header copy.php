<header>
	<div class="topbar d-flex align-items-center">
		<nav class="navbar navbar-expand">
			<div class="topbar-logo-header">
				<div class="">
					<img src="<?php echo base_url() ?>assets/images/AU_CDC_Logo-800.png" width="200" style="filter: brightness(0) invert(1);">
				</div>

			</div>
			<div class=" mobile-toggle-menu"><i class='bx bx-menu'></i>
			</div>

			<div class="top-menu ms-auto">
				<ul class="navbar-nav align-items-center">
					<li class="nav-item mobile-search-icon">

					</li>

					<li class="nav-item dropdown dropdown-large">

						<div class="dropdown-menu dropdown-menu-end">

							<div class="header-notifications-list">


							</div>

						</div>
					</li>
					<li class="nav-item dropdown dropdown-large">

						<div class="dropdown-menu dropdown-menu-end">

							<div class="header-message-list">




							</div>

						</div>
					</li>
				</ul>
			</div>

			<div class="user-box dropdown">
				<a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
					<img src="<?php echo base_url() ?>assets/images/pp.png" class="user-img" alt="user avatar">
					<div class="user-info ps-3">
						<p class="user-name mb-0"><?php echo $this->session->userdata('user')->name; ?></p>
						<p class="designattion mb-0"></p>
					</div>
				</a>
				<ul class="dropdown-menu dropdown-menu-end">
					<li><a class="dropdown-item" href="<?php echo base_url() ?>/auth/profile"><i class="bx bx-user"></i><span>Profile</span></a>
					</li>
					<li><a class="dropdown-item" href="<?php echo base_url() ?>/auth/change_password"><i class="bx bx-cog"></i><span>Settings</span></a>
					</li>

					<li>
						<div class="dropdown-divider mb-0"></div>
					</li>
					<li><a class="dropdown-item" href="<?php echo base_url() ?>auth/logout"><i class="bx bx-log-out-circle"></i><span>Logout</span></a>
					</li>
				</ul>
			</div>

			

		</nav>
	</div>



</header>