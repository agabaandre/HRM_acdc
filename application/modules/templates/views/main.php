<?php
$session = $this->session->userdata('user');
$permissions = $session->permissions;
$staff_id = $this->session->userdata('user')->staff_id;
$current_period = str_replace(' ','-',current_period());
$ppa_entryid = md5($staff_id . '_' . str_replace(' ', '', $current_period));
@$ppa_exists = $this->per_mdl->get_staff_plan_id($ppa_entryid);
$ppa_settings=ppa_settings();
@$ppa_exists = $this->per_mdl->get_staff_plan_id($ppa_entryid);
$today = date('Y-m-d');
//check if the ppa is approved
@$ppaIsapproved = $this->per_mdl->isapproved($ppa_entryid);
//check if midterm exists
$midterm_exists = $this->per_mdl->ismidterm_available($ppa_entryid);
$defaultLangCode = $this->session->userdata('user')->langauge ?? 'en';


//dd($session);
//dd($ppa_exists);
require_once('partials/css_files.php');
require_once('partials/header.php');
if($this->uri->segment(1)!='home'){
include("partials/nav.php");
}
require_once('partials/breadcrumb.php');
//print_r($session);cd 


//dd($ppa_exists);
$this->load->view($module . "/" . $view); 
require("partials/footer.php"); 