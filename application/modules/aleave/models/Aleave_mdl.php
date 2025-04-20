<?php

class Aleave_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }


    public function get_all_leave_statements()
    {
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('staff_leave_annual_statement')->result();
    }

    
}
