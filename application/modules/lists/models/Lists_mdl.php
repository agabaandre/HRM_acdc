<?php

use Illuminate\Database\Eloquent\Builder;

class Lists_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
        $this->load->model("Divisions");
        $this->load->model("Contractors");
        $this->load->model("ContractType");
        $this->load->model("Funder");
        $this->load->model("Grades");
        $this->load->model("Jobs");
        $this->load->model("Jobsacting");
        $this->load->model("Nationality");
        $this->load->model("Stations");
        $this->load->model("Status");
    }

    public function supervisor(){
            return employee::all();
    }
    public function divisions()
    {
        return divisions::all();
    }
    public function contracts()
    {
    return divisions::all();
    }
    public function funder()
    {
        return funder::all();
    }
        public function grades()
    {
        return grades::all();
    }
    public function jobs()
    {
        return jobs::all();
    }
    public function contracttype()
    {
        return contracttype::all();
    }
    public function contractors()
    {
        return Contractors::all();
    }

    public function jobsacting()
    {
        return jobsacting::all();
    }
    public function nationality()
    {
        return nationality::all();
    }
    public function stations()
    {
        return stations::all();
    }
    public function status()
    {
        return status::all();
    }


}