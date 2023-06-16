<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Staff extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "staff";
		$this->load->model("staff_mdl",'staff_mdl');
	}

	public function index()
	{
		$data['module'] = $this->module;
		$data['title'] = "Staff";
		$data['staff'] = $this->staff_mdl->get_all();
		

		//dd($data['staff']);
		render('staff', $data);
	}
	public function contract_status($status){
		$data['module'] = $this->module;
		if ($status = 2) {
			$data['title'] = "Due Contracts";
		}
		else if ($status = 3) {
			$data['title'] = "Expired Contracts";
		}
		
		$data['staff'] = $this->staff_mdl->get_status($status);
	
		render('contract_status', $data);

	}

	
}
