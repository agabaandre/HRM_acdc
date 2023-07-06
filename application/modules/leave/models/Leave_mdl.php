<?php

class Leave_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }
    public function approve_leave($request_id, $message, $role)
    {
        // Update the approval_status field to 'Approved' in the 'staff_leave' table for the specified leave ID

         if ($role=='supporting_staff'){
            $data['approval_status'] = $message;
         }
         else if ($role == 'supervisor') {
            $data['approval_status1'] = $message;
         }
          else if ($role == 'hod') {
            $data['approval_status2'] = $message;
            //get leave status at all levels to give a final overall status
            $status = $this->get_leave_status($request_id);
            if(($status->approval_status=='Approved')&& ($status->approval_status1 == 'Approved')&& ($status->approval_status2 == 'Approved')&& ($status->approval_status3 == 'Approved')){
                $data['overall_status'] = 'Approved';
            }
            else
            {
                $data['overall_status'] = 'Rejected';
            }
            
         }
         else if  ($role == 'hr'){

            $data['approval_status3'] = $message;
         }
        //dd($data);
        $data['updated_at'] = date('Y-m-d H:i:s');
        
              $this->db->where('request_id', $request_id);
           $res = $this->db->update('staff_leave',$data);
        return $res;
    }

    public function get_leave_status($request_id)
    {
        // Update the approval_status field to 'Approved' in the 'staff_leave' table for the specified leave ID
        $this->db->where('leave_id', $request_id);
        return $this->db->get('staff_leave')->row();
    }

    public function reject_leave($request_id)
    {
        // Update the approval_status field to 'Rejected' in the 'staff_leave' table for the specified leave ID
        $this->db->set('approval_status', 'Rejected');
        $this->db->where('leave_id', $request_id);
        $this->db->update('staff_leave');
    }
//get for approval
    public function get_approval_leaves($status, $start_date, $end_date)
    {
        // Fetch the leave data from the 'staff_leave' table based on the specified filters
        $this->db->select('l.*, s.fname, s.lname, lt.leave_name');
        $this->db->from('staff_leave l');
        $this->db->join('staff s', 'l.staff_id = s.staff_id');
        $this->db->join('leave_types lt', 'l.leave_id = lt.leave_id');
        $this->db->where('s.staff_id!=', $this->session->userdata('user')->staff_id);
        $this->db->or_where('l.supervisor_id', $this->session->userdata('user')->staff_id);
        $this->db->or_where('l.supporting_staff', $this->session->userdata('user')->staff_id);
        $this->db->or_where('l.division_head', $this->session->userdata('user')->staff_id);

        if ($status) {
            $this->db->where('l.approval_status', $status);
        }

        if ($start_date) {
            $this->db->where('l.start_date >=', date('Y-m-d', strtotime($start_date)));
        }

        if ($end_date) {
            $this->db->where('l.end_date <=', date('Y-m-d',strtotime($end_date)));
        }

        return $this->db->get()->result_array();
    }
    public function staff_leave_status($status, $start_date, $end_date)
    {
        // Fetch the leave data from the 'staff_leave' table based on the specified filters
        $this->db->select('l.*, s.fname, s.lname, lt.leave_name');
        $this->db->from('staff_leave l');
        $this->db->join('staff s', 'l.staff_id = s.staff_id');
        $this->db->join('leave_types lt', 'l.leave_id = lt.leave_id');
        $this->db->where('s.staff_id',$this->session->userdata('user')->staff_id);

        if ($status) {
            $this->db->where('l.overall_status', $status);
        
        }

        if ($start_date) {
            $this->db->where('l.start_date >=', date('Y-m-d', strtotime($start_date)));
        }

        if ($end_date) {
            $this->db->where('l.end_date <=', date('Y-m-d', strtotime($end_date)));
        }
        $this->db->order_by('l.start_date', 'DESC');

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
            $this->db->where('l.start_date >=', date('Y-m-d',strtotime($start_date)));
        }

        if ($end_date !== '') {
            $this->db->where('l.end_date <=', date('Y-m-d', strtotime($end_date)));
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

 
    
  
