
<header>
	<div class="topbar d-flex align-items-center">
		<nav class="navbar navbar-expand">
			<div class="topbar-logo-header">
				<div class="">
					<img src="<?php echo base_url()?>assets/images/AU_CDC_Logo-800.png" width="200" style="filter: brightness(0) invert(1);">
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
				<a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret" href="#"
					role="button" data-bs-toggle="dropdown" aria-expanded="false">
					<img src="<?php echo base_url()?>assets/images/pp.png" class="user-img" alt="user avatar">
					<div class="user-info ps-3">
						<p class="user-name mb-0" style="color:#FFF !important; ">
							<?php echo $this->session->userdata('user')->name; ?>
						</p>
						<a href="<?php echo base_url()?>auth/logout" class="designattion mb-0" style="color:#FFF !important; ">Log Out</a>
					</div>
				</a>

			</div>
		</nav>
	</div>



</header>