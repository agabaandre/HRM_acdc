<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Attendance extends MX_Controller
{
	public  function __construct()
	{
		parent::__construct();
		$this->load->model('attendance_model');
		$this->departments = Modules::run("departments/getDepartments");
		$this->attendModule = "attendance";
		$this->watermark = FCPATH . "assets/images/watermark.png";
		//requires a join on ihrisdata
		$this->filters = Modules::run('filters/sessionfilters');
		//doesnt require a join on ihrisdata
		$this->ufilters = Modules::run('filters/universalfilters');
		// requires a join on ihrisdata with district level
		$this->distfilters = Modules::run('filters/districtfilters');
		$this->load->library('pagination');
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
