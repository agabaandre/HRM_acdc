<?php

class Leave_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }
    public function approve_leave($request_id)
    {
        // Update the approval_status field to 'Approved' in the 'staff_leave' table for the specified leave ID
        $this->db->set('approval_status', 'Approved');
        $this->db->where('request_id', $request_id);
        $this->db->update('staff_leave');
    }

    public function reject_leave($request_id)
    {
        // Update the approval_status field to 'Rejected' in the 'staff_leave' table for the specified leave ID
        $this->db->set('approval_status', 'Rejected');
        $this->db->where('request_id', $request_id);
        $this->db->update('staff_leave');
    }
//get for approval
    public function get_leaves($status, $start_date, $end_date)
    {
        // Fetch the leave data from the 'staff_leave' table based on the specified filters
        $this->db->select('l.*, s.fname, s.lname, lt.leave_name');
        $this->db->from('staff_leave l');
        $this->db->join('staff s', 'l.staff_id = s.staff_id');
        $this->db->join('leave_types lt', 'l.leave_id = lt.leave_id');
    

        if ($status) {
            $this->db->where('l.approval_status', $status);
        }

        if ($start_date) {
            $this->db->where('l.start_date >=', $start_date);
        }

        if ($end_date) {
            $this->db->where('l.end_date <=', $end_date);
        }

        return $this->db->get()->result_array();
    }
    public function my_leave($status, $start_date, $end_date)
    {
        // Select leave data from the 'staff_leave' table based on the specified filters
        $this->db->select('l.*, s.fname, s.lname, lt.leave_name');
        $this->db->from('staff_leave l');
        $this->db->join('staff s', 'l.staff_id = s.staff_id');
        $this->db->join('leave_types lt', 'l.leave_id = lt.leave_id');

        if ($status !== '') {
            $this->db->where('l.approval_status', $status);
        }

        if ($start_date !== '') {
            $this->db->where('l.start_date >=', $start_date);
        }

        if ($end_date !== '') {
            $this->db->where('l.end_date <=', $end_date);
        }

        // Order the records with the most recent applications on top
        $this->db->order_by('l.created_at', 'desc');

        return $this->db->get()->result_array();
    }
    
    public function save_leave($data){
        $query = $this->db->insert('staff_leave', $data);
       if($query){
            return 'Application Submitted';
       }
       else{
            return 'Failed to Submit Application';


       }
    }



}

 
    
  
