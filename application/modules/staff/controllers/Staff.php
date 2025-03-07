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

	public function index($csv=FALSE)
	{

		$data['module'] = $this->module;
		$data['title'] = "Staff";
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->post();
		$filters['csv']=$csv;

		$data['staffs'] = $this->staff_mdl->get_active_staff_data($per_page = 20, $page, $filters);
		$staffs= $data['staffs'];
		$file_name = 'Africa CDC Staff_'.date('dd-mm-yyyy').'.csv';
		if($csv==1){
            $staff = $this->remove_ids($staffs);
			
			render_csv_data($staff, $file_name,true);

		}
		//dd($data);
		$data['links'] = pagination('staff/index', count($data['staffs']), 3);
		render('staff_table', $data);
	}
	function remove_ids($staffs = []) {
		$keysToRemove = [
			'staff_contract_id',
			'job_id',
			'job_acting_id',
			'grade_id',
			'contracting_institution_id',
			'funder_id',
			'first_supervisor',
			'second_supervisor',
			'contract_type_id',
			'duty_station_id',
			'division_id',
			'unit_id',
			'flag',
			'created_at',
			'updated_at',
			'status_id',
			'division_head',
			'focal_person',
			'admin_assistant',
			'finance_officer',
			'region_id',
			'email_status'

		];
		
		// If it's an array of arrays:
		foreach ($staffs as $index => $staff) {
			foreach ($keysToRemove as $key) {
				unset($staffs[$index][$key]);
			}
		}
		
		return $staffs;
	}
	
	
	public function all_staff($csv=false)
	{

		$data['module'] = $this->module;
		$data['title'] = "Staff";
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->post();
		$filters['csv']=$csv;
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($per_page = 20, $page, $filters);
		//dd($data);
		$staffs= $data['staffs'];
		$file_name = 'All Africa CDC Staff_'.date('dd-mm-yyyy').'.csv';
		if($csv==1){
            $staff = $this->remove_ids($staffs);
			
			render_csv_data($staff, $file_name,true);

		}
		$data['links'] = pagination('staff/index', count($data['staffs']), 3);
		render('all_staff', $data);
	}
	// }
	public function get_staff_data_ajax()
	{
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->post();
		$data['staffs'] = $this->staff_mdl->get_active_staff_data($per_page = 20, $page, $filters);
		$data['links'] = pagination('staff/index', count($data['staffs']), 3);
		$html_content = $this->load->view('staff_ajax', $data, true);
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(['html' => $html_content]));

		
	}
	// Getting All Contracts
	public function staff_contracts($staff_id)
	{
		$data['module'] = $this->module;
		$filters = array('staff_id' => $staff_id);
		//$staff = $data['this_staff'] = $this->staff_mdl->get_all_staff_data($start=1, $limit=0, $filters);
		//($this->db->last_query());
		$data['contracts'] = $this->staff_mdl->get_staff_contracts($staff_id);
	   //dd($data['contracts']);
		$data['title'] = $data['contracts'][0]->lname." ".$data['contracts'][0]->fname;
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
		
		$data['staff_id'] = $staff_id;
		$filters = array('staff_id' => $staff_id);
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($start=1, $limit=0, $filters);
		$staffs = $data['staffs'];
		//dd($staffs);
		$data['title'] = $staffs[0]->lname." ".$staffs[0]->fname;
		$data['contracts'] = $this->staff_mdl->get_staff_contracts($staff_id);
		
		render('new_contract', $data);
	}
	public function test_email(){
		$mailto = send_email_async('agabaandre@gmail.com','Africa CDC TEST EMAIL','This is a test emal from Africa CDC for mail notifications');
		if ($mailto) {
			Modules::run('utility/setFlash', 'SENT test MAIL');	
		}
		redirect('staff');

	}

	function timer_after($time,$function)
	{
		// Access the event loop
		$loop = $this->reactphp_lib->getLoop();
		$loop->addTimer($time, function () {
			
		});
		$this->reactphp_lib->run();
	}


	// Add New Contract
	public function add_new_contract(){
		$data['module'] = $this->module;
		$data = $this->input->post();
		$new_contract = $this->staff_mdl->add_new_contract($data);
		//dd($new_contract);
		$staffid = $this->input->post('staff_id');
		$this->notify_contract_status_change($data);
		$update['staff_id'] = $data['staff_id'];
		$update['staff_contract_id']=$this->staff_mdl->previous_contract($staffid, $new_contract);
		$update['status_id']=$data['previous_contract_status_id'];
		//dd($update['staff_contract_id']);
	
		$this->staff_mdl->update_contract($update);
		
		redirect("staff/staff_contracts/".$staffid);
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
		else if ($status == 7) {
			$data['title'] = "Under Renewal";
		}
		else if ($status == 6) {
			$data['title'] = "Renewed Contracts";
		}
		else if ($status == 5) {
			$data['title'] = "Re Assigned Staff";
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
		$staffid = $data['staff_id'];
		$q= $this->staff_mdl->update_contract($data);
		//dd($data);
		$this->notify_contract_status_change($data);
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
		redirect("staff/staff_contracts/".$staffid);
	}

	public function notify_contract_status_change($data) {
		     $staff_id = $data['staff_id'];
		        if($data['status_id']==7){
				
				$data['subject'] = "Staff Contract Under Renewal Notice";
				$data['date_2'] = date('Y-m-d');
				$supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
				$first_supervisor_mail =staff_details($supervisor_id)->work_email;
				$copied_mails = settings()->contracts_status_copied_emails;
				$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
				$data['name'] = staff_name($staff_id);
				
					// Load the view and return its output as a string.
				$data['body'] = $this->load->view('emails/under_review', $data, true);
				//dd($data);
				$id = $this->session->user_data('user')->staff_id;
				$trigger=staff_name($id);
				$dispatch = date('Y-m-d H:i:s');
				$entry_id = $staff_id.md5($data['subject']).md5($$data['date_2']);
				return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,$entry_id);
				}
				else  if($data['status_id']==4){
		
				$data['subject'] = "Staff Contract Separation Notice";
				$data['date_2'] = date('Y-m-d');
				$supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
				$first_supervisor_mail =staff_details($supervisor_id)->work_email;
				$copied_mails = settings()->contracts_status_copied_emails;
				$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
				$data['name'] = staff_name($staff_id);
						// Load the view and return its output as a string.
				$data['body'] = $this->load->view('emails/separated', $data, true);
				$id = $this->session->user_data('user')->staff_id;
				$trigger=staff_name($id);
				$dispatch = date('Y-m-d H:i:s');
				$entry_id = $staff_id.md5($data['subject']).md5($data['date_2']);
				return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,$entry_id);
			
				}
				else  if($data['status_id']==1){
		
					$data['subject'] = "New Contract Notice";
					
					
					$supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
					$first_supervisor_mail =staff_details($supervisor_id)->work_email;
					$copied_mails = settings()->contracts_status_copied_emails;
					$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
					$data['name'] = staff_name($staff_id);
							// Load the view and return its output as a string.
					$data['body'] = $this->load->view('emails/new_contract', $data, true);
					$id = $this->session->user_data('user')->staff_id;
					$trigger=staff_name($id);
					$dispatch = date('Y-m-d H:i:s');
					$data['date_2'] = date('Y-m-d');
					$entry_id = $staff_id.md5($data['subject']).md5($data['date_2']);
					return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,$entry_id);
				
					}	
				
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

	// Controller: Staff.php

