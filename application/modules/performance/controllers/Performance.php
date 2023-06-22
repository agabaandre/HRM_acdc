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
		render('plan', $data);
	}
	public function appraisal()
	{
		$data['module'] = $this->module;
		$data['title'] = "Performance Plan";
		render('plan', $data);
	}
	

	
}
