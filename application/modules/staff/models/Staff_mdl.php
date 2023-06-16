<?php

class Staff_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }

    public function get_all($search = [])
    {
        $query = Employee::orderBy("lname", "desc");

        if (@$search['nationality_id'])
        $query->where('nationality_id', 1);

        $results = $query->with('contracts', 'contracts.funder')->take(20)->skip(20)->get();
        return $results;
    }
 
    
  
}