// Method to load the form only
public function new()
{
    $data['module'] = $this->module;
    $data['title']  = "New Staff";
    // Render the view with your form (e.g., new_staff.php)
    render('new_staff', $data);
}

// Method to process the form submission via AJAX
public function new_submit()
{
    // Check if it's a POST request
    if ($this->input->post()) {

        // Personal Information
        $sapno          = $this->input->post('SAPNO');
        $title          = $this->input->post('title');
        $fname          = $this->input->post('fname');
        $lname          = $this->input->post('lname');
        $oname          = $this->input->post('oname');
        $dob            = date('Y-m-d', strtotime($this->input->post('date_of_birth')));
        $gender         = $this->input->post('gender');
        $nationality_id = $this->input->post('nationality_id');
        $initiation_date= date('Y-m-d', strtotime($this->input->post('initiation_date')));

        // Contact Information
        $tel_1            = $this->input->post('tel_1');
        $tel_2            = $this->input->post('tel_2');
        $whatsapp         = $this->input->post('whatsapp');
        $work_email       = $this->input->post('work_email');
        $private_email    = $this->input->post('private_email');
        $physical_location= $this->input->post('physical_location');

        // Contract Information
        $job_id                    = $this->input->post('job_id');
        $job_acting_id             = $this->input->post('job_acting_id');
        $grade_id                  = $this->input->post('grade_id');
        $contracting_institution_id= $this->input->post('contracting_institution_id');
        $funder_id                 = $this->input->post('funder_id');
        $first_supervisor          = $this->input->post('first_supervisor');
        $second_supervisor         = $this->input->post('second_supervisor');
        $contract_type_id          = $this->input->post('contract_type_id');
        $duty_station_id           = $this->input->post('duty_station_id');
        $division_id               = $this->input->post('division_id');
        $unit_id                   = $this->input->post('unit_id');
        $start_date                = date('Y-m-d', strtotime($this->input->post('start_date')));
        $end_date                  = date('Y-m-d', strtotime($this->input->post('end_date')));
        $status_id                 = $this->input->post('status_id');
        $file_name                 = $this->input->post('file_name');
        $comments                  = $this->input->post('comments');

        // Save to database (first save staff, then contract information)
        $staff_id = $this->staff_mdl->add_staff(
            $sapno, $title, $fname, $lname, $oname, $dob, $gender, 
            $nationality_id, $initiation_date, $tel_1, $tel_2, $whatsapp, 
            $work_email, $private_email, $physical_location
        );

        if ($staff_id) {
            $contract_id = $this->staff_mdl->add_contract_information(
                $staff_id, $job_id, $job_acting_id, $grade_id, $contracting_institution_id, 
                $funder_id, $first_supervisor, $second_supervisor, $contract_type_id, 
                $duty_station_id, $division_id, $unit_id, $start_date, $end_date, 
                $status_id, $file_name, $comments
            );
            if ($contract_id) {
                $response = array(
                    'msg'  => 'Staff information saved successfully.',
                    'type' => 'success'
                );
            } else {
                $response = array(
                    'msg'  => 'Failed, please Retry',
                    'type' => 'error'
                );
            }
        } else {
            $response = array(
                'msg'  => 'Failed, please Retry',
                'type' => 'error'
            );
        }
        // Return JSON response
        echo json_encode($response);
    } else {
        // If not POST, return an error message
        echo json_encode(array('msg' => 'Invalid request', 'type' => 'error'));
    }
}


	
}
