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
        'supervisor2_id' => NULL, //Set to NULL beacuse its PPA
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

    $save_data['name']=staff_name($staff_id);
    $save_data['type']='submission_staff';
    $data['approval_trail'] = $this->per_mdl->get_approval_trail($entry_id);
    $this->notify_ppa_status($save_data);
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
	    $data['name']=staff_name($staff_id);
        $data['status']=$log_action;
        $data['type']="status_update";
        $data['entry_id']=$entry_id;
        $data['staff_id']=$staff_id;
    
        $this->notify_ppa_status($data);
		Modules::run('utility/setFlash', $msg);
		redirect('performance/view_ppa/' . $entry_id.'/'.$staff_id);
	}

public function notify_ppa_status($data)
{
    $staff_id = $data['staff_id'];
    $entry_id = $data['entry_id'];
    $ppa = $this->per_mdl->get_plan_by_entry_id($entry_id);
    $period = $ppa->performance_period ?? current_period();
    $approval_trail = $this->per_mdl->get_approval_trail($entry_id);

    $trigger_id = $this->session->userdata('user')->staff_id;
    $trigger_name = staff_name($trigger_id);
    $dispatch = date('Y-m-d H:i:s');
    
    $staff_email = staff_details($staff_id)->work_email;
    $supervisor_email = staff_details($ppa->supervisor_id)->work_email ?? '';
    //$second_supervisor_email = $ppa->supervisor2_id ? staff_details($ppa->supervisor2_id)->work_email : '';

    $data['name'] = staff_name($staff_id);
    $data['period'] = $period;
    $data['approval_trail'] = $approval_trail;
    $data['ppa'] = $ppa;

    if ($data['type'] === 'submission_staff') {
        $data['subject'] = "PPA Submission Confirmation";
        $data['body'] = $this->load->view('emails/submission', $data, true);
        $data['email_to'] = $staff_email.';'.settings()->email;
        $entry_log_id = md5($staff_id . '-PPAS-' . date('Y-m-d'));

    }elseif ($data['type'] === 'submission_supervisor') {
        $data['subject'] = "PPA Submission Confirmation";
        $data['body'] = $this->load->view('emails/supervisor_ppa', $data, true);
        $data['email_to'] =  $supervisor_email.';'.$staff_email.';'.settings()->email;
        $entry_log_id = md5($staff_id . '-PPAS-' . date('Y-m-d'));

    } elseif ($data['type'] === 'status_update') {
        $data['subject'] = "PPA Status Update";
        $data['status'] = $data['status'] ?? 'Pending';
        $data['body'] = $this->load->view('emails/ppa_status', $data, true);
        $data['email_to'] = $staff_email . ';' . $supervisor_email.';'.settings()->email;
        $entry_log_id = md5($staff_id . '-PPAST-' . date('Y-m-d'));
    } else {
        return false; // Invalid type
    }

    return golobal_log_email(
        $trigger_name,
        $data['email_to'],
        $data['body'],
        $data['subject'],
        $staff_id,
        date('Y-m-d'),
        $dispatch,
        $entry_log_id
    );
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
public function print_ppa($entry_id,$staff_id,$approval_trail=FALSE)
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

	
}
