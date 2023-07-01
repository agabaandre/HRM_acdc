<?php
 $permissions = $this->session->userdata('user')->permissions;
require_once('partials/css_files.php');
require_once('partials/header.php');
include("partials/nav.php");
require_once('partials/breadcrumb.php');
$this->load->view($module . "/" . $view); 
require("partials/footer.php"); 