<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Leave extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "leave";
		$this->load->model("leave_mdl",'leave_mdl');
	}

	public function index()
	{
		$data['module'] = $this->module;
		$data['title'] = "Leave";
		$data['staff'] = $this->staff_mdl->get_all();
		

		//dd($data['staff']);
		render('leave', $data);
	}
	public function request()
	{
		// Validate the form data
		$this->form_validation->set_rules('staff_id', 'Staff ID', 'required');
		$this->form_validation->set_rules('start_date', 'Start Date', 'required');
		$this->form_validation->set_rules('end_date', 'End Date', 'required');
		$this->form_validation->set_rules('leave_id', 'Leave Type', 'required');
		$this->form_validation->set_rules('requested_days', 'Requested Days', 'required');

		$data['module'] = $this->module;
		$data['title'] = "Leave Request";
		

		if ($this->form_validation->run() == FALSE) {
			$data['message'] = 'Leave Application Failed';
			render('leave', $data);
		} else {

			$leave_data = array(
				'staff_id' => $this->input->post('staff_id'),
				'start_date' => $this->input->post('start_date'),
				'end_date' => $this->input->post('end_date'),
				'leave_id' => $this->input->post('leave_id'),
				'requested_days' => $this->input->post('requested_days'),
				'remarks' => $this->input->post('remarks')
			);

			$this->leave_mdl->save_leave($leave_data);
			$data['message'] = "Leave Application Submmited";
			render('leave', $data);
		}
	}

	public function approve($request_id)
	{
		$this->leave_mdl->approve_leave($request_id);
		$leave = $this->leave_mdl->get_leave($request_id);
		$response['status'] = 'success';
		$response['leave'] = $leave;
		echo json_encode($response);
	}

	public function reject($leave_id)
	{
		$this->leave_mdl->approve_leave($leave_id);
		$leave = $this->leave_mdl->get_leave($leave_id);
		$response['status'] = 'success';
		$response['leave'] = $leave;
		echo json_encode($response);
	}

	public function approve_leave()
	{
		$data['module'] = $this->module;
		$data['title'] = "Approve / Reject Leave";
		// Get the leave data with filters
		$status = $this->input->get('status');
		$start_date = $this->input->get('start_date');
		$end_date = $this->input->get('end_date');
		$data['leaves'] = $this->leave_mdl->get_leaves($status, $start_date, $end_date);
		render('approval', $data);
	}

	public function status()
	{
		$data['module'] = $this->module;
		$data['title'] = "My Leave Status";
		// Get the filter values from the query parameters
		$data['module'] = $this->module;
		$data['title'] = "My Leave Status";
		$status = $this->input->get('status');
		$start_date = $this->input->get('start_date');
		$end_date = $this->input->get('end_date');

		// Get the leave data with filters and ordering
		$data['leaves'] = $this->leave_mdl->get_leaves($status, $start_date, $end_date);
		render('leave_status', $data);
	}



	
}
