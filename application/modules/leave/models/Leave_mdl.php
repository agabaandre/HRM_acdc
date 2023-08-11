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
         else if ($role == 'hr') {
            $data['approval_status1'] = $message;
         } else if ($role == 'supervisor') {

            $data['approval_status2'] = $message;
        }
          else if ($role == 'hod') {
            $data['approval_status3'] = $message;
        }
            //get leave status at all levels to give a final overall status
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('request_id', $request_id);
        $res = $this->db->update('staff_leave',$data);
       // final_status
        $status = $this->get_leave_status($request_id);
        if ($status->approval_status3 == 'Approved') {
            $data['overall_status'] = 'Approved';
        } else {
            $data['overall_status'] = 'Rejected';
        }
        if ($role == 'hod') {
            $this->final_status($data, $request_id);
        }
        return $res;
    }
    public function final_status($data, $request_id){
        $this->db->where('request_id', $request_id);
        $res = $this->db->update('staff_leave', $data);
        return $res;
    }

    public function get_leave_status($request_id)
    {
        // Update the approval_status field to 'Approved' in the 'staff_leave' table for the specified leave ID
              $this->db->where('request_id', $request_id);
             $result = $this->db->get('staff_leave')->row();
        return $result;
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

$staff_id = $this->session->userdata('user')->staff_id;

$status = isset($status) ? $status : '';
$start_date = isset($start_date) ? $start_date : '';
$end_date = isset($end_date) ? $end_date : '';

$where = "";

if ($status) {
    $where .= "AND l.approval_status = '$status'";
}

if ($start_date) {
    $sdate = date('Y-m-d', strtotime($start_date));
    $where .= " AND l.start_date >= '$sdate'";
}

if ($end_date) {
    $e_date = date('Y-m-d', strtotime($end_date));
    $where .= " AND l.end_date <= '$e_date'";
}

$query = $this->db->query("SELECT l.*, s.fname, s.lname, lt.leave_name 
            FROM staff_leave l 
            JOIN staff s ON l.staff_id = s.staff_id 
            JOIN leave_types lt ON l.leave_id = lt.leave_id 
            WHERE l.overall_status = 'Pending' $where
            AND (l.supervisor_id = $staff_id OR l.staff_id = $staff_id OR l.supporting_staff = $staff_id OR l.division_head = $staff_id OR l.staff_id IN(select user.staff_id from user where role=20)) 
            ORDER BY l.start_date DESC");

return $query->result_array();
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

 
    
  
