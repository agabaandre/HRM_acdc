<?php
require_once('partials/css_files.php');

require_once('partials/header.php');
include("partials/nav.php");
require_once('partials/breadcrumb.php');
?>

<!-- <div class="nav-container primary-menu">
			<div class="mobile-topbar-header">
				<div>
					<img src="<?php //echo base_url() 
								?>assets/images/africacdc_2.jpeg" class="logo-icon" alt="logo icon">
				</div>
			</div> -->


<?php $this->load->view($module . "/" . $view); ?>

<?php require("partials/footer.php"); ?>