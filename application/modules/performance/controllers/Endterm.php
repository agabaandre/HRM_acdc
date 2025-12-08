<?php

use SebastianBergmann\Type\FalseType;

defined('BASEPATH') or exit('No direct script access allowed');

class Endterm extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->module = "performance";
		$this->load->model("performance_mdl", 'per_mdl');
        $this->load->model("endterm_mdl", 'endterm_mdl');
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
    
        // Get existing data to preserve fields that aren't being updated
        $exists = $this->db->where('entry_id', $entry_id)->get('ppa_entries')->row();
        
        // Prepare endterm save data - ensure we're saving to ENDTERM fields only
        // IMPORTANT: Only use endterm_* field names, never midterm_* fields
        // Only update fields that are actually provided in POST data to avoid clearing existing data
        $save_data = [];
        
        // Only update objectives if provided
        if (isset($data['objectives']) && !empty($data['objectives'])) {
            $save_data['endterm_objectives'] = json_encode($data['objectives']);
        }
        
        // Only update supervisor IDs if provided
        if (isset($data['supervisor_id'])) {
            $save_data['endterm_supervisor_1'] = $data['supervisor_id'];
        }
        if (isset($data['supervisor2_id'])) {
            $save_data['endterm_supervisor_2'] = $data['supervisor2_id'];
        }
        
        // Only update competency if provided
        if (isset($data['endterm_competency']) && !empty($data['endterm_competency'])) {
            $save_data['endterm_competency'] = json_encode($data['endterm_competency']);
        }
        
        // Only update other fields if provided (preserve existing if not provided)
        if (isset($data['endterm_comments'])) {
            $save_data['endterm_comments'] = $data['endterm_comments'];
        }
        if (isset($data['endterm_training_review'])) {
            $save_data['endterm_training_review'] = $data['endterm_training_review'];
        }
        if (isset($data['endterm_recommended_skills']) && !empty($data['endterm_recommended_skills'])) {
            $save_data['endterm_recommended_skills'] = json_encode($data['endterm_recommended_skills']);
        }
        if (isset($data['endterm_achievements'])) {
            $save_data['endterm_achievements'] = $data['endterm_achievements'];
        }
        if (isset($data['endterm_non_achievements'])) {
            $save_data['endterm_non_achievements'] = $data['endterm_non_achievements'];
        }
        if (isset($data['endterm_training_contributions'])) {
            $save_data['endterm_training_contributions'] = $data['endterm_training_contributions'];
        }
        if (isset($data['endterm_recommended_trainings'])) {
            $save_data['endterm_recommended_trainings'] = $data['endterm_recommended_trainings'];
        }
        if (isset($data['endterm_recommended_trainings_details'])) {
            $save_data['endterm_recommended_trainings_details'] = $data['endterm_recommended_trainings_details'];
        }
        
        // Always update these fields
        $save_data['endterm_rating_by'] = $this->session->userdata('user')->staff_id;
        $save_data['endterm_sign_off'] = 1;
        
        // Only update draft_status if endterm_submit_action is provided
        if (isset($data['endterm_submit_action'])) {
            $save_data['endterm_draft_status'] = $data['endterm_submit_action'] === 'submit' ? 0 : 1;
        }
        
        $save_data['endterm_updated_at'] = date('Y-m-d H:i:s');
        
        // Safety check: Remove any midterm fields that might have accidentally been included
        foreach ($save_data as $key => $value) {
            if (strpos($key, 'midterm_') === 0) {
                unset($save_data[$key]);
                log_message('error', "Attempted to save midterm field '$key' in endterm save_ppa - removed!");
            }
        }
        
        // First-time save sets endterm_created_at
        if (empty($exists->endterm_created_at)) {
            $save_data['endterm_created_at'] = date('Y-m-d H:i:s');
        }
    
        // Perform DB update - only endterm fields that are provided
        if (!empty($save_data)) {
            $this->db->where('entry_id', $entry_id)->update('ppa_entries', $save_data);
        }
    
        // Insert into approval trail only on submit
        if ($data['endterm_submit_action'] === 'submit') {
            if($data['staff_id']==$user_id){
                $action='Submitted';
            }
            else{
                $action='Updated';
            }
            // Clean comments - remove any acceptance text that might have been added
            $comments = $data['endterm_comments'] ?? '';
            if ($comments) {
              $comments = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with the staff member\.?\s*/i', '', $comments);
              $comments = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with my supervisor\.?\s*/i', '', $comments);
              $comments = preg_replace('/\s*Second supervisor (agrees|disagrees) with the evaluation\.?\s*/i', '', $comments);
              $comments = preg_replace('/\s*Staff confirmed discussion and (accepted|rejected) the overall rating\.?\s*/i', '', $comments);
              $comments = trim($comments);
            }
            
            $this->db->insert('ppa_approval_trail_end_term', [
                'entry_id'   => $entry_id,
                'staff_id'   => $user_id,
                'comments'   => $comments ?: null,
                'action'     => $action,
                'type'       => 'END-TERM REVIEW',
                'created_at' => date('Y-m-d H:i:s')
            ]);
    
            $save_data['type'] = 'endterm_submission';
            $save_data['entry_id'] =$entry_id;
            $save_data['staff_id'] = $staff_id;
            $save_data['supervisor_id'] = $data['supervisor_id'];
            $this->notify_ppa_status($save_data);
        }
    
        // Notify and redirect
        Modules::run('utility/setFlash', [
            'msg'  => $data['endterm_submit_action'] === 'submit'
                ? 'Endterm submitted successfully.'
                : 'Endterm draft saved successfully.',
            'type' => 'success'
        ]);
    
        redirect("performance/endterm/endterm_review/{$entry_id}/{$staff_id}");
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
		
		// Redirect to endterm review page
		redirect("performance/endterm/endterm_review/{$entry_id}/{$staff_id}");
	}

