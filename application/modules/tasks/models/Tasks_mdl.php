<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tasks_mdl extends CI_Model {

    // Add Activity
    public function add_activity($data) {
        return $this->db->insert('work_planner_tasks', $data);
    }

    // Get Deliverables
    public function get_deliverables() {
        return $this->db->get('deliverables')->result();
    }

 

    // Submit Report
    public function submit_report($data) {
        return $this->db->insert('reports', $data);
    }

    // Get Reports
    public function get_reports($staff_id) {
        $this->db->select('reports.*, work_planner_tasks.activity_name');
        $this->db->from('reports');
        $this->db->join('work_planner_tasks', 'work_planner_tasks.activity_id = reports.activity_id');
        $this->db->where('work_planner_tasks.staff_id', $staff_id);
        return $this->db->get()->result();
    }

    public function get_output($output_id=FALSE,$division_id=FALSE) {
       
        if($division_id){
            $this->db->where('division_id',$division_id);
            }
        if($output_id){
        $this->db->where('workplan_tasks.id', $output_id);
        }
        $this->db->order_by('year', 'DESC');
        $this->db->order_by('created_at','DESC');
        return $this->db->get('workplan_tasks')->result();
    }
    public function get_work_planner_tasks($staff_id = null, $output_id = null, $start_date = null, $end_date = null, $limit = null, $offset = null) {
        $this->db->select('
            work_planner_tasks.activity_id, 
            work_planner_tasks.activity_name, 
            work_planner_tasks.workplan_id, 
            divisions.division_head,
            workplan_tasks.activity_name AS work_activity_name, 
            work_planner_tasks.start_date, 
            work_planner_tasks.end_date, 
            reports.report_id,
            reports.status as report_status,
            reports.description as report,
            reports.report_date,
            work_planner_tasks.comments, 
            work_planner_tasks.created_by as staff_id, 
            work_planner_tasks.status,
            DATEDIFF(work_planner_tasks.end_date, work_planner_tasks.start_date)+1 AS activity_days
        ');
        $this->db->from('work_planner_tasks');
        
        // Apply filters if provided
        if (!empty($output_id)) {
            $this->db->where('work_planner_tasks.workplan_id', $output_id);
        }
        if (!empty($start_date)) {
            $this->db->where('work_planner_tasks.start_date >=', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->where('work_planner_tasks.end_date <=', $end_date);
        }
        
        // Join the workplan_tasks and divisions tables
        $this->db->join('workplan_tasks', 'workplan_tasks.id = work_planner_tasks.workplan_id');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id');
    
        // Use a LEFT JOIN for reports to include all work_planner_tasks
        $this->db->join('reports', 'work_planner_tasks.activity_id = reports.activity_id', 'left');
    
        // Apply limit and offset for pagination
        if ($limit !== null) {
            $this->db->limit($limit, $offset);
        }
        $this->db->order_by('work_planner_tasks.end_date','ASC');
        $query = $this->db->get();
        return $query->result();
    }
    
    public function get_pending_work_planner_tasks($staff_id=null, $output_id = null, $start_date = null, $end_date = null,$limit = null, $offset = null) {
       $division_id = $this->session->userdata('user')->division_id;
        $this->db->select('
        work_planner_tasks.activity_id, 
        work_planner_tasks.activity_name, 
        work_planner_tasks.workplan_id, 
        workplan_tasks.activity_name AS activity_name, 
        work_planner_tasks.start_date, 
        work_planner_tasks.end_date, 
        work_planner_tasks.comments, 
        work_planner_tasks.staff_id, 
        work_planner_tasks.status,
        DATEDIFF(work_planner_tasks.end_date, work_planner_tasks.start_date)+1 AS activity_days
    ');
    $this->db->where()('status',0);
    $this->db->where('division_id',$division_id);
    $this->db->from('work_planner_tasks');
    
    // Apply filters if provided
    if (!empty($output_id)) {
        $this->db->where('work_planner_tasks.workplan_id', $output_id);
    }
    if (!empty($start_date)) {
        $this->db->where('work_planner_tasks.start_date >=', $start_date);
    }
    if (!empty($end_date)) {
        $this->db->where('work_planner_tasks.end_date <=', $end_date);
    }
    
    // Join the workplan_tasks table

    $this->db->join('workplan_tasks', 'workplan_tasks.id = work_planner_tasks.workplan_id');
    $this->db->join('divisions','workplan_tasks.division_id=divisions.division_id' );
        // Apply limit and offset for pagination
    if ($limit !== null) {
            $this->db->limit($limit, $offset);
    }
    $this->db->order_by('work_planner_tasks.end_date','ASC');
    
    $query = $this->db->get();
    return $query->result();
    
    }
    public function get_work_planner_tasks_count($output_id = null, $start_date = null, $end_date = null) {
        $this->db->select('COUNT(*) as total');
        $this->db->from('work_planner_tasks');
        
        if (!empty($output_id)) {
            $this->db->where('workplan_id', $output_id);
        }
        if (!empty($start_date)) {
            $this->db->where('start_date >=', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->where('end_date <=', $end_date);
        }
        
        $query = $this->db->get();
        return $query->row()->total;
    }

    public function get_outputs($output_id = null, $start_date = null, $end_date = null, $limit = null, $offset = null) {
        $this->db->select('*','divisions.division_name');
    $this->db->from('workplan_tasks');
    
    // Apply filters if provided
    $division_id = $this->session->userdata('user')->division_id;
    if (!empty($division_id)) {
        $this->db->where('divisions.division_id', $division_id);
    }
    
    if (!empty($output_id)) {
        $this->db->where('workplan_tasks.id', $output_id);
    }
    if (!empty($start_date)) {
        $this->db->where('workplan_tasks.start_date >=', $start_date);
    }
    if (!empty($end_date)) {
        $this->db->where('workplan_tasks.end_date <=', $end_date);
    }
    
   
        // Apply limit and offset for pagination
    if ($limit !== null) {
            $this->db->limit($limit, $offset);
    }
    $this->db->join('divisions','divisions.division_id=workplan_tasks.division_id');
  
    $query = $this->db->get();
    return $query->result();
    
    }
    public function get_reports_full(
        $staff_id = null,
        $output_id = null,
        $start_date = null,
        $end_date = null,
        $activity_name = null,
        $report_status = null,
        $employee_name = null,
        $period = null,
        $quarter = null,
        $limit = null,
        $offset = null
    ) {
        $this->db->select('
            work_planner_tasks.activity_id, 
            work_planner_tasks.activity_name, 
            work_planner_tasks.workplan_id, 
            workplan_tasks.activity_name AS activity_name, 
            work_planner_tasks.start_date, 
            work_planner_tasks.end_date, 
            work_planner_tasks.comments, 
            work_planner_tasks.staff_id, 
            work_planner_tasks.status AS activity_status,
            DATEDIFF(work_planner_tasks.end_date, work_planner_tasks.start_date)+1 AS activity_days,
            reports.report_id,
            reports.report_date,
            reports.week,
            reports.supervisor_comment,
            divisions.division_head as unit_head,
            reports.description AS report_description,
            reports.status AS report_status,
            reports.created_at AS report_created_at,
            reports.updated_at AS report_updated_at,
            
        ');
    
        $this->db->from('work_planner_tasks');
    
        // Join the staff table for employee name filter
        $this->db->join('staff', 'staff.staff_id = work_planner_tasks.staff_id', 'left');
        // Join other related tables
        $this->db->join('workplan_tasks', 'workplan_tasks.id = work_planner_tasks.workplan_id');
        $this->db->join('divisions', 'workplan_tasks.division_id = divisions.division_id');
        $this->db->join('reports', 'reports.activity_id = work_planner_tasks.activity_id', 'left');
    
        // Access filter based on logged in staff
        if (!empty($staff_id)) {
            $this->db->group_start();
                $this->db->where('work_planner_tasks.staff_id', $staff_id);
                $this->db->or_where('divisions.division_head', $staff_id);
            $this->db->group_end();
        }
    
        // Existing filters
        if (!empty($output_id)) {
            $this->db->where('work_planner_tasks.workplan_id', $output_id);
        }
        if (!empty($start_date)) {
            $this->db->where('work_planner_tasks.start_date >=', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->where('work_planner_tasks.end_date <=', $end_date);
        }
        if (!empty($activity_name)) {
            $this->db->like('work_planner_tasks.activity_name', $activity_name);
        }
        if (!empty($report_status)) {
            $this->db->where('reports.status', $report_status);
        }
        if (!empty($employee_name)) {
            $this->db->like('staff.name', $employee_name);
        }
        // New filters
     
        // Apply limit and offset for pagination if provided
        if ($limit !== null) {
            $this->db->limit($limit, $offset);
        }
        $this->db->order_by('work_planner_tasks.end_date','ASC');
        $query = $this->db->get();
        return $query->result();
    }
    
    
    
}