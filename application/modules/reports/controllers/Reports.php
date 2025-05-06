<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Reports extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "reports";
		$this->load->model("reports_mdl",'reports_mdl');
	}

	public function index()
	{
		$data['module'] = $this->module;
		$data['title'] = "reports";	
		render('index', $data);
	}
	public function staff()
	{
		$data['module'] = $this->module;
		$data['title'] = "reports";
		$data['staff'] = $this->staff_mdl->get_all();

		render('staff', $data);
	}
	

	
}
