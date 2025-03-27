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
		$data['ppa'] = $this->per_mdl->get_staff_plan($staff_id);
		render('plan', $data);
	}

	public function save_ppa()
	{
		$data = $this->input->post();
		$staff_id = $this->session->userdata('user')->staff_id;
		$performance_period = current_period();
		$entry_id = $staff_id . '_' . str_replace(' ', '', $performance_period);

		$save_data = [
			'staff_id' => $staff_id,
			'performance_period' => $performance_period,
			'entry_id' => $entry_id,
			'supervisor_id' => $data['supervisor_id'],
			'supervisor2_id' => ($data['supervisor2_id'] == '0') ? null : $data['supervisor2_id'],
			'objectives' => json_encode($data['objectives']),
			'training_recommended' => $data['training_recommended'] ?? 'No',
			'required_skills' => isset($data['required_skills']) ? json_encode($data['required_skills']) : null,
			'training_contributions' => $data['training_contributions'] ?? null,
			'recommended_trainings' => $data['recommended_trainings'] ?? null,
			'recommended_trainings_details' => $data['recommended_trainings_details'] ?? null,
			'staff_sign_off' => isset($data['staff_sign_off']) ? 1 : 0,
			'draft_status' => $data['submit_action'] === 'submit' ? 0 : 1,
			'updated_at' => date('Y-m-d H:i:s')
		];

		// Check if it's a new entry or update
		$exists = $this->per_mdl->get_staff_plan($staff_id, $performance_period);
		if ($exists) {
			$this->db->where('entry_id', $entry_id)->update('ppa_entries', $save_data);
		} else {
			$save_data['created_at'] = date('Y-m-d H:i:s');
			$this->db->insert('ppa_entries', $save_data);
		}

		$msg = [
			'msg' => $data['submit_action'] === 'submit' ? 'Plan submitted to supervisor.' : 'Draft saved successfully.',
			'type' => 'success'
		];
		Modules::run('utility/setFlash', $msg);
		redirect('performance/view_ppa/' . $entry_id);
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
	
	
}
