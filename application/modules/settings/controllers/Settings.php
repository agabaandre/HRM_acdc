<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "settings";
		$this->load->model("settings_mdl", 'reports_mdl');
	}

	public function index()
	{
		$data['module'] = $this->module;
		$data['title'] = "Settings";
		render('settings', $data);
	}

	public function duty_stations()
	{
		$data['module'] = $this->module;
		$data['title'] = "Settings";
		render('settings', $data);
	}
}
