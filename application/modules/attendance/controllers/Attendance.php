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
	public function timelogs($id = FALSE, $print = false, $from = false, $to = false)
  {


    $post = $this->input->post();
    if ($post) {


      $search_data = $this->input->post();

      $data['from'] = $search_data['date_from'];
      $data['to'] = $search_data['date_to'];
    } else {

      $data['from'] = date('Y-m-') . '01';
      $data['to'] = date('Y-m-d');
      $search_data['date_from'] = $data['from'];
      $search_data['date_to'] = $data['to'];
    }

    $dbresult = $this->empModel->getEmployeeTimeLogs(urldecode($id), 10000, 0, $search_data);
    $data['timelogs'] = $dbresult['timelogs'];


    $data['title'] = "Time Logs";
    //$data['facilities']=Modules::run("facilities/getFacilities");
    $data['view'] = 'individual_time_logs';

    $data['module'] = "employees";
    echo Modules::run("templates/main", $data);
  }

	
}//end of class
