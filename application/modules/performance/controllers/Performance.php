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
        $this->load->model("midterm_mdl", 'midterm_mdl');
	}

	public function index()
	{
		$staff_id = $this->session->userdata('user')->staff_id;
		$data['module'] = $this->module;
		$data['title'] = "Performance Plan - " . $this->session->userdata('user')->name;
		$data['skills'] = $this->db->get('training_skills')->result();
        
        // Get period from GET parameter, default to current period
        $performance_period = $this->input->get('period');
        if ($performance_period) {
            $performance_period = str_replace(' ', '-', $performance_period);
        } else {
            $performance_period = str_replace(' ','-',current_period());
        }
        
		// Check if PPA already exists for this period
        $existing_ppa = $this->employee_ppa($performance_period, $staff_id);
        
        // If PPA exists, redirect to view page
        if ($existing_ppa && !empty($existing_ppa) && is_object($existing_ppa) && isset($existing_ppa->entry_id)) {
            redirect('performance/view_ppa/' . $existing_ppa->entry_id . '/' . $staff_id);
            return;
        }
        
		// Fetch existing plan if any (will be empty/FALSE for new PPA)
        $data['ppa'] = $existing_ppa;

        //dd($this->session->userdata('user'));
		
		render('plan', $data);
	}

    public function employee_ppa($performance_period,$staff_id){
        $ppa = $this->db->query("SELECT * FROM ppa_entries WHERE performance_period='$performance_period' and staff_id='$staff_id'")->row();
        //dd($ppa);
        if(!empty($ppa)){
            return $data['ppa']=$ppa;
           
        }
        else{
           return $data['ppa']=FALSE;
        }

    }

	public function save_ppa()
{
    $data = $this->input->post();
    
    $staff_id = $data['staff_id'];
    $performance_period = str_replace(' ','-',current_period());
    $entry_id = md5($staff_id . '_' . str_replace(' ', '', $performance_period));

    $save_data = [
        'staff_id' => $data['staff_id'],
        'staff_contract_id' => $data['staff_contract_id'],
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
        'staff_sign_off' => 1,
        'draft_status' => $data['submit_action'] === 'submit' ? 0 : 1,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
//dd($save_data);
    $exists = $this->per_mdl->get_staff_plan_id($entry_id);
    //$ppa = $this->db->query("SELECT * FROM ppa_entries WHERE performance_period='$performance_period' and staff_id='$staff_id'")->row();

    if ($exists) {
       // dd($exists);
        $this->db->where('entry_id', $entry_id)->update('ppa_entries', $save_data);
        $msg = [
            'msg' => $data['submit_action'] === 'submit' ? 'Saved Successfully.' : 'Draft saved successfully.',
            'type' => 'success'
        ];
        $name = staff_name($staff_id);
        $log_message = "Updated PPA entry [{$entry_id}] for staff ID [{$staff_id}] , [{$name}]";
        log_user_action($log_message);
    } else {
        $save_data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('ppa_entries', $save_data);
        $msg = [
            'msg' => $data['submit_action'] === 'submit' ? 'Plan submitted for Review.' : 'Draft saved successfully.',
            'type' => 'success'
        ];

        $name = staff_name($staff_id);
        $log_message = "Created PPA entry [{$entry_id}] for staff ID [{$staff_id}] , [{$name}]";
        log_user_action($log_message);
    }

    // 📝 Insert to approval trail only if submit
    if ($data['submit_action'] === 'submit') {
        if($staff_id == $this->session->userdata('user')->staff_id){

        $this->db->insert('ppa_approval_trail', [
            'entry_id' => $entry_id,
            'staff_id' => $staff_id,
            'comments' => $this->input->post('comments'),
            'action' => 'Submitted',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }else{
        $this->db->insert('ppa_approval_trail', [
            'entry_id' => $entry_id,
            'staff_id' => $data['supervisor_id'],
            'comments' => $this->input->post('comments'),
            'action' => 'Updated',
            'created_at' => date('Y-m-d H:i:s')
        ]); 
    }

    //$data['approval_trail'] = $this->per_mdl->get_approval_trail($entry_id);
    $save_data['type']='submission';
    $this->notify_ppa_status($save_data);
    }

   

    Modules::run('utility/setFlash', $msg);
    redirect('performance/view_ppa/' . $entry_id.'/'.$staff_id);
}

	
	public function view_ppa($entry_id)
	{
		// Extract staff_id from entry_id
		$staff_id = explode('_', $entry_id)[0];
	
		// Load dependencies
		$data['module'] = $this->module;
		$data['title'] = "Performance Plan - " . staff_name($this->uri->segment(4));
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
			
			$data['approvals'] = $this->per_mdl->get_all_pending_approvals($staff_id);
			$data['pendingcount'] = count($data['approvals']);
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
        //draft status 0 is for summitted entries, 1 is in in draft mode, 2 is for approved.
		$staff_id = $this->session->userdata('user')->staff_id;
		$action = $this->input->post('action');
        //FOR LOGGING 
        $staffno = $this->db->query("SELECT staff_id from ppa_entries where entry_id='$entry_id'")->row()->staff_id;
        $name = staff_name($staffno);
     

	
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
       

        $log_message = "Returned PPA entry [{$entry_id}] for staff ID [{$staffno}] , [{$name}]";
        log_user_action($log_message);
		}
        else if ($action === 'approve') {
			$this->db->where('entry_id', $entry_id)->update('ppa_entries', [
				'draft_status' => 2,
				'updated_at'   => date('Y-m-d H:i:s')
			]);

            $log_message = "Approved PPA entry [{$entry_id}] for staff ID [{$staffno}] , [{$name}]";
            log_user_action($log_message);
		}
	
		$msg = [
			'msg'  => $log_action === 'Approved' ? 'PPA approved successfully.' : 'PPA returned for revision.',
			'type' => 'success'
		];

        $data['ppa'] = $this->per_mdl->get_plan_by_entry_id($entry_id);
        $data['supervisor_id'] = $this->session->userdata('user')->staff_id;
        $data['staff_id'] = $data['ppa']->staff_id; // This is the actual PPA owner
        $data['name'] = staff_name($data['staff_id']);
        $data['status'] = $log_action;
        $data['type'] = "status_update";
        $data['entry_id'] = $entry_id;

       // dd($data);
    
        $this->notify_ppa_status($data);
		Modules::run('utility/setFlash', $msg);
		redirect('performance/pending_approval');
	}

    public function notify_ppa_status($data)
    {
        // This is the staff whose PPA it is (PPA owner)
        $staff_id = $data['staff_id'];
        $entry_id = $data['entry_id'];
        $supervisor_id = $data['supervisor_id'];
        $data['supervisor_name'] = staff_name($supervisor_id);
        $trigger_id = $this->session->userdata('user')->staff_id;
        $trigger_name = staff_name($trigger_id);
        $dispatch = date('Y-m-d H:i:s');
    
        $ppa = $this->per_mdl->get_plan_by_entry_id($entry_id);
        $period = $ppa->performance_period ?? current_period();
        $approval_trail = $this->per_mdl->get_approval_trail($entry_id);
    
        $staff_email = staff_details($staff_id)->work_email;
        $supervisor_email = staff_details($supervisor_id)->work_email ?? '';
    
        $data['name'] = staff_name($staff_id); // This name appears in the email
        $data['period'] = $period;
        $data['approval_trail'] = $approval_trail;
        $data['ppa'] = $ppa;
    
        // Handle submission notifications
        if ($data['type'] === 'submission') {
            $entry_log_id = md5($staff_id . '-STAFFPPACONF-' . date('Y-m-d'));
            if($staff_id == $this->session->userdata('user')->staff_id){
               $subject = "PPA Submission Confirmation " . date('Y-m-d H:i:s');
               $staff_data = array_merge($data, [
                'subject' => $subject,
                'email_to' => $staff_email . ';' . settings()->email,
                'body' => $this->load->view('emails/submission', $data, true),
            ]);
            golobal_log_email(
                $trigger_name,
                $staff_data['email_to'],
                $staff_data['body'],
                $staff_data['subject'],
                $staff_id,
                date('Y-m-d'),
                $dispatch,
                $entry_log_id
            );
            }
            else{
             $subject ="PPA Details Update Notification " . date('Y-m-d H:i:s');
             $staff_data = array_merge($data, [
                'subject' => $subject,
                'email_to' => $staff_email . ';' . settings()->email,
                'body' => $this->load->view('emails/notify_changes', $data, true),
            ]);

            golobal_log_email(
                $trigger_name,
                $staff_data['email_to'],
                $staff_data['body'],
                $staff_data['subject'],
                $staff_id,
                date('Y-m-d'),
                $dispatch,
                $entry_log_id
            );

            
            }
            // 1. Notify staff (confirmation)
            
    
            
            if($staff_id == $this->session->userdata('user')->staff_id){
            // 1. Notify supervisor
            $supervisor_data = array_merge($data, [
                'subject' => "PPA Submission Notification " . date('Y-m-d H:i:s'),
                'email_to' => $supervisor_email . ';' . settings()->email,
                'body' => $this->load->view('emails/supervisor_ppa', $data, true),
            ]);
    
            golobal_log_email(
                $trigger_name,
                $supervisor_data['email_to'],
                $supervisor_data['body'],
                $supervisor_data['subject'],
                $supervisor_id,
                date('Y-m-d'),
                $dispatch,
                $entry_log_id
            );
        }
        else{
             // 2. Notify supervisor on changes
             $supervisor_data = array_merge($data, [
                'subject' => "PPA Details Update Notification " . date('Y-m-d H:i:s'),
                'email_to' => $supervisor_email . ';' . settings()->email,
                'body' => $this->load->view('emails/supervisor_ppa', $data, true),
            ]);
    
            golobal_log_email(
                $trigger_name,
                $supervisor_data['email_to'],
                $supervisor_data['body'],
                $supervisor_data['subject'],
                $supervisor_id,
                date('Y-m-d'),
                $dispatch,
                $entry_log_id
            );

        }
       }
    
        // Handle status update notifications to staff
        if ($data['type'] === 'status_update') {
            $entry_log_id = md5($staff_id . '-PPAST-' . date('Y-m-d'));
            $data['subject'] = "PPA Status Update " . date('Y-m-d H:i:s');
            $data['status'] = $data['status'] ?? 'Pending';
            $data['body'] = $this->load->view('emails/super_ppa_changes', $data, true);
            $data['email_to'] = $supervisor_email . ';' . settings()->email;
    
            golobal_log_email(
                $trigger_name,
                $data['email_to'],
                $data['body'],
                $data['subject'],
                $supervisor_id,
                date('Y-m-d'),
                $dispatch,
                $entry_log_id
            );
        }
    }
    
    

	public function my_ppas()
{
    $data['module'] = $this->module;
    $data['title'] = "My Performance Plans";
    
    $staff_id = $this->session->userdata('user')->staff_id;
    
    // Get period filter from GET parameter
    $period = $this->input->get('period');
    if ($period) {
        $period = str_replace(' ', '-', $period);
    }
    
    // Get all periods for dropdown
    $data['periods'] = $this->db->query('SELECT DISTINCT performance_period FROM ppa_entries ORDER BY performance_period DESC')->result();
    $data['selected_period'] = $period;
    
    // Load all PPAs (not just approved) with optional period filter
    $data['plans'] = $this->per_mdl->get_all_ppas_for_user($staff_id, $period);

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
public function print_ppa($entry_id,$staff_id,$staff_contract_id,$approval_trail=FALSE)
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
        $data['contract'] = $this->ppa_contract($staff_contract_id);
        $data['readonly'] = true;
		$file_name= staff_name($staff_id).'_'.$data['ppa']->performance_period.'_PPA.pdf';

        pdf_print_data($data, $file_name, 'P', 'performance/staff_ppa_print');
    }


    
        public function ppa_dashboard()
        {
            $data['module'] = "performance";
            $data['title'] = "PPA Dashboard";
            $data['divisions'] = cache_list('divisions', function () {
                return $this->db->order_by('division_name')->get('divisions')->result();
            }, 120);
            render('ppa_dashboard', $data);
        }
        public function fetch_ppa_dashboard_data()
        {
            $division_id = $this->input->get('division_id');
            $period = $this->input->get('period');
            $user = $this->session->userdata('user');
        
            $is_restricted = ($user && isset($user->role) && $user->role == 17);
            $staff_id = $is_restricted ? $user->staff_id : null;
        
            $cache_key = 'ppa_dashboard_' . ($division_id ?: 'all') . '_' . ($period ?: 'current') . ($staff_id ? "_staff_$staff_id" : '');
        
            // $data = cache_list($cache_key, function () use ($division_id, $period, $staff_id) {
            //     return 
            // }, 300); // Cache for 5 minutes
            $data =  $this->per_mdl->get_dashboard_data($division_id, $period, $staff_id);
        
            echo json_encode($data);
        }
        
    
        
        public function staff_list()
    {
        $type = $this->input->get('type');
        $division_id = $this->input->get('division_id');
        $period = $this->input->get('period');
        $data['period']= $period;
        $data['type'] = $type;
        $data['module'] = "performance";
        if($type== 'total'){
        $data['title'] = 'Total PPAs Submitted';
        }
        else  if($type== 'approved'){
        $data['title'] = 'Staff PPAs Approved';

        }
        else  if($type== 'with_pdp'){
            $data['title'] = 'Staff with PDPs';
    
        } else if($type=='without_ppa'){
            $data['title'] = 'Staff without PPAs';
        }
        // You can reuse your model to fetch based on type
        $data['staff_list'] = $this->per_mdl->get_staff_by_type($type, $division_id, $period);

        render('staff_list',$data);
    }
    public function all_ppas()
{

    $data['module'] = "performance";

    $filters = [
        'staff_name' => $this->input->get('staff_name'),
        'draft_status' => $this->input->get('draft_status'),
        'created_at' => $this->input->get('created_at'),
        'division_id' => $this->input->get('division_id'),
        'period' => str_replace(' ','-',current_period())
    ];

    $per_page = 40;
    $offset = (int) $this->input->get('offset');

    // Export to Excel
    if ($this->input->get('export') === 'excel') {
        $data = $this->per_mdl->get_all_ppas_filtered($filters, 0, 0); // all records
        render_csv_data($data, 'all_ppa_entries.csv');
    }

    // Export to PDF
    if ($this->input->get('export') === 'pdf') {
        $data['plans'] = $this->per_mdl->get_all_ppas_filtered($filters, 0, 0);
        pdf_print_data($data, 'ppa_report.pdf', 'L', 'pdfs/all_pdf_ppa');
        return;
    }
     // Export to PDF
     if ($this->input->get('export') === 'pdf2') {
        $data['plans'] = $this->per_mdl->get_all_ppas_filtered($filters, 0, 0);
        pdf_print_data($data, 'ppa_report.pdf', 'L', 'pdfs/simple');
        return;
    }

    $data['plans'] = $this->per_mdl->get_all_ppas_filtered($filters, $per_page, $offset);
    $data['filters'] = $filters;
    $data['divisions'] = $this->db->get('divisions')->result();
    $data['periods'] = $this->db->query('SELECT distinct performance_period FROM ppa_entries')->result();
    $data['total'] = $this->per_mdl->count_ppas_filtered($filters);

    $data['links'] = pagination('performance/all_ppas', $data['total'], $per_page,3);
    $data['title'] = 'All Staff PPAs';

    render('all_ppas', $data);
}
public function ppa_contract($contract_id){

    $this->db->where('staff_contract_id',$contract_id);
    $this->db->join('jobs', 'staff_contracts.job_id=jobs.job_id');
    $this->db->join('staff', 'staff.staff_id=staff_contracts.staff_id');
    $this->db->join('jobs_acting', 'staff_contracts.job_acting_id=jobs_acting.job_acting_id');
    $data=  $this->db->get('staff_contracts')->row();
    return $data;
  }

       // In models/Au_model.php
       public function get_competencies_by_version($version = 1)
       {
           $query = $this->db->where('version', $version);
       return  $this->db->get('au_values')->result_array();
             
       
       // /dd($this->db->last_query());
       }      

    /**
     * Update supervisors for PPA entry and staff contract
     * Only allowed if user has permission 83 (allow_return_ppa) and PPA is not approved (draft_status != 2)
     */
    /**
     * Helper method to send JSON response with CSRF token
     */
    private function _send_json_response($success, $message, $is_ajax)
    {
        if (!$is_ajax) {
            return false; // Not an AJAX request, caller should handle redirect
        }
        
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        // Include new CSRF hash if CSRF regeneration is enabled
        if ($this->config->item('csrf_regenerate')) {
            $response['new_csrf_hash'] = $this->security->get_csrf_hash();
        }
        
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($response));
        return true;
    }
    
    public function update_supervisors()
    {
        // Check if this is an AJAX request
        $is_ajax = $this->input->is_ajax_request() || 
                   !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        // Check if user has permission 83 (allow_return_ppa)
        $permissions = $this->session->userdata('user')->permissions ?? [];
        if (!in_array('83', $permissions)) {
            $error_msg = 'You do not have permission to change supervisors';
            if ($this->_send_json_response(false, $error_msg, $is_ajax)) {
                return;
            }
            Modules::run('utility/setFlash', ['msg' => $error_msg, 'type' => 'error']);
            redirect($_SERVER['HTTP_REFERER'] ?? 'performance');
            return;
        }
        
        $entry_id = $this->input->post('entry_id');
        $supervisor_1 = $this->input->post('supervisor_1');
        $supervisor_2 = $this->input->post('supervisor_2');
        $type = $this->input->post('type'); // 'ppa', 'midterm', or 'endterm'
        
        // Debug: Log received data for troubleshooting
        log_message('debug', 'Update supervisors - entry_id: ' . var_export($entry_id, true));
        log_message('debug', 'Update supervisors - type: ' . var_export($type, true));
        log_message('debug', 'Update supervisors - POST data keys: ' . implode(', ', array_keys($this->input->post() ?? [])));
        
        // Check if entry_id is provided and not empty (handle alphanumeric strings)
        // Use trim and check for null/empty string explicitly
        $entry_id = trim($entry_id ?? '');
        if (empty($entry_id) || $entry_id === '' || $entry_id === null) {
            $error_msg = 'Invalid entry ID: Entry ID is required. Received: ' . var_export($this->input->post('entry_id'), true);
            if ($this->_send_json_response(false, $error_msg, $is_ajax)) {
                return;
            }
            Modules::run('utility/setFlash', ['msg' => $error_msg, 'type' => 'error']);
            redirect($_SERVER['HTTP_REFERER'] ?? 'performance');
            return;
        }
        
        // Get PPA entry
        $ppa = $this->db->where('entry_id', $entry_id)->get('ppa_entries')->row();
        
        if (!$ppa) {
            $error_msg = 'PPA entry not found';
            if ($this->_send_json_response(false, $error_msg, $is_ajax)) {
                return;
            }
            Modules::run('utility/setFlash', ['msg' => $error_msg, 'type' => 'error']);
            redirect($_SERVER['HTTP_REFERER'] ?? 'performance');
            return;
        }
        
        // Check if PPA is approved - if so, don't allow changes
        // For endterm, check overall_end_term_status instead
        $is_approved = false;
        if ($type === 'endterm') {
            $is_approved = isset($ppa->overall_end_term_status) && $ppa->overall_end_term_status === 'Approved';
        } else {
            $is_approved = (int)$ppa->draft_status === 2;
        }
        
        if ($is_approved) {
            $error_msg = 'Cannot change supervisors for approved ' . ucfirst($type);
            if ($this->_send_json_response(false, $error_msg, $is_ajax)) {
                return;
            }
            Modules::run('utility/setFlash', ['msg' => $error_msg, 'type' => 'error']);
            redirect($_SERVER['HTTP_REFERER'] ?? 'performance');
            return;
        }
        
        // Prepare update data based on type
        $ppa_update = [];
        $contract_update = [];
        
        if ($type === 'ppa') {
            $ppa_update['supervisor_id'] = $supervisor_1 ?: null;
            $contract_update['first_supervisor'] = $supervisor_1 ?: null;
        } elseif ($type === 'midterm') {
            $ppa_update['midterm_supervisor_1'] = $supervisor_1 ?: null;
            $contract_update['first_supervisor'] = $supervisor_1 ?: null;
        } elseif ($type === 'endterm') {
            $ppa_update['endterm_supervisor_1'] = $supervisor_1 ?: null;
            $ppa_update['endterm_supervisor_2'] = $supervisor_2 ?: null;
            $contract_update['first_supervisor'] = $supervisor_1 ?: null;
            $contract_update['second_supervisor'] = $supervisor_2 ?: null;
        } else {
            $error_msg = 'Invalid type';
            if ($this->_send_json_response(false, $error_msg, $is_ajax)) {
                return;
            }
            Modules::run('utility/setFlash', ['msg' => $error_msg, 'type' => 'error']);
            redirect($_SERVER['HTTP_REFERER'] ?? 'performance');
            return;
        }
        
        $ppa_update['updated_at'] = date('Y-m-d H:i:s');
        
        // Update ppa_entries
        $this->db->where('entry_id', $entry_id)->update('ppa_entries', $ppa_update);
        
        // Update staff_contracts (latest contract for the staff)
        if (!empty($contract_update) && !empty($ppa->staff_contract_id)) {
            $this->db->where('staff_contract_id', $ppa->staff_contract_id)->update('staff_contracts', $contract_update);
        }
        
        // Log the action
        $log_message = "Updated {$type} supervisors for PPA entry [{$entry_id}]";
        log_user_action($log_message);
        
        $success_msg = 'Supervisors updated successfully';
        if ($this->_send_json_response(true, $success_msg, $is_ajax)) {
            return;
        }
        
        Modules::run('utility/setFlash', ['msg' => $success_msg, 'type' => 'success']);
        redirect($_SERVER['HTTP_REFERER'] ?? 'performance');
    }
 
    
	
}
