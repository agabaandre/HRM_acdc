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

	public function index($csv=FALSE,$pdf=FALSE)
	{

		$data['module'] = $this->module;
		$data['title'] = "Current Staff";
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->get();
		$filters['csv'] =$csv;
		$filters['pdf'] =$pdf;
		$data['divisions'] = $this->db->get('divisions')->result(); 
        $data['duty_stations'] = $this->db->get('duty_stations')->result(); // 
		
        $count = count($this->staff_mdl->get_active_staff_data($filters));
		$data['records'] = $count;
		//dd($count);
		$data['staffs'] = $this->staff_mdl->get_active_staff_data($filters,$per_page = 20, $page);
		$staffs= $data['staffs'];
		$file_name = 'Africa-CDC-Staff_'.date('d-m-Y-H-i').'.csv';
		if($csv==1){
            $staff = $this->remove_ids($staffs);
			
			render_csv_data($staff, $file_name,true);

		}
		elseif($pdf==1){
			$pdf_name = 'Africa-CDC-Staff_'.date('d-m-Y-H-i').'.pdf';
			
			$this->print_data($data, $pdf_name,'L','pdfs/staff');
		
		}
		//dd($data);
		$data['links'] = pagination('staff/index', $count, $per_page = 20);
		render('staff_table', $data);
	}

	public function find_staff_by_email($email){
		
		$this->db->where('work_email', $email);
		$data = $this->db->get('staff')->result();
		json_encode($data);
	}

	public function profile($profile){
        $filters['staff_id']=$profile;
	
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($filters,$per_page = 20, $page=0);
		
	
		$pdf_name = $data['staffs'][0]->lname.'_'.$data['staffs'][0]->fname.'_'.date('d-m-Y-H-i').'.pdf';
		
		$this->print_data($data, $pdf_name,'P','pdfs/staff_profile');
		
		

	}

	public function profile_view($profile){
        $filters['staff_id']=$profile;
		$data['module']= $this->module;
	
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($filters,$per_page = 20, $page=0);
		
	
		$pdf_name = $data['staffs'][0]->lname.'_'.$data['staffs'][0]->fname.'_'.date('d-m-Y-H-i').'.pdf';
		
	render('pdfs/staff_profile',$data);
		
		

	}

	function print_data($staffs, $file_name,$orient,$view)  
	{
	   if($orient =='L'){
	   $this->load->library('ML_pdf');
	   }
	   else{
	    $this->load->library('M_pdf');
	   }
	   // Define PDF File Name
	   $watermark = FCPATH . "assets/images/AU_CDC_Logo-800.png";
	   $filename = $file_name; 
	   // Set Execution Time to Unlimited
	   ini_set('max_execution_time', 0);
	   // Load the Specified View Dynamically and Convert to HTML

	   
	  // dd($data);
	   $html =  $this->load->view($view, $staffs,true);
	   //exit;
	   $PDFContent = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
	   // Set Watermark Image (if applicable)
		

	   if($orient =='L'){
		if (!empty($watermark)) {
		$this->load->ml_pdf->pdf->SetWatermarkImage($watermark);
		$this->load->ml_pdf->pdf->showWatermarkImage = true;
		}
		// Set Footer with Timestamp and Source
		date_default_timezone_set("Africa/Addis Ababa");
		$this->load->ml_pdf->pdf->SetHTMLFooter(
			"Printed/Accessed on: <b>" . date('d F,Y h:i A') . "</b><br>" .
			"Source: Africa CDC - Staff Tracker " . base_url()
		);
	
		// Generate the PDF with the Staff Profile Data
		$this->load->ml_pdf->pdf->WriteHTML($PDFContent);
	
		// Output the PDF (Display in Browser)
		$this->load->ml_pdf->pdf->Output($filename, 'I');
		}
		else{
		if (!empty($watermark)) {
		$this->load->m_pdf->pdf->SetWatermarkImage($watermark);
		$this->load->m_pdf->pdf->showWatermarkImage = true;
		}
		
		// Set Footer with Timestamp and Source
		date_default_timezone_set("Africa/Addis Ababa");
		$this->load->m_pdf->pdf->SetHTMLFooter(
			"Printed/Accessed on: <b>" . date('d F,Y h:i A') . "</b><br>" .
			"Source: Africa CDC - Staff Tracker " . base_url()
		);
	
		// Generate the PDF with the Staff Profile Data
		$this->load->m_pdf->pdf->WriteHTML($PDFContent);
	
		// Output the PDF (Display in Browser)
		$this->load->m_pdf->pdf->Output($filename, 'I');
		}
	   
   }
   
	function remove_ids($staffs = []) {
		$keysToRemove = [
			'staff_contract_id',
			'email_disabled_at',
			'email_disabled_by',
			'job_id',
			'job_acting_id',
			'job_acting',
			'grade_id',
			'contracting_institution_id',
			'funder_id',
			'nationality_id',
			'staff_id',
			'first_supervisor',
			'second_supervisor',
			'contract_type_id',
			'duty_station_id',
			'division_id',
			'unit_id',
			'photo',
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
	
	

	public function all_staff($csv=FALSE,$pdf=FALSE)
	{

		$data['module'] = $this->module;
		$data['title'] = "All Staff (Active, Due, Expired, Under Renewal)";
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->get();
		$filters['csv'] =$csv;
		$filters['pdf'] =$pdf;
		$data['divisions'] = $this->db->get('divisions')->result(); 
        $data['duty_stations'] = $this->db->get('duty_stations')->result(); // 
		
        $count = count($this->staff_mdl->get_all_staff_data($filters));
		$data['records'] = $count;
		//dd($count);
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($filters,$per_page = 20, $page);
		$staffs= $data['staffs'];
		$file_name = 'All-Africa-CDC-Staff_'.date('d-m-Y-H-i').'.csv';
		if($csv==1){
            $staff = $this->remove_ids($staffs);
			
			render_csv_data($staff, $file_name,true);

		}
		elseif($pdf==1){
			$pdf_name = 'Africa-CDC-All_Staff_'.date('d-m-Y-H-i').'.pdf';
			
			$this->print_data($data, $pdf_name,'L','pdfs/staff');
		
		}
		//dd($data);
		$data['links'] = pagination('staff/all_staff', $count, $per_page = 20);
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
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($filters,$per_page = 20, $page=0);
		$staffs = $data['staffs'];
		//dd($staffs);
		$data['title'] = $staffs[0]->lname." ".$staffs[0]->fname;
		$data['contracts'] = $this->staff_mdl->get_staff_contracts($staff_id);
		
		render('new_contract', $data);
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




	public function contract_status($status, $csv=FALSE,$pdf=FALSE)
	{

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
		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$filters = $this->input->get();
		$filters['csv'] =$csv;
		$filters['pdf'] =$pdf;
		$filters['status_id'] =$status;	
		$per_page=20;
		$data['staffs'] = $this->staff_mdl->get_status($filters,$per_page, $page);
		//dd($data['staffs']);
        $data['divisions'] = $this->db->get('divisions')->result(); 
        $data['duty_stations'] = $this->db->get('duty_stations')->result(); // 
        $count = count($this->staff_mdl->get_status($filters));
		$data['records'] = $count;
		//dd($count);
	
		
		//dd($data);
		$staffs= $data['staffs'];
		$file_name = $data['title'].'_Africa CDC Staff_'.date('d-m-Y-H-i').'.csv';
		if($csv==1){
            $staff = $this->remove_ids($staffs);
			
			render_csv_data($staff, $file_name,true);

		}
		elseif($pdf==1){
			$pdf_name = str_replace(' ','',$data['title']) .'_Africa-CDC-Staff_'.date('d-m-Y-H-i').'.pdf';
			$this->print_data($data, $pdf_name,'L','pdfs/staff');
		}
		
		//dd($data);

		$data['links'] = pagination("staff/contract_status/".$status, $count, $per_page = 20,4);
		render('contract_status', $data);
	}
	public function contract_statuses($status) {
		$data['module'] = $this->module;
	
		// Set Titles Based on Status
		$titles = [
			2 => "Due Contracts",
			3 => "Expired Contracts",
			4 => "Former Staff",
			5 => "Re Assigned Staff",
			6 => "Renewed Contracts",
			7 => "Under Renewal"
		];
	
		$data['title'] = $titles[$status] ?? "Contracts";
	
		// Fetch Staff Data
		$staffData = $this->staff_mdl->get_status($status);
	
		// Ensure CSRF Token is Updated
		$csrf = [
			"csrf_token_name" => $this->security->get_csrf_token_name(),
			"csrf_token_hash" => $this->security->get_csrf_hash()
		];
	
		// Return Data with Updated CSRF Token
		echo json_encode([
			"draw" => $_POST['draw'] ?? null,
			"recordsTotal" => count($staffData),
			"recordsFiltered" => count($staffData),
			"data" => $staffData,
			"csrf" => $csrf // Include new CSRF token
		]);
	}
	
		
	public function staff_birthday()
	{
		$data['module'] = $this->module;
		$data['title'] = "Staff Birthday";
		$data['today'] = $this->staff_mdl->getBirthdays(0);
		$data['tomorrow'] = $this->staff_mdl->getBirthdays(1);
		$data['week'] = $this->staff_mdl->getBirthdays(7);
		$data['month'] = $this->staff_mdl->getBirthdays(30);
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
				$id = $this->session->userdata('user')->staff_id;
				$trigger=staff_name($id);
				$dispatch = date('Y-m-d H:i:s');
				$entry_id = $staff_id.'UR'.date('Y-m-d');
				return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,md5($entry_id));
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
				$id = $this->session->userdata('user')->staff_id;
				$trigger=staff_name($id);
				$dispatch = date('Y-m-d H:i:s');
				$entry_id = $staff_id.'-SP-'.date('Y-m-d');
				return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,md5($entry_id));
			
				}
				else  if($data['status_id']==1){
		
					$data['subject'] = "Contract Notice";
					$supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
					$first_supervisor_mail =staff_details($supervisor_id)->work_email;
					$copied_mails = settings()->contracts_status_copied_emails;
					$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
					$data['name'] = staff_name($staff_id);
							// Load the view and return its output as a string.
					$data['body'] = $this->load->view('emails/new_contract', $data, true);
					$id = $this->session->userdata('user')->staff_id;
					$trigger=staff_name($id);
					$dispatch = date('Y-m-d H:i:s');
					$data['date_2'] = date('Y-m-d');
					$entry_id = $staff_id.'-NC-'.date('Y-m-d');
					return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,md5($entry_id));
				
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
	$data['divisions'] = $this->db->get('divisions')->result(); 
    $data['duty_stations'] = $this->db->get('duty_stations')->result(); // 
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

		//dd($end_date);

        // Save to database (first save staff, then contract information)
        $staff_id = $this->staff_mdl->add_staff(
            $sapno, $title, $fname, $lname, $oname, $dob, $gender, 
            $nationality_id, $initiation_date, $tel_1, $tel_2, $whatsapp, 
            $work_email, $private_email, $physical_location
        );

        if ($staff_id) {
            $contract_id = $this->staff_mdl->add_contract_information($staff_id, $job_id, $job_acting_id, $grade_id, $contracting_institution_id, $funder_id, $first_supervisor, $second_supervisor, $contract_type_id, $duty_station_id, $division_id, $start_date, $end_date, $status_id, $file_name, $comments);
            if ($contract_id) {
                $response = array(
					'staff_id'=>$staff_id,
                    'msg'  => 'Staff information saved successfully.',
                    'type' => 'success'
                );
            } else {
                $response = array(
					'staff_id'=>$staff_id,
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
public function check_work_email()
{
    $email = $this->input->post('work_email');

    $staff = $this->db->select('fname, lname')
                      ->where('work_email', $email)
                      ->get('staff')
                      ->row();

    if ($staff) {
        echo json_encode([
            'exists' => true,
            'name' => $staff->fname . ' ' . $staff->lname
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
}


	
}
