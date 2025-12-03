<?php

class Apm_mdl extends CI_Model
{
    protected $db;
    
    public function __construct()
    {
        parent::__construct();
        // Use the APM database connection
        $this->db = $this->load->database('apm', TRUE);
    }

    /**
     * Get service requests with optional filtering
     */
    public function get_service_requests($filters = [])
    {
        $this->db->select('sr.*');
        $this->db->from('service_requests sr');
        
        // Filter by overall_status
        if (!empty($filters['overall_status'])) {
            $this->db->where('sr.overall_status', $filters['overall_status']);
        }
        
        // Filter by division_id
        if (!empty($filters['division_id'])) {
            $this->db->where('sr.division_id', $filters['division_id']);
        }
        
        // Filter by staff_id
        if (!empty($filters['staff_id'])) {
            $this->db->where('sr.staff_id', $filters['staff_id']);
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $this->db->where('sr.request_date >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('sr.request_date <=', $filters['date_to']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'sr.id';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'DESC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Get activities with optional filtering
     */
    public function get_activities($filters = [])
    {
        $this->db->select('a.*');
        $this->db->from('activities a');
        
        // Filter by overall_status
        if (!empty($filters['overall_status'])) {
            $this->db->where('a.overall_status', $filters['overall_status']);
        }
        
        // Filter by division_id
        if (!empty($filters['division_id'])) {
            $this->db->where('a.division_id', $filters['division_id']);
        }
        
        // Filter by staff_id
        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', $filters['staff_id']);
        }
        
        // Filter by is_single_memo
        if (isset($filters['is_single_memo'])) {
            $this->db->where('a.is_single_memo', $filters['is_single_memo']);
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $this->db->where('a.date_from >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('a.date_to <=', $filters['date_to']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'a.id';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'DESC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Get non-travel memos with optional filtering
     */
    public function get_non_travel_memos($filters = [])
    {
        $this->db->select('a.*');
        $this->db->from('activities a');
        $this->db->where('a.is_single_memo', 1);
        
        // Filter by overall_status
        if (!empty($filters['overall_status'])) {
            $this->db->where('a.overall_status', $filters['overall_status']);
        }
        
        // Filter by division_id
        if (!empty($filters['division_id'])) {
            $this->db->where('a.division_id', $filters['division_id']);
        }
        
        // Filter by staff_id
        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', $filters['staff_id']);
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $this->db->where('a.date_from >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('a.date_to <=', $filters['date_to']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'a.id';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'DESC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Get special memos with optional filtering
     */
    public function get_special_memos($filters = [])
    {
        $this->db->select('a.*');
        $this->db->from('activities a');
        $this->db->where('a.is_special_memo', 1);
        
        // Filter by overall_status
        if (!empty($filters['overall_status'])) {
            $this->db->where('a.overall_status', $filters['overall_status']);
        }
        
        // Filter by division_id
        if (!empty($filters['division_id'])) {
            $this->db->where('a.division_id', $filters['division_id']);
        }
        
        // Filter by staff_id
        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', $filters['staff_id']);
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $this->db->where('a.date_from >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('a.date_to <=', $filters['date_to']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'a.id';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'DESC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Get ARFs (Request ARFs) with optional filtering
     */
    public function get_request_arfs($filters = [])
    {
        $this->db->select('raf.*');
        $this->db->from('request_arfs raf');
        
        // Filter by overall_status
        if (!empty($filters['overall_status'])) {
            $this->db->where('raf.overall_status', $filters['overall_status']);
        }
        
        // Filter by division_id
        if (!empty($filters['division_id'])) {
            $this->db->where('raf.division_id', $filters['division_id']);
        }
        
        // Filter by staff_id
        if (!empty($filters['staff_id'])) {
            $this->db->where('raf.staff_id', $filters['staff_id']);
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $this->db->where('raf.request_date >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('raf.request_date <=', $filters['date_to']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'raf.id';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'DESC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Get change requests with optional filtering
     */
    public function get_change_requests($filters = [])
    {
        $this->db->select('cr.*');
        $this->db->from('change_request cr');
        
        // Filter by overall_status
        if (!empty($filters['overall_status'])) {
            $this->db->where('cr.overall_status', $filters['overall_status']);
        }
        
        // Filter by parent_memo_model
        if (!empty($filters['parent_memo_model'])) {
            $this->db->where('cr.parent_memo_model', $filters['parent_memo_model']);
        }
        
        // Filter by parent_memo_id
        if (!empty($filters['parent_memo_id'])) {
            $this->db->where('cr.parent_memo_id', $filters['parent_memo_id']);
        }
        
        // Filter by staff_id
        if (!empty($filters['staff_id'])) {
            $this->db->where('cr.staff_id', $filters['staff_id']);
        }
        
        // Filter by date range
        if (!empty($filters['date_from'])) {
            $this->db->where('cr.created_at >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('cr.created_at <=', $filters['date_to']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'cr.id';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'DESC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Get count of records for pagination
     */
    public function count_service_requests($filters = [])
    {
        $this->db->from('service_requests sr');
        
        if (!empty($filters['overall_status'])) {
            $this->db->where('sr.overall_status', $filters['overall_status']);
        }
        if (!empty($filters['division_id'])) {
            $this->db->where('sr.division_id', $filters['division_id']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('sr.staff_id', $filters['staff_id']);
        }
        
        return $this->db->count_all_results();
    }

    public function count_activities($filters = [])
    {
        $this->db->from('activities a');
        
        if (!empty($filters['overall_status'])) {
            $this->db->where('a.overall_status', $filters['overall_status']);
        }
        if (!empty($filters['division_id'])) {
            $this->db->where('a.division_id', $filters['division_id']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', $filters['staff_id']);
        }
        if (isset($filters['is_single_memo'])) {
            $this->db->where('a.is_single_memo', $filters['is_single_memo']);
        }
        
        return $this->db->count_all_results();
    }

    public function count_non_travel_memos($filters = [])
    {
        $this->db->from('activities a');
        $this->db->where('a.is_single_memo', 1);
        
        if (!empty($filters['overall_status'])) {
            $this->db->where('a.overall_status', $filters['overall_status']);
        }
        if (!empty($filters['division_id'])) {
            $this->db->where('a.division_id', $filters['division_id']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', $filters['staff_id']);
        }
        
        return $this->db->count_all_results();
    }

    public function count_special_memos($filters = [])
    {
        $this->db->from('activities a');
        $this->db->where('a.is_special_memo', 1);
        
        if (!empty($filters['overall_status'])) {
            $this->db->where('a.overall_status', $filters['overall_status']);
        }
        if (!empty($filters['division_id'])) {
            $this->db->where('a.division_id', $filters['division_id']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('a.staff_id', $filters['staff_id']);
        }
        
        return $this->db->count_all_results();
    }

    public function count_request_arfs($filters = [])
    {
        $this->db->from('request_arfs raf');
        
        if (!empty($filters['overall_status'])) {
            $this->db->where('raf.overall_status', $filters['overall_status']);
        }
        if (!empty($filters['division_id'])) {
            $this->db->where('raf.division_id', $filters['division_id']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('raf.staff_id', $filters['staff_id']);
        }
        
        return $this->db->count_all_results();
    }

    public function count_change_requests($filters = [])
    {
        $this->db->from('change_request cr');
        
        if (!empty($filters['overall_status'])) {
            $this->db->where('cr.overall_status', $filters['overall_status']);
        }
        if (!empty($filters['parent_memo_model'])) {
            $this->db->where('cr.parent_memo_model', $filters['parent_memo_model']);
        }
        if (!empty($filters['parent_memo_id'])) {
            $this->db->where('cr.parent_memo_id', $filters['parent_memo_id']);
        }
        if (!empty($filters['staff_id'])) {
            $this->db->where('cr.staff_id', $filters['staff_id']);
        }
        
        return $this->db->count_all_results();
    }

    /**
     * Get fund codes with optional filtering
     */
    public function get_fund_codes($filters = [])
    {
        $this->db->select('fc.*, ft.name as fund_type_name, d.name as division_name, f.name as funder_name');
        $this->db->from('fund_codes fc');
        $this->db->join('fund_types ft', 'fc.fund_type_id = ft.id', 'left');
        $this->db->join('divisions d', 'fc.division_id = d.id', 'left');
        $this->db->join('funders f', 'fc.funder_id = f.id', 'left');
        
        // Filter by fund_type_id
        if (!empty($filters['fund_type_id'])) {
            $this->db->where('fc.fund_type_id', $filters['fund_type_id']);
        }
        
        // Filter by division_id
        if (!empty($filters['division_id'])) {
            $this->db->where('fc.division_id', $filters['division_id']);
        }
        
        // Filter by funder_id
        if (!empty($filters['funder_id'])) {
            $this->db->where('fc.funder_id', $filters['funder_id']);
        }
        
        // Filter by year
        if (!empty($filters['year'])) {
            $this->db->where('fc.year', $filters['year']);
        }
        
        // Filter by is_active
        if (isset($filters['is_active'])) {
            $this->db->where('fc.is_active', $filters['is_active']);
        }
        
        // Filter by code (search)
        if (!empty($filters['code'])) {
            $this->db->like('fc.code', $filters['code']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'fc.code';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'ASC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Get fund types with optional filtering
     */
    public function get_fund_types($filters = [])
    {
        $this->db->select('ft.*');
        $this->db->from('fund_types ft');
        
        // Filter by id
        if (!empty($filters['id'])) {
            $this->db->where('ft.id', $filters['id']);
        }
        
        // Filter by name (search)
        if (!empty($filters['name'])) {
            $this->db->like('ft.name', $filters['name']);
        }
        
        // Order by
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'ft.name';
        $order_dir = !empty($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'ASC';
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? $filters['offset'] : 0;
            $this->db->limit($filters['limit'], $offset);
        }
        
        return $this->db->get()->result_array();
    }

    /**
     * Count fund codes
     */
    public function count_fund_codes($filters = [])
    {
        $this->db->from('fund_codes fc');
        
        if (!empty($filters['fund_type_id'])) {
            $this->db->where('fc.fund_type_id', $filters['fund_type_id']);
        }
        if (!empty($filters['division_id'])) {
            $this->db->where('fc.division_id', $filters['division_id']);
        }
        if (!empty($filters['funder_id'])) {
            $this->db->where('fc.funder_id', $filters['funder_id']);
        }
        if (!empty($filters['year'])) {
            $this->db->where('fc.year', $filters['year']);
        }
        if (isset($filters['is_active'])) {
            $this->db->where('fc.is_active', $filters['is_active']);
        }
        if (!empty($filters['code'])) {
            $this->db->like('fc.code', $filters['code']);
        }
        
        return $this->db->count_all_results();
    }

    /**
     * Count fund types
     */
    public function count_fund_types($filters = [])
    {
        $this->db->from('fund_types ft');
        
        if (!empty($filters['id'])) {
            $this->db->where('ft.id', $filters['id']);
        }
        if (!empty($filters['name'])) {
            $this->db->like('ft.name', $filters['name']);
        }
        
        return $this->db->count_all_results();
    }
}

