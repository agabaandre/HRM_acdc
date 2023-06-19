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
	public function new()
	{
		$this->load->library('form_validation');
		$data['module'] = $this->module;
		$data['title'] = "New Staff";

		// Set validation rules for the form fields
		$this->form_validation->set_rules('SAPNO', 'SAP Number', 'required');
		$this->form_validation->set_rules('title', 'Title', 'required');
		$this->form_validation->set_rules('fname', 'First Name', 'required');
		$this->form_validation->set_rules('lname', 'Last Name', 'required');
		$this->form_validation->set_rules('oname', 'Other Name', 'required');
		$this->form_validation->set_rules('date_of_birth', 'Date of Birth', 'required');
		$this->form_validation->set_rules('gender', 'Gender', 'required');
		$this->form_validation->set_rules('nationality_id', 'Nationality', 'required');
		$this->form_validation->set_rules('initiation_date', 'Initiation Date', 'required');
		$this->form_validation->set_rules('tel_1', 'Telephone 1', 'required');
		$this->form_validation->set_rules('tel_2', 'Telephone 2');
		$this->form_validation->set_rules('whatsapp', 'WhatsApp');
		$this->form_validation->set_rules('work_email', 'Work Email', 'valid_email');
		$this->form_validation->set_rules('private_email', 'Private Email', 'valid_email');
		$this->form_validation->set_rules('physical_location', 'Physical Location', 'required');

		if ($this->form_validation->run() == FALSE) {
			// Validation failed, reload the form view with validation errors
			render('new_staff', $data);
		} else {
			// Validation passed, prepare the data for insertion
			$data = array(
				'SAPNO' => $this->input->post('SAPNO'),
				'title' => $this->input->post('title'),
				'fname' => $this->input->post('fname'),
				'lname' => $this->input->post('lname'),
				'oname' => $this->input->post('oname'),
				'date_of_birth' => $this->input->post('date_of_birth'),
				'gender' => $this->input->post('gender'),
				'nationality_id' => $this->input->post('nationality_id'),
				'initiation_date' => $this->input->post('initiation_date'),
				'tel_1' => $this->input->post('tel_1'),
				'tel_2' => $this->input->post('tel_2'),
				'whatsapp' => $this->input->post('whatsapp'),
				'work_email' => $this->input->post('work_email'),
				'private_email' => $this->input->post('private_email'),
				'physical_location' => $this->input->post('physical_location'),
			);

			// Call the model to save the data
			$result = $this->StaffModel->insert_staff($data);

			if ($result) {
				$data['message'] = 'Employee Record Saved';
				// Data inserted successfully, redirect to a success page or show a success message
				render('new_staff', $data);
			} else {
				// Failed to insert data, show an error message
				$data['error_message'] = 'Failed to save Employee. Please try again.';
				render('new_staff', $data);
			}
		}
	}

	
}
