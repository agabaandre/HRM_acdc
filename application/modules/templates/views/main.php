<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$staff_id = $this->session->userdata('user')->staff_id;
$current_period = str_replace(' ','-',current_period());
$ppa_entryid = md5($staff_id . '_' . str_replace(' ', '', $current_period));
@$ppa_exists = $this->per_mdl->get_staff_plan_id($ppa_entryid);

$defaultLangCode = $this->session->userdata('user')->langauge ?? 'en';


//dd($session);
//dd($ppa_exists);
require_once('partials/css_files.php');
require_once('partials/header.php');
include("partials/nav.php");
require_once('partials/breadcrumb.php');
//print_r($session);


//dd($ppa_exists);
$this->load->view($module . "/" . $view); 
require("partials/footer.php"); 