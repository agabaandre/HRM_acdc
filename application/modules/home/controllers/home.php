<?php

use SebastianBergmann\Type\FalseType;

defined('BASEPATH') or exit('No direct script access allowed');

class Home extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->module = "home";
	
	}

	public function index()
	{
	
		$data['module'] = $this->module;
		$data['title'] = "Home";
	
		
		render('home', $data);
	}
}