public function endterm_review($entry_id)
	{
		// Extract staff_id from entry_id
		$staff_id = explode('_', $entry_id)[0];
	
		// Load dependencies
		$data['module'] = $this->module;
		$data['title'] = "Endterm Review - " . staff_name($this->uri->segment(5));
		$data['skills'] = $this->db->get('training_skills')->result();
	
		// Get saved PPA form
		$data['ppa'] = $this->per_mdl->get_plan_by_entry_id($entry_id);

        $data['endppa'] = $this->endterm_mdl->get_plan_by_entry_id($entry_id);
	
		// Get approval logs if any
		$data['approval_trail'] = $this->endterm_mdl->get_approval_trail($entry_id);
	
		render('endterm', $data);
}

	
	public function recent_endterm($entry_id){
		$data['module'] = $this->module;
		$data['title'] = "My Current Endterm - ". $this->session->userdata('user')->name;
		$staff_id = $this->session->userdata('user')->staff_id;
		$performance_period = str_replace(' ','-',current_period());

		$endterm = $this->endterm_mdl->get_recent_endterm_for_user($entry_id, $performance_period);
         //dd($endterm);
		$data['endterm'] = $endterm;
		
		// Get list of periods that have midterm data (midterm_created_at IS NOT NULL)
		$data['periods'] = $this->db->query(
			'SELECT DISTINCT performance_period 
			FROM ppa_entries 
			WHERE staff_id = ? 
			AND midterm_created_at IS NOT NULL 
			ORDER BY performance_period DESC', 
			[$staff_id]
		)->result();
		
		// Check if user has any midterm data
		$data['has_midterm_data'] = !empty($data['periods']);
		$data['staff_id'] = $staff_id;

		render('current_endterm', $data);
	}


    
	public function staff_consent($entry_id)
	{
		$staff_id = $this->session->userdata('user')->staff_id;
		$ppa = $this->per_mdl->get_plan_by_entry_id($entry_id);
		
		// Verify this is the staff member's own endterm
		if ($ppa->staff_id != $staff_id) {
			show_error("Unauthorized access.");
		}
		
		// Check if first supervisor has approved
		$firstSupervisorApproved = false;
		$approval_trail = $this->endterm_mdl->get_approval_trail($entry_id);
		if (!empty($approval_trail)) {
			foreach ($approval_trail as $trail) {
				if ($trail->staff_id == $ppa->endterm_supervisor_1 && $trail->action == 'Approved') {
					$firstSupervisorApproved = true;
					break;
				}
			}
		}
		
		if (!$firstSupervisorApproved) {
			Modules::run('utility/setFlash', [
				'msg' => 'First supervisor must approve before you can give consent.',
				'type' => 'error'
			]);
			redirect("performance/endterm/endterm_review/{$entry_id}/{$staff_id}");
		}
		
		$discussion_confirmed = $this->input->post('staff_discussion_confirmation');
		$rating_acceptance = $this->input->post('staff_rating_acceptance');
		$comments = trim($this->input->post('comments') ?? '');
		
		if (empty($discussion_confirmed) || $rating_acceptance === null) {
			Modules::run('utility/setFlash', [
				'msg' => 'Please complete all required fields.',
				'type' => 'error'
			]);
			redirect("performance/endterm/endterm_review/{$entry_id}/{$staff_id}");
		}
		
		// Update ppa_entries with staff consent
		$update_data = [
			'endterm_staff_discussion_confirmed' => 1,
			'endterm_staff_rating_acceptance' => (int)$rating_acceptance,
			'endterm_staff_consent_at' => date('Y-m-d H:i:s'),
			'endterm_updated_at' => date('Y-m-d H:i:s')
		];
		
		// Check if first supervisor is the same as second supervisor
		$sameSupervisor = !empty($ppa->endterm_supervisor_1) && 
		                  !empty($ppa->endterm_supervisor_2) && 
		                  ((int)$ppa->endterm_supervisor_1 === (int)$ppa->endterm_supervisor_2);
		
		// Determine overall_end_term_status based on staff rating acceptance
		// If staff rejects (rating_acceptance = 0), set to "To be Calibrated"
		if ((int)$rating_acceptance == 0) {
			$update_data['overall_end_term_status'] = 'To be Calibrated';
		} elseif ((int)$rating_acceptance == 1) {
			// If staff accepts and there's no second supervisor, set to "Approved"
			// If first supervisor = second supervisor and both have approved, set to "Approved" or "To be Calibrated" based on agreement
			if (!$ppa->endterm_supervisor_2) {
				$update_data['overall_end_term_status'] = 'Approved';
			} elseif ($sameSupervisor) {
				// Both supervisors are the same person - check their agreement
				$ppa_refresh = $this->per_mdl->get_plan_by_entry_id($entry_id);
				if ((int)$ppa_refresh->endterm_supervisor2_agreement == 0) {
					$update_data['overall_end_term_status'] = 'To be Calibrated';
				} else {
					$update_data['overall_end_term_status'] = 'Approved';
					// Set draft_status to 2 (approved) since both supervisors (same person) have approved and employee consented
					$update_data['endterm_draft_status'] = 2;
				}
			}
			// If there's a different second supervisor, status will be determined when they approve
		}
		
		$this->db->where('entry_id', $entry_id)->update('ppa_entries', $update_data);
		
		// Filter out acceptance text patterns from comments before saving
		if (!empty($comments)) {
			$comments = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with my supervisor\.?\s*/i', '', $comments);
			$comments = preg_replace('/\s*I accept the overall rating assigned by my supervisor\.?\s*/i', '', $comments);
			$comments = preg_replace('/\s*I reject the overall rating assigned by my supervisor\.?\s*/i', '', $comments);
			$comments = trim($comments);
		}
		
		// Log in approval trail - save comments if provided (filtered to remove acceptance text)
		$this->db->insert('ppa_approval_trail_end_term', [
			'entry_id'   => $entry_id,
			'staff_id'   => $staff_id,
			'comments'   => !empty($comments) ? $comments : null,
			'action'     => 'Employee Consent',
			'created_at' => date('Y-m-d H:i:s'),
			'type'       => 'ENDTERM'
		]);
		
		Modules::run('utility/setFlash', [
			'msg' => 'Employee consent recorded successfully.',
			'type' => 'success'
		]);
		
		redirect("performance/endterm/endterm_review/{$entry_id}/{$staff_id}");
	}

	public function approve_ppa($entry_id)
	{
        //draft status 0 is for summitted entries, 1 is in in draft mode, 2 is for approved.
		$staff_id = $this->session->userdata('user')->staff_id;
		$action = $this->input->post('action');
        //FOR LOGGING 
        $staffno = $this->db->query("SELECT staff_id from ppa_entries where entry_id='$entry_id'")->row()->staff_id;
        $name = staff_name($staffno);
        $ppa = $this->per_mdl->get_plan_by_entry_id($entry_id);
     

	
	if (!in_array($action, ['approve', 'return'])) {
		show_error("Invalid action.");
	}

	// Prevent returns when draft_status is 1 (still in draft)
	if ($action === 'return' && (int)@$ppa->endterm_draft_status === 1) {
		Modules::run('utility/setFlash', [
			'msg' => 'Cannot return an endterm review that is still in draft.',
			'type' => 'error'
		]);
		redirect("performance/endterm/endterm_review/{$entry_id}/{$staffno}");
	}

	$log_action = $action === 'approve' ? 'Approved' : 'Returned';
		
		// Check if first supervisor is the same as second supervisor
		$sameSupervisor = !empty($ppa->endterm_supervisor_1) && 
		                  !empty($ppa->endterm_supervisor_2) && 
		                  ((int)$ppa->endterm_supervisor_1 === (int)$ppa->endterm_supervisor_2);
		
		// Handle first supervisor approval
		if ($action === 'approve' && $staff_id == $ppa->endterm_supervisor_1) {
			$discussion_confirmed = $this->input->post('discussion_confirmation');
			if (empty($discussion_confirmed)) {
				Modules::run('utility/setFlash', [
					'msg' => 'Please confirm that you have discussed the results with the staff member.',
					'type' => 'error'
				]);
				redirect("performance/endterm/endterm_review/{$entry_id}/{$staffno}");
			}
			
			// If first supervisor is the same as second supervisor, also require agreement field
			if ($sameSupervisor) {
				$supervisor2_agreement = $this->input->post('supervisor2_agreement');
				if ($supervisor2_agreement === null) {
					Modules::run('utility/setFlash', [
						'msg' => 'Please indicate whether you agree or disagree with the evaluation.',
						'type' => 'error'
					]);
					redirect("performance/endterm/endterm_review/{$entry_id}/{$staffno}");
				}
				
				// Update both supervisor1 and supervisor2 fields at once
				$update_data = [
					'endterm_supervisor1_discussion_confirmed' => 1,
					'endterm_supervisor2_agreement' => (int)$supervisor2_agreement,
					'endterm_updated_at' => date('Y-m-d H:i:s')
				];
				
				// Note: We don't set draft_status to 2 yet because employee consent is still needed
				// The draft_status will be set to 2 after employee consents
				
				$this->db->where('entry_id', $entry_id)->update('ppa_entries', $update_data);
				
				// Log both approvals in the trail (first supervisor and second supervisor)
				// First supervisor approval
				$comments = $this->input->post('comments') ?? null;
				if ($comments) {
					$comments = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with the staff member\.?\s*/i', '', $comments);
					$comments = preg_replace('/\s*I agree with the evaluation\.?\s*/i', '', $comments);
					$comments = preg_replace('/\s*I disagree with the evaluation\.?\s*/i', '', $comments);
					$comments = trim($comments);
				}
				
				// Log first supervisor approval
				$this->db->insert('ppa_approval_trail_end_term', [
					'entry_id'   => $entry_id,
					'staff_id'   => $staff_id,
					'comments'   => $comments ?: null,
					'action'     => 'Approved',
					'created_at' => date('Y-m-d H:i:s'),
					'type'       => 'ENDTERM'
				]);
				
				// Log second supervisor approval (same person, same timestamp)
				$this->db->insert('ppa_approval_trail_end_term', [
					'entry_id'   => $entry_id,
					'staff_id'   => $staff_id,
					'comments'   => $comments ?: null,
					'action'     => 'Approved',
					'created_at' => date('Y-m-d H:i:s'),
					'type'       => 'ENDTERM'
				]);
				
				// Set flag to skip the normal approval trail logging below
				$skip_normal_trail = true;
			} else {
				// Normal case: only first supervisor approval
				$this->db->where('entry_id', $entry_id)->update('ppa_entries', [
					'endterm_supervisor1_discussion_confirmed' => 1,
					'endterm_updated_at' => date('Y-m-d H:i:s')
				]);
			}
		}
		
		// Handle second supervisor approval (only if not the same as first supervisor)
		if ($action === 'approve' && $staff_id == $ppa->endterm_supervisor_2 && !$sameSupervisor) {
			$supervisor2_agreement = $this->input->post('supervisor2_agreement');
			if ($supervisor2_agreement === null) {
				Modules::run('utility/setFlash', [
					'msg' => 'Please indicate whether you agree or disagree with the evaluation.',
					'type' => 'error'
				]);
				redirect("performance/endterm/endterm_review/{$entry_id}/{$staffno}");
			}
			
			// Refresh ppa to get latest staff_rating_acceptance
			$ppa = $this->per_mdl->get_plan_by_entry_id($entry_id);
			
			// Update ppa_entries with second supervisor agreement
			$update_data = [
				'endterm_supervisor2_agreement' => (int)$supervisor2_agreement,
				'endterm_updated_at' => date('Y-m-d H:i:s')
			];
			
			// Determine overall_end_term_status
			// "To be Calibrated" if:
			// - Employee rejects (endterm_staff_rating_acceptance = 0), OR
			// - Second supervisor disagrees (endterm_supervisor2_agreement = 0)
			// "Approved" if both supervisors agree and employee accepts
			$staff_rating_acceptance = $ppa->endterm_staff_rating_acceptance;
			if ($staff_rating_acceptance == 0 || (int)$supervisor2_agreement == 0) {
				$update_data['overall_end_term_status'] = 'To be Calibrated';
			} else {
				$update_data['overall_end_term_status'] = 'Approved';
			}
			
			// Set draft_status to 2 (approved) after supervisor 2 approves
			$update_data['endterm_draft_status'] = 2;
			
			$this->db->where('entry_id', $entry_id)->update('ppa_entries', $update_data);
		}

	
		// Log approval trail (skip if we already logged both approvals for same supervisor)
		if (!isset($skip_normal_trail) || !$skip_normal_trail) {
			// Get comments and clean them - remove any acceptance text that might have been added
			$comments = $this->input->post('comments') ?? null;
			if ($comments) {
				// Remove acceptance text patterns if they exist in comments
				$comments = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with the staff member\.?\s*/i', '', $comments);
				$comments = preg_replace('/\s*I hereby confirm that I formally discussed the results of this review with my supervisor\.?\s*/i', '', $comments);
				$comments = preg_replace('/\s*Second supervisor (agrees|disagrees) with the evaluation\.?\s*/i', '', $comments);
				$comments = preg_replace('/\s*Staff confirmed discussion and (accepted|rejected) the overall rating\.?\s*/i', '', $comments);
				$comments = preg_replace('/\s*I agree with the evaluation\.?\s*/i', '', $comments);
				$comments = preg_replace('/\s*I disagree with the evaluation\.?\s*/i', '', $comments);
				$comments = trim($comments);
			}
			
			// Save only the actual comments (without acceptance text)
			$this->db->insert('ppa_approval_trail_end_term', [
				'entry_id'   => $entry_id,
				'staff_id'   => $staff_id,
				'comments'   => $comments ?: null,
				'action'     => $log_action,
				'created_at' => date('Y-m-d H:i:s'),
				'type'       => 'ENDTERM'
			]);
		}
	
		// If returned, update the draft status and reset employee consent
		if ($action === 'return') {
			$return_update_data = [
				'endterm_draft_status' => 1,
				'endterm_updated_at'   => date('Y-m-d H:i:s'),
				// Reset employee consent fields
				'endterm_staff_discussion_confirmed' => 0,
				'endterm_staff_rating_acceptance' => NULL,
				'endterm_staff_consent_at' => NULL
			];
			
			// If first supervisor returns, also reset their discussion confirmation
			if ($staff_id == $ppa->endterm_supervisor_1) {
				$return_update_data['endterm_supervisor1_discussion_confirmed'] = 0;
			}
			
			// If second supervisor returns, also reset their agreement
			if ($staff_id == $ppa->endterm_supervisor_2) {
				$return_update_data['endterm_supervisor2_agreement'] = NULL;
			}
			
			$this->db->where('entry_id', $entry_id)->update('ppa_entries', $return_update_data);
       

        $log_message = "Returned PPA entry [{$entry_id}] for staff ID [{$staffno}] , [{$name}]";
        log_user_action($log_message);
		}
        else if ($action === 'approve') {
			// For single supervisor case, determine overall_end_term_status
			if (!$ppa->endterm_supervisor_2 && $staff_id == $ppa->endterm_supervisor_1) {
				// Only one supervisor, check staff rating acceptance
				// Refresh ppa to get latest staff_rating_acceptance
				$ppa = $this->per_mdl->get_plan_by_entry_id($entry_id);
				$staff_rating_acceptance = $ppa->endterm_staff_rating_acceptance;
				
				$update_data = [
					'endterm_updated_at' => date('Y-m-d H:i:s')
				];
				
				// If staff has consented and rejected, set to "To be Calibrated"
				if ($staff_rating_acceptance == 0) {
					$update_data['overall_end_term_status'] = 'To be Calibrated';
				} elseif ($staff_rating_acceptance == 1) {
					$update_data['overall_end_term_status'] = 'Approved';
				}
				
				// If staff has consented, mark as fully approved
				if (!empty($ppa->endterm_staff_consent_at)) {
					$update_data['endterm_draft_status'] = 2; // 2 = approved
					$this->db->where('entry_id', $entry_id)->update('ppa_entries', $update_data);
				}
			}
			// For two supervisors, the logic is handled in the second supervisor approval section above

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
        $data['entry_id'] = $entry_id;

        // If first supervisor approved, send staff consent request email
        if ($action === 'approve' && $staff_id == $ppa->endterm_supervisor_1) {
            $data['type'] = "staff_consent_request";
            $this->notify_ppa_status($data);
        } else {
            $data['type'] = "status_update";
            $this->notify_ppa_status($data);
        }
        
		Modules::run('utility/setFlash', $msg);
		redirect('performance/pending_approval');
	}

    public function notify_ppa_status($data)
    {
        // This is the staff whose PPA it is (PPA owner)

       // dd($data);
        $staff_id = $data['staff_id'];
        $entry_id = $data['entry_id'];
        $supervisor_id = $data['endterm_supervisor_1'];
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
        if ($data['type'] === 'endterm_submission') {
            $entry_log_id = md5($staff_id . '-ENDTERMSUBN-' . date('Y-m-d'));
            if($staff_id == $this->session->userdata('user')->staff_id){
               $subject = "Endterm Review Submission Confirmation " . date('Y-m-d H:i:s');
               $staff_data = array_merge($data, [
                'subject' => $subject,
                'email_to' => $staff_email . ';' . settings()->email,
                'body' => $this->load->view('endterm/emails/submission', $data, true),
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
             $subject ="Endterm Review Details Update Notification " . date('Y-m-d H:i:s');
             $staff_data = array_merge($data, [
                'subject' => $subject,
                'email_to' => $staff_email . ';' . settings()->email,
                'body' => $this->load->view('endterm/emails/notify_changes', $data, true),
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
                'subject' => "Endterm Review Submission Notification " . date('Y-m-d H:i:s'),
                'email_to' => $supervisor_email . ';' . settings()->email,
                'body' => $this->load->view('endterm/emails/supervisor_ppa', $data, true),
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
                'subject' => "Endterm Review Details Update Notification " . date('Y-m-d H:i:s'),
                'email_to' => $supervisor_email . ';' . settings()->email,
                'body' => $this->load->view('endterm/emails/supervisor_ppa', $data, true),
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
    
        // Handle staff consent request notification (when first supervisor approves)
        if ($data['type'] === 'staff_consent_request') {
            $entry_log_id = md5($staff_id . '-ENDTERMSCR-' . date('Y-m-d'));
            $subject = "Endterm Review - Staff Consent Required " . date('Y-m-d H:i:s');
            // Use the supervisor who approved (from supervisor_id in data)
            $approving_supervisor_id = isset($data['supervisor_id']) ? $data['supervisor_id'] : $supervisor_id;
            $data['supervisor_name'] = staff_name($approving_supervisor_id);
            $staff_data = array_merge($data, [
                'subject' => $subject,
                'email_to' => $staff_email . ';' . settings()->email,
                'body' => $this->load->view('endterm/emails/staff_consent_request', $data, true),
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
        
        // Handle status update notifications to staff
        if ($data['type'] === 'status_update') {
            $entry_log_id = md5($staff_id . '-ENDTERMST-' . date('Y-m-d'));
            $data['subject'] = "Endterm Status Update " . date('Y-m-d H:i:s');
            $data['status'] = $data['status'] ?? 'Pending';
            $data['body'] = $this->load->view('endterm/emails/super_ppa_changes', $data, true);
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
    
    

	public function my_endterms()
	{
	    $data['module'] = $this->module;
	    $data['title'] = "My End Term Reviews";
	    
	    $staff_id = $this->session->userdata('user')->staff_id;
	    
	    // Get all approved endterm reviews for this staff
	    $data['plans'] = $this->endterm_mdl->get_all_approved_endterms_for_user($staff_id);

	    // If using pagination links
	    $data['links'] = ''; // placeholder, update if using pagination

	    render('staff_endterm_reviews', $data); // updated view for endterm reviews
	}



public function approved_by_me()
{
    $data['module'] = $this->module;
    $data['title'] = "Endterms I've Approved";

    $supervisor_id = $this->session->userdata('user')->staff_id;
    $data['plans'] = $this->endterm_mdl->get_endterms_approved_by_supervisor($supervisor_id);

    render('approved_by_me_endterm', $data);
}



    
        /**
         * Show the Endterm Dashboard page.
         */
        public function ppa_dashboard()
        {
            $data['module'] = "performance";
            $data['title'] = "Endterm Dashboard";
            // Get divisions list, cache for 2 minutes
            $data['divisions'] = cache_list('divisions', function () {
                return $this->db->order_by('division_name')->get('divisions')->result();
            }, 120);
            // Get funders list, cache for 2 minutes
            $data['funders'] = cache_list('funders', function () {
                return $this->db->order_by('funder')->get('funders')->result();
            }, 120);

            render('endterm_dashboard', $data);
        }

        /**
         * Fetch data for the Endterm Dashboard (AJAX endpoint).
         */
        public function fetch_ppa_dashboard_data()
        {
            $division_id = $this->input->get('division_id');
            $funder_id = $this->input->get('funder_id');
            $period = $this->input->get('period');
            $user = $this->session->userdata('user');

            // Restrict to staff if user is a staff member (role 17)
            $is_restricted = ($user && isset($user->role) && $user->role == 17);
            $staff_id = $is_restricted ? $user->staff_id : null;

            $cache_key = 'endterm_dashboard_' . ($division_id ?: 'all') . '_' . ($funder_id ?: 'all') . '_' . ($period ?: 'current') . ($staff_id ? "_staff_$staff_id" : '');

            // You can enable caching here if desired:
            // $data = cache_list($cache_key, function () use ($division_id, $funder_id, $period, $staff_id) {
            //     return $this->endterm_mdl->get_endterm_dashboard_data($division_id, $funder_id, $period, $staff_id);
            // }, 300);

            // For now, always fetch fresh data
            $data = $this->endterm_mdl->get_endterm_dashboard_data($division_id, $funder_id, $period, $staff_id);

            // Output as JSON for the dashboard JS
            header('Content-Type: application/json');
            echo json_encode($data);
        }
        
    
        
        public function staff_list()
    {
        $type = $this->input->get('type');
        $division_id = $this->input->get('division_id');
        $funder_id = $this->input->get('funder_id');
        $period = $this->input->get('period');
        $data['period']= $period;
        $data['type'] = $type;
        $data['module'] = "performance";
        if($type== 'total'){
        $data['title'] = 'Total  Endterms Submitted';
        }
        else  if($type== 'approved'){
        $data['title'] = 'Staff Endterms Approved';

        }
        else  if($type== 'require_calibration'){
            $data['title'] = 'Staff Requiring Calibration';
    
        } else if($type=='without_ppa'){
            $data['title'] = 'Staff without Endterms';
        }
        // You can reuse your model to fetch based on type
        $data['staff_list'] = $this->endterm_mdl->get_staff_by_type($type, $division_id, $funder_id, $period);

        render('endterm_staff_list',$data);
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

      $data['endppa'] = $this->endterm_mdl->get_plan_by_entry_id($entry_id);
	
		// Get approval logs if any
	  $data['approval_trail'] = $this->endterm_mdl->get_approval_trail($entry_id);
      $data['staff_id'] = $staff_id;

      // Get contract and supervisor info
      $data['contract'] = $this->ppa_contract($staff_contract_id);
      $data['readonly'] = true;
      $file_name= staff_name($staff_id).'_'.$data['ppa']->performance_period.'_Endterm.pdf';

      pdf_print_data($data, $file_name, 'P', 'performance/staff_endterm_print');
  }

 
    
	
}

