<?php
require_once('partials/css_files.php');
//require_once('partials/preloader.php');

?>

<body>
	<!--wrapper-->
	<div class="wrapper">
		<?php
		require_once('partials/header.php');
		?>

		<div class="nav-container primary-menu">
			<div class="mobile-topbar-header">
				<div>
					<img src="<?php echo base_url() ?>assets/images/africacdc_2.jpeg" class="logo-icon" alt="logo icon">
				</div>
			</div>
			<?php include("partials/nav.php"); ?>
		</div>
		<div class="page-wrapper">
			<div class="page-content">
				<div class="row">

					<div class="main-content" style="margin-right: 20px; margin-left: 20px; margin-top: 20px;">
						<div class="container-fluid">
							<div class="col-md-12">
								<?php //include'../controllers/add_users.php';?>
								<div class="card card-default">
									<div class="card-header">
										<div class="row">
											<div class="col-md-4">
												<h6><b>
														<?php echo $title ?>
													</b></h6>
											</div>
											<?php $this->load->view($module . "/" . $view); ?>
										</div>
									</div>

								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php require("partials/footer.php"); ?>