<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tasks_mdl extends CI_Model {

    // Add Activity
    public function add_activity($data) {
        return $this->db->insert('activities', $data);
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
        $this->db->select('reports.*, activities.activity_name');
        $this->db->from('reports');
        $this->db->join('activities', 'activities.activity_id = reports.activity_id');
        $this->db->where('activities.staff_id', $staff_id);
        return $this->db->get()->result();
    }

    public function get_quarterly_output($quarterly_output_id=FALSE) {
        if($quarterly_output_id){
        $this->db->where('quarterly_outputs.quarterly_output_id', $quarterly_output_id);
        }
        $this->db->order_by('financial_year', 'DESC');
        $this->db->order_by('period', 'ASC');
        return $this->db->get('quarterly_outputs')->result();
    }
    public function get_activities($staff_id = null, $output_id = null, $start_date = null, $end_date = null, $limit = null, $offset = null) {
        $this->db->select('
            activities.activity_id, 
            activities.activity_name, 
            activities.quarterly_output_id, 
            units.staff_id as unit_head,
            quarterly_outputs.name AS quarterly_output_name, 
            activities.start_date, 
            activities.priority, 
            activities.end_date, 
            reports.report_id,
            reports.status as report_status,
            reports.description as report,
            reports.report_date,
            activities.comments, 
            activities.staff_id, 
            activities.status,
            DATEDIFF(activities.end_date, activities.start_date)+1 AS activity_days
        ');
        $this->db->from('activities');
        
        // Apply filters if provided
        if (!empty($output_id)) {
            $this->db->where('activities.quarterly_output_id', $output_id);
        }
        if (!empty($start_date)) {
            $this->db->where('activities.start_date >=', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->where('activities.end_date <=', $end_date);
        }
        
        // Join the quarterly_outputs and units tables
        $this->db->join('quarterly_outputs', 'quarterly_outputs.quarterly_output_id = activities.quarterly_output_id');
        $this->db->join('units', 'units.unit_id = quarterly_outputs.unit_id');
    
        // Use a LEFT JOIN for reports to include all activities
        $this->db->join('reports', 'activities.activity_id = reports.activity_id', 'left');
    
        // Apply limit and offset for pagination
        if ($limit !== null) {
            $this->db->limit($limit, $offset);
        }
        
        $query = $this->db->get();
        return $query->result();
    }
    
    public function get_pending_activities($staff_id=null, $output_id = null, $start_date = null, $end_date = null,$limit = null, $offset = null) {
       $unit_id = $this->session->userdata('user')->unit_id;
        $this->db->select('
        activities.activity_id, 
        activities.activity_name, 
        activities.quarterly_output_id, 
        quarterly_outputs.name AS quarterly_output_name, 
        activities.start_date, 
        activities.priority, 
        activities.end_date, 
        activities.comments, 
        activities.staff_id, 
        activities.status,
        DATEDIFF(activities.end_date, activities.start_date)+1 AS activity_days
    ');
    $this->db->where()('status',0);
    $this->db->where('unit_id',$unit_id);
    $this->db->from('activities');
    
    // Apply filters if provided
    if (!empty($output_id)) {
        $this->db->where('activities.quarterly_output_id', $output_id);
    }
    if (!empty($start_date)) {
        $this->db->where('activities.start_date >=', $start_date);
    }
    if (!empty($end_date)) {
        $this->db->where('activities.end_date <=', $end_date);
    }
    
    // Join the quarterly_outputs table

    $this->db->join('quarterly_outputs', 'quarterly_outputs.quarterly_output_id = activities.quarterly_output_id');
    $this->db->join('units','quarterly_outputs.unit_id=units.unit_id' );
        // Apply limit and offset for pagination
    if ($limit !== null) {
            $this->db->limit($limit, $offset);
    }
    
    $query = $this->db->get();
    return $query->result();
    
    }
    public function get_activities_count($output_id = null, $start_date = null, $end_date = null) {
        $this->db->select('COUNT(*) as total');
        $this->db->from('activities');
        
        if (!empty($output_id)) {
            $this->db->where('quarterly_output_id', $output_id);
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
        $this->db->select('*','units.unit_name');
    $this->db->from('quarterly_outputs');
    
    // Apply filters if provided
    $division_id = $this->session->userdata('user')->division_id;
    if (!empty($division_id)) {
        $this->db->where('units.division_id', $division_id);
    }
    
    if (!empty($output_id)) {
        $this->db->where('quarterly_outputs.quarterly_output_id', $output_id);
    }
    if (!empty($start_date)) {
        $this->db->where('quarterly_outputs.start_date >=', $start_date);
    }
    if (!empty($end_date)) {
        $this->db->where('quarterly_outputs.end_date <=', $end_date);
    }
    
   
        // Apply limit and offset for pagination
    if ($limit !== null) {
            $this->db->limit($limit, $offset);
    }
    $this->db->join('units','units.unit_id=quarterly_outputs.unit_id');
  
    $query = $this->db->get();
    return $query->result();
    
    }

    public function get_reports_full($staff_id = null, $output_id = null, $start_date = null, $end_date = null, $limit = null, $offset = null) {
        $this->db->select('
            activities.activity_id, 
            activities.activity_name, 
            activities.quarterly_output_id, 
            quarterly_outputs.name AS quarterly_output_name, 
            activities.start_date, 
            activities.end_date, 
            activities.comments, 
            activities.staff_id, 
            activities.priority, 
            activities.status AS activity_status,
            DATEDIFF(activities.end_date, activities.start_date)+1 AS activity_days,
            reports.report_id,
            reports.report_date,
            reports.week,
            quarterly_outputs.period,
            units.staff_id as unit_head,
            reports.description AS report_description,
            reports.status AS report_status,
            reports.created_at AS report_created_at,
            reports.updated_at AS report_updated_at
        ');
        $this->db->from('activities');
    
        // Apply filters if provided
        if (!empty($staff_id)) {
            $this->db->group_start();
                $this->db->where('activities.staff_id', $staff_id);
                $this->db->or_where('units.staff_id', $staff_id);
            $this->db->group_end();
        }
        if (!empty($output_id)) {
            $this->db->where('activities.quarterly_output_id', $output_id);
        }
        if (!empty($start_date)) {
            $this->db->where('activities.start_date >=', $start_date);
        }
        if (!empty($end_date)) {
            $this->db->where('activities.end_date <=', $end_date);
        }
        
        // Join the quarterly_outputs table first...
        $this->db->join('quarterly_outputs', 'quarterly_outputs.quarterly_output_id = activities.quarterly_output_id');
        // ...then join the units table using quarterly_outputs
        $this->db->join('units', 'quarterly_outputs.unit_id = units.unit_id');
        
        // Join the reports table using activity_id with a LEFT JOIN so that all activities are included
        $this->db->join('reports', 'reports.activity_id = activities.activity_id', 'left');
        
        // Apply limit and offset for pagination
        if ($limit !== null) {
            $this->db->limit($limit, $offset);
        }
        
        $query = $this->db->get();
        $this->db->last_query();
        return $query->result();
    }
    
    
}