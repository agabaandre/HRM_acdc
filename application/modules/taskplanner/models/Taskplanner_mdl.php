<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Taskplanner_mdl extends CI_Model {

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
    public function get_activities($staff_id=null, $output_id = null, $start_date = null, $end_date = null,$limit = null, $offset = null) {
        $this->db->select('
        activities.activity_id, 
        activities.activity_name, 
        activities.quarterly_output_id, 
        quarterly_outputs.name AS quarterly_output_name, 
        activities.start_date, 
        activities.end_date, 
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
    
    // Join the quarterly_outputs table
    $this->db->join('quarterly_outputs', 'quarterly_outputs.quarterly_output_id = activities.quarterly_output_id');
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
}