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
					// Prepare session data for token generation
					$sessionobj = $this->session->userdata('user');
					$permissions = $sessionobj->permissions;
					$session = (array) $sessionobj;
					$session['base_url'] = base_url();
					
					// APM URL
					$apmToken = '';
					$apmUrl = '';
					if (in_array('85', $permissions)) {
						$apmToken = urlencode(base64_encode(json_encode($session)));
						$apmUrl = $session['base_url'] . 'apm?token=' . $apmToken;
					}
					
					// Finance URL: Use PRODUCTION_URL/finance in production, localhost:3002 in development
					// Only show Finance link if user has permission 92
					$financeUrl = '';
					if (in_array('92', $permissions)) {
						$host = $_SERVER['HTTP_HOST'] ?? '';
						$financeToken = urlencode(base64_encode(json_encode($session)));
						if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
							$financeUrl = 'http://localhost:3002?token=' . $financeToken;
						} else {
							$productionUrl = $_ENV['PRODUCTION_URL'] ?? base_url();
							$financeUrl = rtrim($productionUrl, '/') . '/finance?token=' . $financeToken;
						}
					}
					?>
					
					<!-- APM Link -->
					<?php if (in_array('85', $permissions)) : ?>
					<li class="nav-item">
						<a 
							class="nav-link" 
							href="<?= $apmUrl ?>"
							target="_blank"
							rel="noopener noreferrer"
							style="font-size: 0.875rem;"
						>
							<i class='fa fa-sitemap' style="color:#FFF; font-size: 1.1rem;"></i>
							<span class="ms-2 d-none d-md-inline" style="color:#FFF; font-size: 0.875rem;">APM</span>
						</a>
					</li>
					<?php endif; ?>
					
					<!-- Finance Management Link -->
					<?php if (in_array('92', $permissions) && !empty($financeUrl)) : ?>
					<li class="nav-item">
						<a 
							class="nav-link" 
							href="<?= $financeUrl ?>"
							target="_blank"
							rel="noopener noreferrer"
							style="font-size: 0.875rem;"
						>
							<i class='bx bx-wallet' style="color:#FFF; font-size: 1.1rem;"></i>
							<span class="ms-2 d-none d-md-inline" style="color:#FFF; font-size: 0.875rem;">Finance</span>
						</a>
					</li>
					<?php endif; ?>
					
					<li class="nav-item  dropdown-large">
						<a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"> <i class='bx bx-category' style="color:#FFF;"></i>
						</a>
						<div class="dropdown-menu dropdown-menu-end">
							<div class="row row-cols-3 g-3 p-3">
								<div class="col text-center">
									<div class="app-box mx-auto bg-gradient-cosmic text-white"><i class='bx bx-group'></i>
									</div>
									<div class="app-title">Divisions</div>
								</div>
								<div class="col text-center">
									<div class="app-box mx-auto bg-gradient-burning text-white"><i class='bx bx-atom'></i>
									</div>
									<div class="app-title">Projects</div>
								</div>
								<div class="col text-center">
									<div class="app-box mx-auto bg-gradient-lush text-white"><i class='bx bx-shield'></i>
									</div>
									<div class="app-title">RCCS</div>
								</div>
								<div class="col text-center">
									<div class="app-box mx-auto bg-gradient-kyoto text-dark"><i class='bx bx-notification'></i>
									</div>
									<div class="app-title">Leave</div>
								</div>
								<div class="col text-center">
									<div class="app-box mx-auto bg-gradient-blues text-dark"><i class='bx bx-file'></i>
									</div>
									<div class="app-title">Appraisal</div>
								</div>
								<div class="col text-center">
									<div class="app-box mx-auto bg-gradient-moonlit text-white"><i class='bx bx-filter-alt'></i>
									</div>
									<div class="app-title">Travel</div>
								</div>
							</div>
						</div>
					</li>

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
					$image_path = base_url() . 'uploads/staff/' . $this->session->userdata('user')->photo;
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