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
<header>
	<div class="topbar d-flex">
		<nav class="navbar navbar-expand">
			<div class="topbar-logo-header">
				<div>
					<img src="<?php echo base_url() ?>assets/images/AU_CDC_Logo-800.png" width="200" style="filter: brightness(0) invert(1);">
					<!-- <img src="<?php echo base_url() ?>assets/images/AU_CDC_Logo-800.png" width="200"> -->
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

					<li class="nav-item dropdown-large">
						<a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							<span class="alert-count" id="message-count">0</span>
							<i class='bx bx-comment' style="color:#FFF;"></i>
						</a>
						<div class="dropdown-menu dropdown-menu-end">
							<a href="javascript:;">
								<div class="msg-header">
									<p class="msg-header-title">Messages</p>
									<p class="msg-header-clear ms-auto">Marks all as read</p>
								</div>
							</a>
							<div class="header-message-list" id="ajax-messages">
								<!-- Messages will be loaded here via AJAX -->
							</div>
							<a href="<?= base_url('dashboard/all_messages'); ?>">
								<div class="text-center msg-footer">View All Messages</div>
							</a>
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