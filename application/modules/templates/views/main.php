<?php
require_once('partials/css_files.php');

require_once('partials/header.php');
include("partials/nav.php");
require_once('partials/breadcrumb.php');
?>


<?php $this->load->view($module . "/" . $view); ?>

<?php require("partials/footer.php"); ?>