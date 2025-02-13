<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class TaskPlanner_model extends CI_Model {

    // Add Activity
    public function add_activity($data) {
        return $this->db->insert('activities', $data);
    }

    // Get Deliverables
    public function get_deliverables() {
        return $this->db->get('deliverables')->result();
    }

    // Get Activities
    public function get_activities($staff_id) {
        $this->db->where('staff_id', $staff_id);
        return $this->db->get('activities')->result();
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
}
?>