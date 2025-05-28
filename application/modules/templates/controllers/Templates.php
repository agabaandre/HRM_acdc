<?php
defined('BASEPATH') or exit('No direct script access allowed');


class Templates extends MX_Controller
{
	public function __construct() {
        parent::__construct();
        $this->db->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }

	public function main($data)
	{


		//  check_admin_access();
		if (isset($this->session->userdata('user')->name)) {
			//dd($this->session->userdata('user'));
			$this->load->view('main', $data);
		} else {
			redirect('auth/login');
		}
	}

	public function plain($data)
	{
		if (@user_session()->is_admin)
		//dd($this->session->userdata('user'));
			redirect(base_url('dashboard'));

		$this->load->view('plain', $data);
	}

	public function frontend($data)
	{
		//check_logged_in();
		$this->load->view('site', $data);
	}

	public function dashboards($data)
	{
		//check_logged_in();
		$this->load->view('dashboards', $data);
	}
}
