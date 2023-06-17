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
		if ($status == 2) {
			$data['title'] = "Due Contracts";
		}
		else if ($status == 3) {
			$data['title'] = "Expired Contracts";
		} 
		else if ($status == 4) {
			$data['title'] = "Former Staff";
		}
		$data['staff'] = $this->staff_mdl->get_status($status);
	
		render('contract_status', $data);

	}
	public function staff_birthday()
	{
		$data['module'] = $this->module;
		$data['title'] = "Staff Birthday";
		$data['today'] = $this->staff_mdl->getBirthdaysForToday();
		$data['tomorrow'] = $this->staff_mdl->getBirthdaysForTomorrow();
		$data['week'] = $this->staff_mdl->getBirthdaysForNextSevenDays();
		$data['month'] = $this->staff_mdl->getBirthdaysForNextThirtyDays();
		//dd($data['month']);
		render('staff_birthday', $data);
	}

	
}
