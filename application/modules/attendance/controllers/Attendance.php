<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Attendance extends MX_Controller
{
	public  function __construct()
	{
		parent::__construct();
		$this->load->model('attendance_model','attend_mdl');
	}
	public function person()
	{
	  $data['title'] = "Person Logs";
	  $data['view'] = 'person_logs';
	  $data['module'] = "attendance";
	  $data['uptitle'] = "Person Attendance";
	  $data['staffs'] = $this->staff_mdl->get_active_staff_data();
	  echo Modules::run("templates/main", $data);
	}
	
}//end of class
