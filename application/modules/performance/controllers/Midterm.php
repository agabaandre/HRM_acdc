<?php

use SebastianBergmann\Type\FalseType;

defined('BASEPATH') or exit('No direct script access allowed');

class Midterm extends MX_Controller
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
        $performance_period = str_replace(' ','-',current_period());
		// Fetch existing plan if any
        $data['ppa'] = $this->employee_ppa($performance_period,$staff_id);

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
        $entry_id = $data['entry_id'];
        $user_id = $this->session->userdata('user')->staff_id;
    
        // Prepare midterm save data
        $save_data = [
            'midterm_objectives'             => isset($data['objectives']) ? json_encode($data['objectives']) : null,
            'midterm_supervisor_1'           => $data['supervisor_id'] ?? null,
            'midterm_competency'             => isset($data['midterm_competency']) ? json_encode($data['midterm_competency']) : null,
            'midterm_comments'               => $data['midterm_comments'] ?? null,
            'midterm_training_review'        => $data['midterm_training_review'] ?? null,
            'midterm_recommended_skills'     => isset($data['midterm_recommended_skills']) ? json_encode($data['midterm_recommended_skills']) : null,
            'midterm_achievements'           => $data['midterm_achievements'] ?? null,
            'midterm_non_achievements'       => $data['midterm_non_achievements'] ?? null,
            'midterm_training_contributions' => $data['midterm_training_contributions'] ?? null,
            'midterm_recommended_trainings' => $data['midterm_recommended_trainings'] ?? null,
            'midterm_recommended_trainings_details' => $data['midterm_recommended_trainings_details'] ?? null,
            'midterm_rating_by'              => $this->session->userdata('user')->staff_id,
            'midterm_sign_off'               => 1,
            'midterm_draft_status'           => $data['midterm_submit_action'] === 'submit' ? 0 : 1,
            'midterm_updated_at'             => date('Y-m-d H:i:s'),
        ];
        
    //dd($save_data);
        // First-time save sets midterm_created_at
        $exists = $this->db->where('entry_id', $entry_id)->get('ppa_entries')->row();
        if (empty($exists->midterm_created_at)) {
            $save_data['midterm_created_at'] = date('Y-m-d H:i:s');
        }
    
        // Perform DB update
        $this->db->where('entry_id', $entry_id)->update('ppa_entries', $save_data);
    
        // Insert into approval trail only on submit
        if ($data['midterm_submit_action'] === 'submit') {
            if($data['staff_id']==$user_id){
                $action='Submitted';
            }
            else{
                $action='Updated';
            }
            $this->db->insert('ppa_approval_trail_midterm', [
                'entry_id'   => $entry_id,
                'staff_id'   => $user_id,
                'comments'   => $data['midterm_comments'] ?? '',
                'action'     => $action,
                'type'       => 'MID-TERM REVIEW',
                'created_at' => date('Y-m-d H:i:s')
            ]);
    
            $save_data['type'] = 'midterm_submission';
            $save_data['entry_id'] =$entry_id;
            $save_data['staff_id'] = $staff_id;
            $save_data['supervisor_id'] = $data['supervisor_id'];
            $this->notify_ppa_status($save_data);
        }
    
        // Notify and redirect
        Modules::run('utility/setFlash', [
            'msg'  => $data['midterm_submit_action'] === 'submit'
                ? 'Midterm submitted successfully.'
                : 'Midterm draft saved successfully.',
            'type' => 'success'
        ]);
    
        redirect("performance/midterm/midterm_review/{$entry_id}/{$staff_id}");
    }
    
public function create_for_period()
	{
		$staff_id = $this->input->post('staff_id');
		$period = $this->input->post('period');
		
		if (empty($staff_id) || empty($period)) {
			Modules::run('utility/setFlash', [
				'msg' => 'Please select a period',
				'type' => 'error'
			]);
			$current_period = str_replace(' ', '-', current_period());
			$current_entry_id = md5($staff_id . '_' . str_replace(' ', '', $current_period));
			redirect("performance/midterm/recent_midterm/{$current_entry_id}/{$staff_id}");
		}
		
		// Generate entry_id for the selected period (remove spaces and dashes from period)
		$entry_id = md5($staff_id . '_' . str_replace(' ', '', $period));
		
		// Redirect to midterm review page
		redirect("performance/midterm/midterm_review/{$entry_id}/{$staff_id}");
	}
    
