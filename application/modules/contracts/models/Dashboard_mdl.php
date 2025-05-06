<?php

class Dashboard_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Staff");
        $this->load->model("Contracts");
    }

    public function get_all(){
    return Staff::all();
    }

    public function two_months_due()
    {
        $today = date('Y-m-d');

        // return staff::where(($today-);
    }
    
  
}