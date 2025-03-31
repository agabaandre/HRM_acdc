<?php

use SebastianBergmann\Type\FalseType;

defined('BASEPATH') or exit('No direct script access allowed');

class Performance extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->module = "performance";
		$this->load->model("performance_mdl", 'per_mdl');
	}

	public function index()
	{
		$staff_id = $this->session->userdata('user')->staff_id;
		$data['module'] = $this->module;
		$data['title'] = "Performance Plan - " . $this->session->userdata('user')->name;
		$data['skills'] = $this->db->get('training_skills')->result();

		// Fetch existing plan if any
		//$data['ppa'] = $this->per_mdl->get_staff_plan($staff_id);
		render('plan', $data);
	}

	public function save_ppa()
{
    $data = $this->input->post();
    $staff_id = $this->session->userdata('user')->staff_id;
    $performance_period = str_replace(' ','-',current_period());
    $entry_id = md5($staff_id . '_' . str_replace(' ', '', $performance_period));

    $save_data = [
        'staff_id' => $staff_id,
        'performance_period' => $performance_period,
        'entry_id' => $entry_id,
        'supervisor_id' => $data['supervisor_id'],
        'supervisor2_id' => $data['supervisor2_id'] == 0 ? null : $data['supervisor2_id'],
        'objectives' => json_encode($data['objectives']),
        'training_recommended' => $data['training_recommended'] ?? 'No',
        'required_skills' => isset($data['required_skills']) ? json_encode($data['required_skills']) : null,
        'training_contributions' => $data['training_contributions'] ?? null,
        'recommended_trainings' => $data['recommended_trainings'] ?? null,
        'recommended_trainings_details' => $data['recommended_trainings_details'] ?? null,
        'staff_sign_off' => isset($data['staff_sign_off']) ? 1 : 0,
        'draft_status' => $data['submit_action'] === 'submit' ? 0 : 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $exists = $this->per_mdl->get_staff_plan($staff_id, $performance_period);

    if ($exists) {
        $this->db->where('entry_id', $entry_id)->update('ppa_entries', $save_data);
    } else {
        $save_data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('ppa_entries', $save_data);
    }

    // 📝 Insert to approval trail only if submit
    if ($data['submit_action'] === 'submit') {
        $this->db->insert('ppa_approval_trail', [
            'entry_id' => $entry_id,
            'staff_id' => $staff_id,
            'comments' => $this->input->post('comments'),
            'action' => 'Submitted',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    $msg = [
        'msg' => $data['submit_action'] === 'submit' ? 'Plan submitted for Review.' : 'Draft saved successfully.',
        'type' => 'success'
    ];

    Modules::run('utility/setFlash', $msg);
    redirect('performance/view_ppa/' . $entry_id.'/'.$staff_id);
}



	
	public function view_ppa($entry_id)
	{
		// Extract staff_id from entry_id
		$staff_id = explode('_', $entry_id)[0];
	
		// Load dependencies
		$data['module'] = $this->module;
		$data['title'] = "Performance Plan - " . $this->session->userdata('user')->name;
		$data['skills'] = $this->db->get('training_skills')->result();
	
		// Get saved PPA form
		$data['ppa'] = $this->per_mdl->get_plan_by_entry_id($entry_id);
	
		// Get approval logs if any
		$data['approval_trail'] = $this->per_mdl->get_approval_trail($entry_id);
	
		render('plan', $data);
	}
			public function pending_approval()
		{
			$data['module'] = $this->module;
			$data['title'] = "Performance Plans Pending Action";
			$staff_id = $this->session->userdata('user')->staff_id;
			
			$data['plans'] = $this->per_mdl->get_pending_ppa($staff_id);
			//$data['pendingcount'] = count($data['plans']);
			//dd($data);

			render('pending_ppa', $data);
		}

	
	public function recent_ppa(){
		$data['module'] = $this->module;
		$data['title'] = "My Current PPA - ". $this->session->userdata('user')->name;;
		
		$staff_id = $this->session->userdata('user')->staff_id;

		$performance_period = str_replace(' ','-',current_period());
		
		// Load paginated results (optional: add pagination logic here)
		
		$data['plans'] = $this->per_mdl->get_recent_ppas_for_user($staff_id, $performance_period);

		//dd($data['plans']);
	
		// If using pagination links
		$data['links'] = ''; // placeholder, update if using pagination
	
		render('staff_ppa', $data); // your blade/PHP view

		
	}
	public function approve_ppa($entry_id)
	{
		$staff_id = $this->session->userdata('user')->staff_id;
		$action = $this->input->post('action');
	
		if (!in_array($action, ['approve', 'return'])) {
			show_error("Invalid action.");
		}
	
		$log_action = $action === 'approve' ? 'Approved' : 'Returned';
	
		// Log approval trail
		$this->db->insert('ppa_approval_trail', [
			'entry_id'   => $entry_id,
			'staff_id'   => $staff_id,
			'comments'   => $this->input->post('comments') ?? null,
			'action'     => $log_action,
			'created_at' => date('Y-m-d H:i:s')
		]);
	
		// If returned, update the draft status
		if ($action === 'return') {
			$this->db->where('entry_id', $entry_id)->update('ppa_entries', [
				'draft_status' => 1,
				'updated_at'   => date('Y-m-d H:i:s')
			]);
		}
	
		$msg = [
			'msg'  => $log_action === 'Approved' ? 'PPA approved successfully.' : 'PPA returned for revision.',
			'type' => 'success'
		];
	
		Modules::run('utility/setFlash', $msg);
		redirect('performance/view_ppa/' . $entry_id.'/'.$staff_id);
	}
	public function my_ppas()
{
    $data['module'] = $this->module;
    $data['title'] = "My Performance Plans";
    
    $staff_id = $this->session->userdata('user')->staff_id;
    
    // Load paginated results (optional: add pagination logic here)
    $data['plans'] = $this->per_mdl->get_all_approved_ppas_for_user($staff_id);

    // If using pagination links
    $data['links'] = ''; // placeholder, update if using pagination

    render('staff_ppa', $data); // your blade/PHP view
}



public function approved_by_me()
{
    $data['module'] = $this->module;
    $data['title'] = "PPAs I've Approved";

    $supervisor_id = $this->session->userdata('user')->staff_id;
    $data['plans'] = $this->per_mdl->get_approved_by_supervisor($supervisor_id);

    render('approved_by_me', $data);
}
public function print_ppa($entry_id,$staff_id)
    {
        $this->load->model('performance_mdl', 'per_mdl');


        $data['module'] = "performance";
        $data['title'] = "Printable PPA";
        $data['skills'] = $this->db->get('training_skills')->result();
        $data['ppa'] = $this->per_mdl->get_plan_by_entry_id($entry_id);
		//dd($data['ppa']);
        $data['approval_trail'] = $this->per_mdl->get_approval_trail($entry_id);
		$data['staff_id'] = $staff_id;

        // Get contract and supervisor info
        $data['contract'] = Modules::run('auth/contract_info', $staff_id);
        $data['readonly'] = true;
		$file_name= staff_name($staff_id).'_'.$data['ppa']->performance_period.'_PPA.pdf';

        pdf_print_data($data, $file_name, 'P', 'performance/staff_ppa_print');
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
	
}
