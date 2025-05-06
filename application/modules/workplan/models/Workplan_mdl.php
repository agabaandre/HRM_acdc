<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Workplan_mdl extends CI_Model {

    public function get_activities($division_id, $year) {
        $this->db->select('workplan_tasks.*, divisions.division_name');
        $this->db->from('workplan_tasks');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id');
        $this->db->where('workplan_tasks.division_id', $division_id);
        $this->db->where('workplan_tasks.year', $year);
        $this->db->order_by('workplan_tasks.created_at', 'DESC');
        
        return $this->db->get()->result();
    }
    

    public function insert($data) {
        $this->db->insert('workplan_tasks', $data);
        return $this->db->insert_id();
    }

    public function update($id, $data) {
        $log_message = "Workplan task with id ".$id. ' Updated';
		log_user_action($log_message);
        return $this->db->where('id', $id)->update('workplan_tasks', $data);
    }

    public function delete($id) {
        $log_message = "Workplan task with id ".$id. ' Deleted';
		log_user_action($log_message);
        return $this->db->where('id', $id)->delete('workplan_tasks');
    }

    public function get_by_id($id) {
        return $this->db->where('id', $id)->get('workplan_tasks')->row();
    }

    public function search($query, $division_id, $year = null) {
        $this->db->select('workplan_tasks.*, divisions.division_name');
        $this->db->from('workplan_tasks');
        $this->db->join('divisions', 'divisions.division_id = workplan_tasks.division_id');
    
        $this->db->group_start(); // ( ... )
            $this->db->like('activity_name', $query);
            $this->db->or_like('output_indicator', $query);
            $this->db->or_like('broad_activity', $query);
            $this->db->or_like('intermediate_outcome', $query);
            $this->db->or_like('year', $query);
        $this->db->group_end();
    
        $this->db->where('workplan_tasks.division_id', $division_id);
    
        if ($year) {
            $this->db->where('workplan_tasks.year', $year);
        }
    
        $this->db->order_by('workplan_tasks.created_at', 'DESC');
    
        return $this->db->get()->result();
    }
    
}
