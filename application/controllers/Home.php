<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Home extends CI_Controller {
	public function __construct() {
		parent::__construct();
		$this->load->model('user');
	}
	public function index() {
		$data['user'] = User::all();
		dd($data);
		$this->load->view('home', $data);
	}
}