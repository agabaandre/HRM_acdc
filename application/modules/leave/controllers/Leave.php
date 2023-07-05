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
		$data['module'] = $this->module;
		$data['title'] = "Leave Request";
		render('leave', $data);
	}

	public function request_leave()
	{
		$this->load->library('upload');
		if ($this->input->post()) {
			$leave_data = array(
				'staff_id' => $this->input->post('staff_id'),
				'start_date' => date('Y-m-d', strtotime($this->input->post('start_date'))),
				'end_date' => date('Y-m-d', strtotime($this->input->post('end_date'))),
				'leave_id' => $this->input->post('leave_id'),
				'contract_id' => $this->input->post('contract_id'),
				'supervisor_id' => $this->input->post('supervisor_id'),
				'supervisor2_id' => $this->input->post('supervisor2_id'),
				'division_head' => $this->input->post('division_head'),
				'supporting_staff' => $this->input->post('supporting_staff'),
				'email_leave' => $this->input->post('email_leave'),
				'mobile_leave' => $this->input->post('mobile_leave'),
				'requested_days' => $this->input->post('requested_days'),
				'supporting_documentation' => $this->input->post('supporting_staff'),
				'remarks' => $this->input->post('remarks')
			);

			$employee = $this->input->post('name');
			// For each get the file name and upload it
			$files = $_FILES;
			$doc = $files['document'];

			if (!empty($doc['name'])) {
				$doc['name'] = str_replace(' ', '_', $employee) . time() . pathinfo($doc['name'], PATHINFO_EXTENSION);
				$config['upload_path'] = './uploads/leave';
				$config['allowed_types'] = 'pdf|doc|docx|png|jpeg';
				$config['file_name'] = $doc['name'];
				$config['max_size'] = 2000;
				//2mb
				$this->upload->initialize($config);
				// If the upload fails, set the error message
				if (!$this->upload->do_upload('document')) {
					$this->session->set_flashdata('error', $this->upload->display_errors());
					$is_error = true;
				} else {
					// If the upload is successful, get the file name
					$photo = $this->upload->data('file_name');
					$leave_data['supporting_documentation'] = $photo;
				}
			}
			//dd($leave_data);

			$res = $this->leave_mdl->save_leave($leave_data);

			if ($res) {
				$msg = array(
					'msg' => $res,
					'type' => 'success'
				);
				Modules::run('utility/setFlash', $msg);
				redirect('leave/status');
			} else {
				$msg = array(
					'msg' => $res,
					'type' => 'error'
				);
			}
			render('leave/status');
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
		$staff_id = $this->session->userdata();
		$data['leaves'] = $this->leave_mdl->get_leaves($status, $start_date, $end_date,$staff_id);
		dd($data);
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
