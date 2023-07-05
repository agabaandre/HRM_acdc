<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
require_once('partials/css_files.php');
require_once('partials/header.php');
include("partials/nav.php");
require_once('partials/breadcrumb.php');
//print_r($session);
$this->load->view($module . "/" . $view); 
require("partials/footer.php"); 