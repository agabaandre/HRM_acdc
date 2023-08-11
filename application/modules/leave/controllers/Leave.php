<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Leave extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "leave";
		$this->load->model("leave_mdl", 'leave_mdl');
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

	public function approve($request_id, $role, $action)
	{

		if ($action == 16) {
			$message = 'Approved';
		}
		if ($action == 32) {
			$message = 'Rejected';
		}
		//dd($role);
		$res = $this->leave_mdl->approve_leave($request_id, $message, $role);
		if ($res) {
			$msg = array(
				'msg' => 'Successfully ' . $message,
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
			redirect('leave/approve_leave');
		} else {
			$msg = array(
				'msg' => 'Failed',
				'type' => 'error'
			);
		}
		redirect('leave/approve_leave');
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
		$data['leaves'] = $this->leave_mdl->get_approval_leaves($status, $start_date, $end_date, $staff_id);
		//dd($data);
		render('approval', $data);
	}

public function curlgetHttp($endpoint, $headers, $username, $password) {
    $url = $endpoint;
    $ch = curl_init($url);

    // Post values (if needed)
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    // Option to Return the Result, rather than just true/false
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Set Request Headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Basic Authentication
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

    // Time to wait while waiting for connection...indefinite
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

    // Set cURL timeout and processing timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 200);

    // Perform the request, and save content to $result
    $result = curl_exec($ch);

    // cURL error handling
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);
    if ($curl_errno > 0) {
        curl_close($ch);
        return "CURL Error ($curl_errno): $curl_error\n";
    }

    $info = curl_getinfo($ch);
    curl_close($ch);

    $decodedResponse = json_decode($result);
    return $decodedResponse;
}
	function fetch_orgunits()
	{
		// Base URL for the API endpoint
		$baseUrl = 'https://hmis.health.go.ug/api/organisationUnits';

		// Initial URL to fetch the first page
		$url = $baseUrl . '?fields=id,name,parent[id,name,parent[id,name]]&level=2';

		// Initialize the data array
		$allData = array();
		$headr = array();
		$headr[] = 'Content-length: 0';
		$headr[] = 'Content-type: application/json';

			// Fetch data from the current URL
			$data = $this->curlgetHttp($url,$headr,'moh-ict.aagaba','Agaba@432');
			 
		   dd($data);

			// Add the organization units data to the main array
			
	

		// CSV file name
		$csvFile = 'organisation_units.csv';

		// Open the CSV file for writing
		$fileHandle = fopen($csvFile, 'w');

		// Write the CSV header
		fputcsv($fileHandle, ['ID', 'Name', 'Parent ID', 'Parent Name', 'Grandparent ID', 'Grandparent Name']);

		// Loop through the organization units data and write each row to the CSV file
		$rows = array();
		foreach ($allData as $organisationUnit) {
			$id = $organisationUnit->id;
			$name = $organisationUnit->name;
			$parentId = $organisationUnit->parent->id;
			

			// Write the row to the CSV file
			fputcsv($fileHandle, [$id, $name, $parentId,]);
		}
		//array_push($rows, $organisationUnit);
		//dd($rows);

		// Close the CSV file
		fclose($fileHandle);

		echo "Data has been saved to $csvFile";
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
		$data['leaves'] = $this->leave_mdl->staff_leave_status($status, $start_date, $end_date);
		render('leave_status', $data);
	}
}
