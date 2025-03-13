<?php

use SebastianBergmann\Type\FalseType;

defined('BASEPATH') or exit('No direct script access allowed');

class Performance extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "performance";
		$this->load->model("performance_mdl",'per_mdl');
	}

	public function index()
	{
		$data['module'] = $this->module;
		$data['title'] = "Performance Plan - ".$this->session->userdata('user')->name;
		$data['skills'] = $this->db->get('training_skills')->result();
		render('plan', $data);
	}
	public function appraisal()
	{
		$data['module'] = $this->module;
		$data['title'] = "Performance Plan";
		render('plan', $data);
	}
	function save_ppa(){
		$data= $this->input->post();
        $staff_id = $this->session->userdata('user')->staff_id;
		$unique_key = str_replace(' ', '', $data['performance-period']) . $this->session->userdata('user')->staff_id;
		$primary = array(
			'period' => $data['performance-period'],
			'staff_id' => $this->session->userdata('user')->staff_id,
			'unique_key'=> $unique_key,
			'supervisor_id' => $data['supervisor_id'],
			'supervisor2_id' => $data['supervisor2_id'],
			'training_contributions' => $data['training_contributions'],
			'recommended_trainings' => $data['recommended_trainings'],
			'objectives' =>json_encode($data['objective']),
			'activities' => json_encode($data['activityName']),
			'kpis' => json_encode($data['kpiName']),
			'skill_areas'=>json_encode($data['required_skills']),
			'staff_sign_off' => $this->session->userdata('user')->signature,
			'staff_sign_off_date'=>date('Y-m-d H:i:s'),
			'timeline_start' =>json_encode($data['timeline_start']),
			'timeline_end' => json_encode($data['timeline_end']),
			'weight' => json_encode($data['weight'])
		);
		 $this->db->insert('ppa_primary', $primary);

		if($this->db->affected_rows()>0) {
			$msg = array(
				'msg' => 'Submission Successfull',
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
			redirect('performance');
		} else {
			$msg = array(
				'msg' => 'Failed to Submit',
				'type' => 'error'
			);
		}
		redirect('performance');

		
	}


	public function myplans()
	{
		$id = $this->input->get('staff_id');
		$period = @urldecode($this->input->get('period'));
		$data['module'] = $this->module;
		$data['title'] = "Staff Performance Plans";
		$status = $this->input->get('status');
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['plans'] = $this->per_mdl->myplans($per_page=10, $page, $id, $period, $status);
		//dd($data['plans']);
		$data['links'] = pagination('performance/myplans', count($data['plans']),3);

	render('staff_ppa', $data);
		
	}
	

	
}
