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
		$data['staff'] = $this->dash_mdl->all_staff();
		$data['two_months'] = $this->dash_mdl->due_contracts();
		$data['expired'] = $this->dash_mdl->expired_contracts();
		$data['member_states'] = $this->dash_mdl->nationalities();
		$data['data_points'] = $this->dash_mdl->staff_by_gender();
		$data['staff_by_member_state'] = $this->dash_mdl->staff_by_member_state();
		$data['staff_by_contract'] = $this->dash_mdl->staff_by_contract();
		$data['staff_by_division'] = $this->dash_mdl->staff_by_division();
		$data['uptitle'] = "Main Dashboard";
		//dd($this->dash_mdl->get_all());

		render('home', $data);
	}
	public function dashboardData()
	{

	}
}
