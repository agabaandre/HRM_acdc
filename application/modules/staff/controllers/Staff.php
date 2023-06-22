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
		$data['staff'] = $this->staff_mdl->get_all();
		

		//dd($data['staff']);
		render('staff', $data);
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
			$data['success'] = 'Staff information saved successfully.';
			Modules::run('utility/setFlash', $data['success']);
		}
		else{
			$data['danger'] = 'Failed to Save';
			Modules::run('utility/setFlash', $data['danger']);

		}
		redirect('staff');
	}
	public function update_staff()
	{
		$data = $this->input->post();
		$q = $this->staff_mdl->update_staff($data);
		if ($q) {
			$data['success'] = 'Staff information saved successfully.';
			Modules::run('utility/setFlash', $data['success']);
		} else {
			$data['danger'] = 'Failed to Save';
			Modules::run('utility/setFlash', $data['danger']);

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
					$data['success'] = 'Staff information saved successfully.';
					Modules::run('utility/setFlash', $data['success']);
					redirect('staff/new');
				} else {
					// Failed to save contact or contract information
					$data['danger'] = 'Failed to save contract information. Please try again.';
					Modules::run('utility/setFlash', $data['danger']);
					redirect('staff/new');
				}
			} else {
				// Failed to save staff information
				$data['danger'] = 'Failed to save staff information. Please try again.';
				Modules::run('utility/setFlash', $data['danger']);
				redirect('self/new');
			}
		}
		render('new_staff', $data);

	}

	
}
