<!--start header -->
<style>
	.modal.modal-bottom .modal-dialog {
		position: absolute;
		bottom: 0;
		left: 0;
		right: 0;
		margin: 0 auto;
		max-height: 90vh;
		transition: transform 0.3s ease-out;
	}

	.modal.fade .modal-dialog.modal-bottom {
		transform: translateY(100%);
	}

	.modal.fade.show .modal-dialog.modal-bottom {
		transform: translateY(0);
	}
</style>
<style>
	.breadcrumb-sm {
		font-size: 0.8rem;
	}

	.goog-te-banner-frame.skiptranslate,
	.goog-logo-link,
	.VIpgJd-ZVi9od-ORHb-OEVmcd,
	.goog-te-gadget-icon,
	div.feedback-form-container,
	div.feedback-prompt {
		display: none !important;
	}
</style>

<header>
	<div class="topbar d-flex">
		<nav class="navbar navbar-expand">
			<div class="topbar-logo-header">
				<div class="">
					<img src="<?php echo base_url() ?>assets/images/AU_CDC_Logo-800.png" width="200" style="filter: brightness(0) invert(1);">

				</div>

			</div>
			<div class="mobile-toggle-menu"><i class='bx bx-menu'></i></div>
			<div class="search-bar flex-grow-1" style="display:none;">
				<div class="position-relative search-bar-box">
					<input type="text" class="form-control search-control" placeholder="Type to search..."> <span class="position-absolute top-50 search-show translate-middle-y"><i class='bx bx-search'></i></span>
					<span class="position-absolute top-50 search-close translate-middle-y"><i class='bx bx-x'></i></span>
				</div>
			</div>
			<div class="top-menu ms-auto">
				<ul class="navbar-nav align-items-center">
					<?php
					$sessionobj = $this->session->userdata('user');
					$cbp_current_uri = trim((string) $this->uri->uri_string(), '/');
					$cbp_nav_home = [
						'id' => 'cbp_home',
						'label' => 'CBP Home',
						'description' => '',
						'href' => site_url('home/index'),
						'is_active' => ($cbp_current_uri === 'home/index' || $cbp_current_uri === 'home'),
					];
					$cbp_nav_modules = [];
					if ($sessionobj) {
						$session = (array) $sessionobj;
						$session['base_url'] = base_url();
						$this->load->model('cbp_modules_mdl');
						if ($this->cbp_modules_mdl->table_exists()) {
							$this->cbp_modules_mdl->seed_defaults_if_empty();
							$cbp_payload = $this->cbp_modules_mdl->get_api_nav_payload(
								$sessionobj,
								$session,
								'',
								'',
								$cbp_current_uri
							);
							$cbp_nav_home = $cbp_payload['home'];
							$cbp_nav_modules = $cbp_payload['modules'];
						}
					}
					include __DIR__ . '/cbp_modules_dropdown.php';
					?>

					<!-- Notification Icon with Counter -->
					<li class="nav-item dropdown" style="border:none !important;">
					    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							<span class="alert-count" id="message-count">0</span>
							<i class='bx bx-comment' style="color:#FFF;"></i>
						</a>

						<!-- Dropdown -->
						<div class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3" style="min-width: 340px;">
							<div class="dropdown-header d-flex justify-content-between align-items-center px-3 pt-2">
								<span class="fw-semibold">Messages</span>
								<a href="javascript:;" class="small text-muted">Mark all as read</a>
							</div>

							<!-- Message List -->
							<div class="header-message-list ps-2 pe-2 pt-2" id="ajax-messages" style="max-height: 300px; overflow-y: auto;">
								<!-- Messages will be injected here via JS -->
								<div class="text-center text-muted py-3">Loading messages...</div>
							</div>

							<!-- Footer -->
							<div class="dropdown-footer text-center border-top py-2">
								<a href="<?= base_url('dashboard/all_messages'); ?>" class="text-decoration-none">View All Messages</a>
							</div>
						</div>
					</li>


				</ul>
			</div>
			<div class="user-box dropdown">
				<a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">

					<?php
					$full_name = $this->session->userdata('user')->name;
					$name_parts = explode(" ", trim($full_name), 2);
					$surname = isset($name_parts[1]) ? $name_parts[1] : '';
					$other_name = $name_parts[0];
					$image_path = staff_secure_upload_url('photo', $this->session->userdata('user')->photo ?? '');
					$photo = $this->session->userdata('user')->photo;
					echo  $staff_photo = generate_user_avatar($other_name, $surname, $image_path, $photo);

					?>

					<div class="user-info ps-3">
						<p class="user-name mb-0"><?php echo $this->session->userdata('user')->name; ?></p>
						<p class="designattion mb-0"></p>
					</div>
				</a>
				<ul class="dropdown-menu dropdown-menu-end">
					<li><a class="dropdown-item" href="<?php echo base_url() ?>auth/profile"><i class="bx bx-user"></i><span>Profile</span></a>
					</li>
					<li><a class="dropdown-item" href="<?php echo base_url() ?>auth/change_password"><i class="bx bx-cog"></i><span>Change Password</span></a>
					</li>

					<li>
						<div class="dropdown-divider mb-0"></div>
					</li>
					<?php if ($this->session->userdata('original_user')): ?>
						<a href="<?php echo site_url('auth/revert'); ?>" class="btn btn-sm btn-danger">
							<i class="fa fa-undo"></i> Revert to Admin
						</a>
					<?php endif; ?>
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
<!--end header -->