public function midterm_review($entry_id)
	{
		// Extract staff_id from entry_id
		$staff_id = explode('_', $entry_id)[0];
	
		// Load dependencies
		$data['module'] = $this->module;
		$data['title'] = "Midterm Review - " . staff_name($this->uri->segment(5));
		$data['skills'] = $this->db->get('training_skills')->result();
	
		// Get saved PPA form
		$data['ppa'] = $this->per_mdl->get_plan_by_entry_id($entry_id);

        $data['midppa'] = $this->midterm_mdl->get_plan_by_entry_id($entry_id);
	
		// Get approval logs if any
		$data['approval_trail'] = $this->midterm_mdl->get_approval_trail($entry_id);
	
		render('midterm', $data);
}

	
	public function recent_midterm($entry_id){
		$data['module'] = $this->module;
		$data['title'] = "My Current Midterm - ". $this->session->userdata('user')->name;
		$staff_id = $this->session->userdata('user')->staff_id;
		$performance_period = str_replace(' ','-',current_period());

		$midterm = $this->midterm_mdl->get_recent_midterm_for_user($entry_id, $performance_period);
         //dd($midterm);
		$data['midterm'] = $midterm;
		
		// Get list of periods for midterm selection
		$data['periods'] = $this->db->query('SELECT DISTINCT performance_period FROM ppa_entries WHERE staff_id = ? ORDER BY performance_period DESC', [$staff_id])->result();
		$data['staff_id'] = $staff_id;

		render('current_midterm', $data);
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
		$this->db->insert('ppa_approval_trail_midterm', [
			'entry_id'   => $entry_id,
			'staff_id'   => $staff_id,
			'comments'   => $this->input->post('comments') ?? null,
			'action'     => $log_action,
			'created_at' => date('Y-m-d H:i:s'),
            'type'=>'MIDTERM'
		]);
	
		// If returned, update the draft status
		if ($action === 'return') {
			$this->db->where('entry_id', $entry_id)->update('ppa_entries', [
				'midterm_draft_status' => 1,
				'midterm_updated_at'   => date('Y-m-d H:i:s')
			]);
       

        $log_message = "Returned PPA entry [{$entry_id}] for staff ID [{$staffno}] , [{$name}]";
        log_user_action($log_message);
		}
        else if ($action === 'approve') {
			$this->db->where('entry_id', $entry_id)->update('ppa_entries', [
				'midterm_draft_status' => 2,
				'midterm_updated_at'   => date('Y-m-d H:i:s')
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

       // dd($data);
        $staff_id = $data['staff_id'];
        $entry_id = $data['entry_id'];
        $supervisor_id = $data['midterm_supervisor_1'];
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
        if ($data['type'] === 'midterm_submission') {
            $entry_log_id = md5($staff_id . '-MIDTERMSUBN-' . date('Y-m-d'));
            if($staff_id == $this->session->userdata('user')->staff_id){
               $subject = "Midterm Review Submission Confirmation " . date('Y-m-d H:i:s');
               $staff_data = array_merge($data, [
                'subject' => $subject,
                'email_to' => $staff_email . ';' . settings()->email,
                'body' => $this->load->view('midterm/emails/submission', $data, true),
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
             $subject ="Midterm Review Details Update Notification " . date('Y-m-d H:i:s');
             $staff_data = array_merge($data, [
                'subject' => $subject,
                'email_to' => $staff_email . ';' . settings()->email,
                'body' => $this->load->view('midterm/emails/notify_changes', $data, true),
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
                'subject' => "Midterm Review Submission Notification " . date('Y-m-d H:i:s'),
                'email_to' => $supervisor_email . ';' . settings()->email,
                'body' => $this->load->view('midterm/emails/supervisor_ppa', $data, true),
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
                'subject' => "Midterm Review Details Update Notification " . date('Y-m-d H:i:s'),
                'email_to' => $supervisor_email . ';' . settings()->email,
                'body' => $this->load->view('midterm/emails/supervisor_ppa', $data, true),
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
            $entry_log_id = md5($staff_id . '-MIDTERMST-' . date('Y-m-d'));
            $data['subject'] = "Midterm Status Update " . date('Y-m-d H:i:s');
            $data['status'] = $data['status'] ?? 'Pending';
            $data['body'] = $this->load->view('midterm/emails/super_ppa_changes', $data, true);
            $data['email_to'] =$supervisor_email . ';' . settings()->email;
    
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
    
    

	public function my_midterms()
	{
	    $data['module'] = $this->module;
	    $data['title'] = "My Mid Term Reviews";
	    
	    $staff_id = $this->session->userdata('user')->staff_id;
	    
	    // Get all approved midterm reviews for this staff
	    $data['plans'] = $this->midterm_mdl->get_all_approved_midterms_for_user($staff_id);

	    // If using pagination links
	    $data['links'] = ''; // placeholder, update if using pagination

	    render('staff_midterm_reviews', $data); // updated view for midterm reviews
	}



public function approved_by_me()
{
    $data['module'] = $this->module;
    $data['title'] = "Midterms I've Approved";

    $supervisor_id = $this->session->userdata('user')->staff_id;
    $data['plans'] = $this->midterm_mdl->get_midterms_approved_by_supervisor($supervisor_id);

    render('approved_by_me_midterm', $data);
}



    
        /**
         * Show the Midterm Dashboard page.
         */
        public function ppa_dashboard()
        {
            $data['module'] = "performance";
            $data['title'] = "Midterm Dashboard";
            // Get divisions list, cache for 2 minutes
            $data['divisions'] = cache_list('divisions', function () {
                return $this->db->order_by('division_name')->get('divisions')->result();
            }, 120);

            render('midterm_dashboard', $data);
        }

        /**
         * Fetch data for the Midterm Dashboard (AJAX endpoint).
         */
        public function fetch_ppa_dashboard_data()
        {
            $division_id = $this->input->get('division_id');
            $period = $this->input->get('period');
            $user = $this->session->userdata('user');

            // Restrict to staff if user is a staff member (role 17)
            $is_restricted = ($user && isset($user->role) && $user->role == 17);
            $staff_id = $is_restricted ? $user->staff_id : null;

            $cache_key = 'midterm_dashboard_' . ($division_id ?: 'all') . '_' . ($period ?: 'current') . ($staff_id ? "_staff_$staff_id" : '');

            // You can enable caching here if desired:
            // $data = cache_list($cache_key, function () use ($division_id, $period, $staff_id) {
            //     return $this->midterm_mdl->get_dashboard_data($division_id, $period, $staff_id);
            // }, 300);

            // For now, always fetch fresh data
            $data = $this->midterm_mdl->get_midterm_dashboard_data($division_id, $period, $staff_id);

            // Output as JSON for the dashboard JS
            header('Content-Type: application/json');
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
        $data['title'] = 'Total  Midterms Submitted';
        }
        else  if($type== 'approved'){
        $data['title'] = 'Staff Midterms Approved';

        }
        else  if($type== 'with_pdp'){
            $data['title'] = 'Staff with PDPs';
    
        } else if($type=='without_ppa'){
            $data['title'] = 'Staff without Midterms';
        }
        // You can reuse your model to fetch based on type
        $data['staff_list'] = $this->midterm_mdl->get_staff_by_type($type, $division_id, $period);

        render('midterm_staff_list',$data);
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

  public function print_ppa($entry_id,$staff_id,$staff_contract_id,$approval_trail=FALSE)
  {
      $this->load->model('performance_mdl', 'per_mdl');


      $data['module'] = "performance";
      $data['title'] = "Printable PPA";
      $data['skills'] = $this->db->get('training_skills')->result();
    // Get saved PPA form
	  $data['ppa'] = $this->per_mdl->get_plan_by_entry_id($entry_id);

      $data['midppa'] = $this->midterm_mdl->get_plan_by_entry_id($entry_id);
	
		// Get approval logs if any
	  $data['approval_trail'] = $this->midterm_mdl->get_approval_trail($entry_id);
      $data['staff_id'] = $staff_id;

      // Get contract and supervisor info
      $data['contract'] = $this->ppa_contract($staff_contract_id);
      $data['readonly'] = true;
      $file_name= staff_name($staff_id).'_'.$data['ppa']->performance_period.'_Midterm.pdf';

      pdf_print_data($data, $file_name, 'P', 'performance/staff_midterm_print');
  }

 
    
	
}
