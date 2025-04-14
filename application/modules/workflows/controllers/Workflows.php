<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Workflows extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();
		$this->load->model('workflows_mdl');

	
	}

	public function submit($submission_id)
    {
        $submission = $this->workflows_mdl->get_submission($submission_id); // Replace with actual source
        $workflow_id = $submission->workflow_id;

        $next_approver = $this->workflows_mdl->get_next_approver($workflow_id, $submission);
        if (!$next_approver) {
            show_error('No approver found for this submission.');
        }

        // Save to approval trail
        $this->workflows_mdl->record_approval_step($submission_id, $next_approver->staff_id, 'pending');

        // Notify
        $this->session->set_flashdata('success', 'Submission forwarded to next approver.');
        redirect('submissions/view/' . $submission_id);
    }

    public function approve($submission_id)
    {
        $staff_id = $this->session->userdata('user')->staff_id;
        $this->workflows_mdl->update_status($submission_id, $staff_id, 'approved');
        redirect('dashboard');
    }

    public function reject($submission_id)
    {
        $staff_id = $this->session->userdata('user')->staff_id;
        $this->workflows_mdl->update_status($submission_id, $staff_id, 'rejected');
        redirect('dashboard');
    }
}
