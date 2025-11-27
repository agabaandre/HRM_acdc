<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Lists extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "lists";
		$this->load->model("lists_mdl",'lists_mdl');
	}

	
	public function supervisor()
	{
	
		$data = $this->lists_mdl->supervisor();
		return $data;
	}
	public function divisions()
	{

		$data = $this->lists_mdl->divisions();
		return $data;
	}
	public function contracts()
	{

		$data = $this->lists_mdl->contracts();
		return $data;
	}
	public function contractors()
	{

		$data = $this->lists_mdl->contractors();
		return $data;
	}
	public function funder()
	{

		$data = $this->lists_mdl->funder();
		return $data;
	}
	public function grades()
	{

		$data = $this->lists_mdl->grades();
		return $data;
	}
	public function jobs()
	{

		$data = $this->lists_mdl->jobs();
		return $data;
	}


	public function jobsacting()
	{

		$data = $this->lists_mdl->jobsacting();
		return $data;
	}
	public function nationality()
	{
		$data = $this->lists_mdl->nationality();
		
		// If this is an AJAX request, return JSON
		if ($this->input->is_ajax_request() || $this->input->get('format') == 'json') {
			// Convert Eloquent models to arrays
			$nationalities = [];
			foreach ($data as $nat) {
				$nationalities[] = [
					'nationality_id' => $nat->nationality_id,
					'nationality' => $nat->nationality ?? '',
					'nationality_name' => $nat->nationality_name ?? '',
					'status' => $nat->status ?? ''
				];
			}
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output(json_encode($nationalities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			return;
		}
		
		return $data;
	}
	public function stations()
	{
		$data = $this->lists_mdl->stations();
		return $data;
	}
	public function contracttype()
	{
		$data = $this->lists_mdl->contracttype();
		//dd($data);
		return $data;
	}
	public function status()
	{
		$data = $this->lists_mdl->status();
		//dd($data);
		return $data;
	}
	public function leave()
	{
		$data = $this->lists_mdl->leave();
		//dd($data);
		return $data;
	}
	public function units($staff_id)
	{
		if(!empty($staff_id)){
			$this->db->where('staff_id',$staff_id);
		}
		$data = $this->db->get('units')->result();
		//dd($data);
		return $data;
	}

	public function get_units_by_division($id)
	{
		$this->db->where('division_id', $id);
		$data = $this->db->get('units')->result();
		
		$this->output
			 ->set_content_type('application/json')
			 ->set_output(json_encode($data));
	}
	

	
}
