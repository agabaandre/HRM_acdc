<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->dashmodule = "dashboard";
		$this->load->model("dashboard_mdl",'dash_mdl');
	}

	public function index()
	{
		$data['module'] = $this->dashmodule;
		$data['title'] = "Main Dashboard";
		$data['staff'] = $this->dash_mdl->get_all();
		$data['two_months'] = $this->dash_mdl->get_all();
		$data['expired'] = $this->dash_mdl->get_all();
		$data['member_states'] = $this->dash_mdl->get_all();
		$data['uptitle'] = "Main Dashboard";
	

		render('home', $data);
	}
	public function dashboardData()
	{

	}
}
