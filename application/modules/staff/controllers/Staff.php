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
		$data['uptitle'] = "Staff";

		//dd($data['staff']);
		render('staff', $data);
	}
}
