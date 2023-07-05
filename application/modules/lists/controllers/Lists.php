<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Lists extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "lists";
		$this->load->model("lists_mdl",'lists_mdl');
	}

	
	public function supervisor()
	{
	
		$data = $this->lists_mdl->supervisor();
		return $data;
	}
	public function divisions()
	{

		$data = $this->lists_mdl->divisions();
		return $data;
	}
	public function contracts()
	{

		$data = $this->lists_mdl->contracts();
		return $data;
	}
	public function contractors()
	{

		$data = $this->lists_mdl->contractors();
		return $data;
	}
	public function funder()
	{

		$data = $this->lists_mdl->funder();
		return $data;
	}
	public function grades()
	{

		$data = $this->lists_mdl->grades();
		return $data;
	}
	public function jobs()
	{

		$data = $this->lists_mdl->jobs();
		return $data;
	}


	public function jobsacting()
	{

		$data = $this->lists_mdl->jobsacting();
		return $data;
	}
	public function nationality()
	{
		$data = $this->lists_mdl->nationality();
		return $data;
	}
	public function stations()
	{
		$data = $this->lists_mdl->stations();
		return $data;
	}
	public function contracttype()
	{
		$data = $this->lists_mdl->contracttype();
		//dd($data);
		return $data;
	}
	public function status()
	{
		$data = $this->lists_mdl->status();
		//dd($data);
		return $data;
	}
	public function leave()
	{
		$data = $this->lists_mdl->leave();
		//dd($data);
		return $data;
	}

	
}
