<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Staff extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "staff";
		$this->load->model("staff_mdl",'staff_mdl');
	}

	public function index()
	{

		$data['module'] = $this->module;
		$data['title'] = "Staff";
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->post();
		$data['staffs'] = $this->staff_mdl->get_staff_data($per_page = 20, $page, $filters);
		$data['links'] = pagination('staff/index', count($data['staffs']), 3);
		render('staff_ajax', $data);
	}
	// }
	// public function get_staff_data_ajax()
	// {
	// 	$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
	// 	$filters = $this->input->post();
	// 	$data['staffs'] = $this->staff_mdl->get_staff_data($per_page = 20, $page, $filters);
	// 	$data['links'] = pagination('staff/index', count($data['staffs']), 3);
	// 	$html_content = $this->load->view('staff_ajax', $data, true);
	// 	$this->output
	// 		->set_content_type('application/json')
	// 		->set_output(json_encode(['html' => $html_content]));

		
	// }
	// Getting All Contracts
	public function staff_contracts($staff_id)
	{
		$data['module'] = $this->module;
		$filters = array('staff_id' => $staff_id);
		$data['this_staff'] = $this->staff_mdl->get_staff_data($start=1, $limit=0, $filters);
		$data['contracts'] = $this->staff_mdl->get_staff_contracts($staff_id);
		$data['title'] = $data['this_staff']['lname']." ".$data['this_staff']['fname']." | SAPNO: ".$data['this_staff']['SAPNO'];
		render('staff_contracts', $data);
	}
	// Getting latest Contract
	public function latest_staff_contract($staff_id)
	{
		$data = $this->staff_mdl->get_latest_contracts($staff_id);
		//dd($data);
		return $data;
	}

	// New Contract
	public function new_contract($staff_id){
		$data['module'] = $this->module;
		$data['title'] = "New Contract";
		$data['staff_id'] = $staff_id;
		$data['staffs'] = $this->staff_mdl->get_all();
		
		render('new_contract', $data);
	}

	// Add New Contract
	public function add_new_contract(){
		$data['module'] = $this->module;
		$staff_id = $this->input->post('staff_id');
		$this->staff_mdl->add_new_contract('staff_contracts');
		redirect('staff/staff_contracts/'.$staff_id);
	}

	// Renew Contract
	public function renew_contract($staff_contract_id, $staff_id){
		$data['module'] = $this->module;
		$renew = 1;
		$data['renewed_contract'] = $this->staff_mdl->renew_contract('staff_contracts', $staff_contract_id, $renew);
		redirect('staff/staff_contracts/'.$staff_id);
	}

	// End Contract
	public function end_contract($staff_contract_id, $staff_id){
		$data['module'] = $this->module;
		$end = 3;
		$data['ended_contract'] = $this->staff_mdl->end_contract('staff_contracts', $staff_contract_id, $end);
		redirect('staff/staff_contracts/'.$staff_id);
	}
	

	public function contract_status($status){
		$data['module'] = $this->module;
		if ($status == 2) {
			$data['title'] = "Due Contracts";
		}
		else if ($status == 3) {
			$data['title'] = "Expired Contracts";
		} 
		else if ($status == 4) {
			$data['title'] = "Former Staff";
		}
		$data['staff'] = $this->staff_mdl->get_status($status);
	
		render('contract_status', $data);

	}
	public function staff_birthday()
	{
		$data['module'] = $this->module;
		$data['title'] = "Staff Birthday";
		$data['today'] = $this->staff_mdl->getBirthdaysForToday();
		$data['tomorrow'] = $this->staff_mdl->getBirthdaysForTomorrow();
		$data['week'] = $this->staff_mdl->getBirthdaysForNextSevenDays();
		$data['month'] = $this->staff_mdl->getBirthdaysForNextThirtyDays();
		//dd($data['month']);
		render('staff_birthday', $data);
	}

	public function update_contract()
	{
		$data = $this->input->post();
		$q= $this->staff_mdl->update_contract($data);
		if ($q) {
			$msg = array(
				'msg' => 'Staff Updated successfully.',
				'type' => 'success'
			);
		}
		else{
			$msg = array(
				'msg' => 'Updated Failed!.',
				'type' => 'error'
			);

		}
		redirect('staff');
	}
	public function update_staff()
	{
		$data = $this->input->post();
		$q = $this->staff_mdl->update_staff($data);
		if ($q) {
			$msg = array(
				'msg' => 'Staff Updated successfully.',
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
		} else {
			$msg = array(
				'msg' => 'Staff update Failed .',
				'type' => 'error'
			);
			Modules::run('utility/setFlash', $msg);

		}
		redirect('staff');
	}

	public function new()
	{
		// Personal Information
			$data['module'] = $this->module;
			$data['title'] = "New Staff";
		if ($this->input->post()) {
			
			$sapno = $this->input->post('SAPNO');
			$title = $this->input->post('title');
			$fname = $this->input->post('fname');
			$lname = $this->input->post('lname');
			$oname = $this->input->post('oname');
			$dob = date('Y-m-d', strtotime($this->input->post('date_of_birth')));
			$gender = $this->input->post('gender');
			$nationality_id = $this->input->post('nationality_id');
			$initiation_date = date('Y-m-d', strtotime( $this->input->post('initiation_date')));

			// Contact Information
			$tel_1 = $this->input->post('tel_1');
			$tel_2 = $this->input->post('tel_2');
			$whatsapp = $this->input->post('whatsapp');
			$work_email = $this->input->post('work_email');
			$private_email = $this->input->post('private_email');
			$physical_location = $this->input->post('physical_location');

			// Contract Information
			$job_id = $this->input->post('job_id');
			$job_acting_id = $this->input->post('job_acting_id');
			$grade_id = $this->input->post('grade_id');
			$contracting_institution_id = $this->input->post('contracting_institution_id');
			$funder_id = $this->input->post('funder_id');
			$first_supervisor = $this->input->post('first_supervisor');
			$second_supervisor = $this->input->post('second_supervisor');
			$contract_type_id = $this->input->post('contract_type_id');
			$duty_station_id = $this->input->post('duty_station_id');
			$division_id = $this->input->post('division_id');
			$start_date = date('Y-m-d',strtotime($this->input->post('start_date')));
			$end_date = date('Y-m-d', strtotime($this->input->post('end_date')));
			$status_id = $this->input->post('status_id');
			$file_name = $this->input->post('file_name');
			$comments = $this->input->post('comments');

			// Save to database
			$staff_id = $this->staff_mdl->add_staff($sapno, $title, $fname, $lname, $oname, $dob, $gender, $nationality_id, $initiation_date,$tel_1,$tel_2,$whatsapp ,$work_email,$private_email,$physical_location);

			if ($staff_id) {
				$contract_id = $this->staff_mdl->add_contract_information($staff_id, $job_id, $job_acting_id, $grade_id, $contracting_institution_id, $funder_id, $first_supervisor, $second_supervisor, $contract_type_id, $duty_station_id, $division_id, $start_date, $end_date, $status_id, $file_name, $comments);
				if ($contract_id) {
					// Successfully saved staff, contact, and contract information
					$msg = array(
						'msg' => 'Staff information saved successfully.',
						'type' => 'success'
					);
					Modules::run('utility/setFlash', $msg);
					redirect('staff/new');
				} else {
					// Failed to save contact or contract information
					$msg = array(
						'msg' =>'Failed, please Retry',
						'type' => 'error'
					);
					Modules::run('utility/setFlash', $msg);
					redirect('staff/new');
				}
			} else {
				// Failed to save staff information
				$msg = array(
					'msg' => 'Failed, please Retry',
					'type' => 'error'
				);
				Modules::run('utility/setFlash', $msg);
				redirect('self/new');
			}
		}
		render('new_staff', $data);

	}

	
}
