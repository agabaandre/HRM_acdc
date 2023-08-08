<?php
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
		$data['title'] = "Performance Plan";
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
			'required_skills' => $data['required_skills'],
			'staff_sign_off' => $data['staff_sign_off']
		);
		//dd($data);
		
		 $this->db->insert('ppa_primary', $primary);
		 $last_id =$this->db->insert_id();
		$last_id = 2;
		if ($last_id > 0):

			//save skills
		$this->save_skills($last_id, $unique_key,$staff_id,$data);
		for ($i=0; $i < count($data['objective']) ; $i++):
			$objectives = $data['objective'][$i];

			foreach ($objectives as $objective):
				//objective
			$obj = array(
				'ppa_id'=>$last_id,
				'unique_key'=> $unique_key,
				'objective'=> $objective,
				'timeline'=>$data['timeline'][$i][0],
				'weight'=> $data['weight'][$i][0],
				'staff_id'=> $staff_id
				
			);
					
				$this->db->insert('ppa_objective', $obj);
				$objective_id = $this->db->insert_id();
				//objectve activities
					$res1 = $this->save_obj_activities($objective_id, $unique_key, $i, $data, $last_id, $staff_id);
					$res2 = $this->save_obj_kpi($objective_id, $unique_key, $i, $data, $last_id,$staff_id);
		endforeach;
		endfor;
		endif;

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
	function save_obj_activities($objective_id, $unique_key, $i, $data,$last_id,$staff_id){

	  $activities = $data['activityName'][$i];

		foreach ($activities as $activity):

			$act = array(
				'ppa_id' => $last_id,
				'unique_key' => $unique_key,
				'objective_id' => $objective_id,
				'activity_name' => $activity,
				'staff_id' => $staff_id
			);
			$this->db->insert('ppa_activities',$act);
		endforeach;
	}
	function save_obj_kpi($objective_id, $unique_key, $i, $data, $last_id,$staff_id)
	{

		$kpis = $data['kpiName'][$i];

		foreach ($kpis as $kpi) :

			$kdata = array(
				'ppa_id' => $last_id,
				'unique_key' => $unique_key,
				'objective_id' => $objective_id,
				'kpi_name' => $kpi,
				'staff_id' => $staff_id
			);
			$this->db->insert('ppa_kpis', $kdata);
		endforeach;
	}
	function save_skills($last_id, $unique_key, $staff_id, $data){
        $skills = $data['required_skills'];
		foreach ($skills as $skill):
			$ins = array(
				'ppa_id' => $last_id,
				'unique_key' => $unique_key,
				'staff_id' => $staff_id,
				'skill_id' => $skill
			);
			$this->db->insert('ppa_required_skills',$ins);
		endforeach;

	}
	

	
}
