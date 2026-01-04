<?php

class Endterm_mdl extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }



    public function get_plan_by_entry_id($entry_id)
    {
        $query = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id]);
        $result = $query->row();

        if ($result) {
            $result->objectives = $result->objectives ? json_decode($result->objectives, true) : [];
            $result->required_skills = $result->required_skills ? json_decode($result->required_skills, true) : [];

            $result->endterm_objectives = $result->endterm_objectives ? json_decode($result->endterm_objectives, true) : [];
            $result->endterm_competency = $result->endterm_competency ? json_decode($result->endterm_competency, true) : [];
            $result->endterm_recommended_skills = $result->endterm_recommended_skills ? json_decode($result->endterm_recommended_skills, true) : [];
        }

        return $result;
    }

    public function save_endterm_review($entry_id, $data)
    {
        if (empty($entry_id) || empty($data)) return false;

        $this->db->where('entry_id', $entry_id);
        return $this->db->update('ppa_entries', $data);
    }

    public function get_endterm_entries($filters = [], $limit = 40, $offset = 0)
    {
        $this->db->select('pe.*, CONCAT(s.fname, " ", s.lname) AS staff_name');
        $this->db->from('ppa_entries pe');
        $this->db->join('staff s', 's.staff_id = pe.staff_id');

        if (!empty($filters['performance_period'])) {
            $this->db->where('pe.performance_period', $filters['performance_period']);
        }

        if (!empty($filters['endterm_draft_status']) || $filters['endterm_draft_status'] === "0") {
            $this->db->where('pe.endterm_draft_status', $filters['endterm_draft_status']);
        }

        if (!empty($filters['staff_id'])) {
            $this->db->where('pe.staff_id', $filters['staff_id']);
        }

        $this->db->order_by('pe.endterm_updated_at', 'DESC');

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->get()->result_array();
    }

    public function get_endterm_status($entry_id)
    {
        $entry = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id])->row();
        if (!$entry) return 'Not Started';

        if ((int) $entry->endterm_draft_status === 0 && $entry->endterm_sign_off == 1) {
            return 'Submitted';
        } elseif ((int) $entry->endterm_draft_status === 1) {
            return 'Draft';
        } else {
            return 'Not Started';
        }
    }

    public function is_endterm_editable($entry_id, $staff_id)
    {
        $entry = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id])->row();

        if (!$entry) return false;

        $status = (int) $entry->endterm_draft_status;
        $isOwner = $entry->staff_id == $staff_id;

        return ($status === 1 && $isOwner);
    }
    public function get_staff_plan_id($entry_id)
	{
		
		if ($entry_id) {
			$this->db->where('entry_id', $entry_id);
            return $this->db->get('ppa_entries')->row();
		}
        else{
            return FALSE;
        }
		
	}

    
public function get_approval_trail($entry_id)
{
    if (!$entry_id) return [];
    // genrate cuurent employees trails.
    $this->db->where("entry_id","$entry_id");
    $this->db->order_by('id', 'DESC'); // Order by most recent first
    return $this->db->get('ppa_approval_trail_end_term')->result();

}

public function get_pending_ppa($staff_id)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Endterm Supervisor 1 last action
            (
                SELECT a1.action 
                FROM ppa_approval_trail_end_term a1
                WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,

            -- Endterm Supervisor 2 last action
            (
                SELECT a2.action 
                FROM ppa_approval_trail_end_term a2
                WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,

            -- Compute overall status
            CASE 
                WHEN p.endterm_draft_status = 1 THEN 'Pending (Draft)'
                WHEN p.endterm_supervisor_2 IS NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail_end_term a1 
                        WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Approved'

                WHEN p.endterm_supervisor_2 IS NOT NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail_end_term a1 
                        WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved' AND
                    (
                        SELECT a2.action 
                        FROM ppa_approval_trail_end_term a2 
                        WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2 
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Approved'

                WHEN (
                    SELECT a2.action 
                    FROM ppa_approval_trail_end_term a2 
                    WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2 
                    ORDER BY a2.id DESC LIMIT 1
                ) = 'Returned'
                THEN 'Returned'

                WHEN (
                    SELECT a1.action 
                    FROM ppa_approval_trail_end_term a1 
                    WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1 
                    ORDER BY a1.id DESC LIMIT 1
                ) = 'Returned'
                THEN 'Returned'

                WHEN p.endterm_supervisor_2 IS NOT NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail_end_term a1 
                        WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Pending Second Supervisor'

                ELSE 'Pending First Supervisor'
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.endterm_draft_status = 0
        AND (
            -- First endterm supervisor
            (
                p.endterm_supervisor_1 = ? AND (
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail_end_term a1
                        WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                        ORDER BY a1.id DESC LIMIT 1
                    ) IS NULL OR
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail_end_term a1
                        WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                        ORDER BY a1.id DESC LIMIT 1
                    ) != 'Approved'
                )
            )
            OR
            -- Second endterm supervisor (after first has approved)
            (
                p.endterm_supervisor_2 = ? AND (
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail_end_term a1
                        WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                    AND (
                        (
                            SELECT a2.action
                            FROM ppa_approval_trail_end_term a2
                            WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
                            ORDER BY a2.id DESC LIMIT 1
                        ) IS NULL OR
                        (
                            SELECT a2.action
                            FROM ppa_approval_trail_end_term a2
                            WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
                            ORDER BY a2.id DESC LIMIT 1
                        ) != 'Approved'
                    )
                )
            )
        )
        ORDER BY p.endterm_created_at DESC
    ";

    return $this->db->query($sql, [$staff_id, $staff_id])->result_array();
}




public function get_my_ppa($staff_id)
{
    $this->db->where('staff_id', $staff_id);
    $this->db->order_by('updated_at', 'DESC');
    return $this->db->get('ppa_entries')->result_array();
}


public function get_approved_ppas($staff_id, $role)
{
    if ($role === 'admin') {
        $this->db->where('status', 'Approved');
    } else {
        $this->db->where('ppa_entries.staff_id', $staff_id);
        $this->db->where('status', 'Approved');
    }
    $this->db->join('staff', 'staff.staff_id = ppa_entries.staff_id');
    $this->db->select('ppa_entries.*, CONCAT(staff.firstname, " ", staff.lastname) as staff_name');
    return $this->db->get('ppa_entries')->result_array();
}

public function get_all_approved_ppas_for_user($staff_id)
{
    $sql = "
    SELECT 
        p.*, 
        CONCAT(s.fname, ' ', s.lname) AS staff_name,
        'Approved' AS overall_status,
        
        -- Last action by Endterm Supervisor 1
        (
            SELECT a1.action 
            FROM ppa_approval_trail_end_term a1
            WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
            ORDER BY a1.id DESC LIMIT 1
        ) AS supervisor1_action,
        
        -- Last action by Endterm Supervisor 2
        (
            SELECT a2.action 
            FROM ppa_approval_trail_end_term a2
            WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
            ORDER BY a2.id DESC LIMIT 1
        ) AS supervisor2_action

    FROM ppa_entries p
    JOIN staff s ON s.staff_id = p.staff_id
    WHERE p.staff_id = ?

    AND (
        -- Case 1: Only one endterm supervisor and they approved
        (p.endterm_supervisor_2 IS NULL AND
         (
            SELECT a1.action 
            FROM ppa_approval_trail_end_term a1
            WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
            ORDER BY a1.id DESC LIMIT 1
         ) = 'Approved')

        -- Case 2: Both endterm supervisors and both approved
        OR (
            p.endterm_supervisor_2 IS NOT NULL AND
            (
                SELECT a1.action 
                FROM ppa_approval_trail_end_term a1
                WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                ORDER BY a1.id DESC LIMIT 1
            ) = 'Approved' AND
            (
                SELECT a2.action 
                FROM ppa_approval_trail_end_term a2
                WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
                ORDER BY a2.id DESC LIMIT 1
            ) = 'Approved'
        )
    )

    ORDER BY p.endterm_created_at DESC
    ";

    return $this->db->query($sql, [$staff_id])->result_array();
}

public function get_recent_ppas_for_user($staff_id, $period)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Last action by Endterm Supervisor 1
            (
                SELECT a1.action 
                FROM ppa_approval_trail_end_term a1
                WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,
            
            -- Last action by Endterm Supervisor 2
            (
                SELECT a2.action 
                FROM ppa_approval_trail_end_term a2
                WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,
            
            -- Final status decision with draft consideration
            CASE 
                WHEN p.endterm_draft_status = 1 THEN 'Pending (Draft)'
                ELSE (
                    CASE
                        WHEN p.endterm_supervisor_2 IS NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail_end_term a1
                                WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN p.endterm_supervisor_2 IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail_end_term a1
                                WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved' AND
                            (
                                SELECT a2.action 
                                FROM ppa_approval_trail_end_term a2
                                WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN (
                                SELECT a2.action 
                                FROM ppa_approval_trail_end_term a2
                                WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a2.staff_id = p.endterm_supervisor_2
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN (
                                SELECT a1.action 
                                FROM ppa_approval_trail_end_term a1
                                WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN p.endterm_supervisor_2 IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail_end_term a1
                                WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND a1.staff_id = p.endterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Pending Second Supervisor'

                        ELSE 'Pending First Supervisor'
                    END
                )
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.staff_id = ? AND p.performance_period = ?
        ORDER BY p.endterm_created_at DESC
    ";

    return $this->db->query($sql, [$staff_id, $period])->result_array();
}


public function get_approved_by_supervisor($supervisor_id)
{
    $sql = "
        SELECT 
            p.entry_id,
            p.staff_id,
            p.performance_period,
            p.endterm_created_at AS created_at,
            CONCAT(st.fname, ' ', st.lname) AS staff_name,
            a.created_at AS approval_date,
            a.comments

        FROM ppa_entries p
        JOIN staff st ON st.staff_id = p.staff_id
        JOIN ppa_approval_trail_end_term a ON a.id = (
            SELECT MAX(id) 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
            AND action = 'Approved'
        )
        WHERE 
            (p.endterm_supervisor_1 = ? OR p.endterm_supervisor_2 = ?)
        ORDER BY a.created_at DESC
    ";

    return $this->db->query($sql, [$supervisor_id, $supervisor_id])->result_array();
}

public function get_staff_by_type($type, $division_id = null, $funder_id = null, $period = null)
{
    // Get latest contract for each staff
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    switch ($type) {
        case 'total':
        case 'approved':
        case 'require_calibration':
            // For completed reviews: show all who completed regardless of contract status
            // First, get staff who completed endterms
            if ($type === 'total') {
            // Staff who have submitted endterm reviews (not drafts)
            $this->db->select('pe.staff_id, pe.entry_id');
            $this->db->from('ppa_entries pe');
            if ($period) $this->db->where('pe.performance_period', $period);
            $this->db->where('pe.draft_status !=', 1); // PPA submitted
            $this->db->where('pe.endterm_draft_status !=', 1); // Submitted
            } elseif ($type === 'approved') {
            // Staff whose endterm reviews have been approved
            // Filter by overall_end_term_status = 'Approved' OR where status is not null
            // This accounts for three approver checks (first supervisor, second supervisor, employee consent)
            $this->db->select('pe.staff_id, pe.entry_id');
            $this->db->from('ppa_entries pe');
            $this->db->where('pe.draft_status !=', 1);
            $this->db->where('pe.endterm_draft_status !=', 1);
            if ($period) $this->db->where('pe.performance_period', $period);
            // Filter by overall_end_term_status = 'Approved' OR where status is not null
            $this->db->group_start();
            $this->db->where('pe.overall_end_term_status', 'Approved');
            $this->db->or_where('pe.overall_end_term_status IS NOT NULL', null, false);
            $this->db->group_end();
            } elseif ($type === 'require_calibration') {
                // Staff whose endterm reviews require calibration
            $this->db->select('pe.staff_id, pe.entry_id');
            $this->db->from('ppa_entries pe');
            $this->db->where('pe.endterm_draft_status !=', 1); // Submitted
            $this->db->where('pe.draft_status !=', 1); // PPA submitted
            $this->db->where('pe.overall_end_term_status', 'To be Calibrated');
            if ($period) $this->db->where('pe.performance_period', $period);
            }
            
            $completed_entries = $this->db->get()->result();
            if (empty($completed_entries)) return [];
            
            $completed_staff_ids = array_column($completed_entries, 'staff_id');
            $entry_map = [];
            foreach ($completed_entries as $row) {
                $entry_map[$row->staff_id] = $row->entry_id;
            }

            // Now get staff details with contract info (no contract status filter)
            $this->db->select('
                s.staff_id, s.fname, s.lname, s.oname, s.work_email, s.SAPNO,
                d.division_name, ct.contract_type, st.status
            ');
            $this->db->from('staff s');
            $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
            $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
            $this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
            $this->db->join('status st', 'st.status_id = sc.status_id', 'left');
            $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
            $this->db->where_in('s.staff_id', $completed_staff_ids);
            // Apply division and funder filters if provided
            if ($division_id) {
                $this->db->where('sc.division_id', $division_id);
            }
            if ($funder_id) {
                $this->db->where('sc.funder_id', $funder_id);
            }
            
            $staff_list = $this->db->get()->result();
            
            // Get all entry IDs for batch query
            $entry_ids = array_values($entry_map);
            if (empty($entry_ids)) {
                return $staff_list;
            }
            
            // Get all PPA entries in one query
            $ppa_entries = $this->db->where_in('entry_id', $entry_ids)->get('ppa_entries')->result();
            $ppa_map = [];
            foreach ($ppa_entries as $entry) {
                $ppa_map[$entry->entry_id] = $entry;
            }
            
            // Get supervisor names for pending items
            $supervisor_ids = [];
            foreach ($ppa_map as $entry) {
                if (!empty($entry->endterm_supervisor_1)) $supervisor_ids[] = $entry->endterm_supervisor_1;
                if (!empty($entry->endterm_supervisor_2)) $supervisor_ids[] = $entry->endterm_supervisor_2;
            }
            $supervisor_ids = array_unique($supervisor_ids);
            $supervisor_names = [];
            if (!empty($supervisor_ids)) {
                $supervisors = $this->db->select('staff_id, fname, lname')
                    ->where_in('staff_id', $supervisor_ids)
                    ->get('staff')
                    ->result();
                foreach ($supervisors as $sup) {
                    $supervisor_names[$sup->staff_id] = trim($sup->fname . ' ' . $sup->lname);
                }
            }
            
            // Get all approval trail entries in one query
            $approval_trails = $this->db->where_in('entry_id', $entry_ids)->get('ppa_approval_trail_end_term')->result();
            $trail_map = [];
            foreach ($approval_trails as $trail) {
                $key = $trail->entry_id . '_' . $trail->staff_id;
                if (!isset($trail_map[$key]) || $trail->id > $trail_map[$key]->id) {
                    $trail_map[$key] = $trail;
                }
            }
            
            // Add entry_id and approval status to each staff member
            return array_map(function ($staff) use ($entry_map, $ppa_map, $trail_map, $supervisor_names) {
                if (isset($entry_map[$staff->staff_id])) {
                    $staff->entry_id = $entry_map[$staff->staff_id];
                    $entry_id = $entry_map[$staff->staff_id];
                    
                    if (isset($ppa_map[$entry_id])) {
                        $ppa_entry = $ppa_map[$entry_id];
                        
                        // Check first supervisor approval
                        $first_sup_key = $entry_id . '_' . $ppa_entry->endterm_supervisor_1;
                        $first_sup_action = isset($trail_map[$first_sup_key]) ? $trail_map[$first_sup_key]->action : null;
                        $staff->first_supervisor_status = $first_sup_action ?: 'Pending';
                        // Add supervisor name if pending
                        if (!$first_sup_action || $first_sup_action !== 'Approved') {
                            $staff->first_supervisor_name = isset($supervisor_names[$ppa_entry->endterm_supervisor_1]) 
                                ? $supervisor_names[$ppa_entry->endterm_supervisor_1] 
                                : null;
                        } else {
                            $staff->first_supervisor_name = null;
                        }
                        
                        // Check second supervisor approval (if exists)
                        if (!empty($ppa_entry->endterm_supervisor_2)) {
                            $second_sup_key = $entry_id . '_' . $ppa_entry->endterm_supervisor_2;
                            $second_sup_action = isset($trail_map[$second_sup_key]) ? $trail_map[$second_sup_key]->action : null;
                            $staff->second_supervisor_status = $second_sup_action ?: 'Pending';
                            // Add supervisor name if pending
                            if (!$second_sup_action || $second_sup_action !== 'Approved') {
                                $staff->second_supervisor_name = isset($supervisor_names[$ppa_entry->endterm_supervisor_2]) 
                                    ? $supervisor_names[$ppa_entry->endterm_supervisor_2] 
                                    : null;
                            } else {
                                $staff->second_supervisor_name = null;
                            }
                        } else {
                            $staff->second_supervisor_status = 'N/A';
                            $staff->second_supervisor_name = null;
                        }
                        
                        // Check employee consent
                        $staff->employee_consent = !empty($ppa_entry->endterm_staff_consent_at) ? 'Consented' : 'Pending';
                    } else {
                        $staff->first_supervisor_status = 'N/A';
                        $staff->second_supervisor_status = 'N/A';
                        $staff->employee_consent = 'N/A';
                    }
                }
                return $staff;
            }, $staff_list);

        case 'without_ppa':
            // For without_ppa: contract status filtering applies (include Under Renewal = 7)
            // Base staff query with contract status filter
            $this->db->select('
                s.staff_id, s.fname, s.lname, s.oname, s.work_email, s.SAPNO,
                d.division_name, ct.contract_type, st.status
            ');
            $this->db->from('staff s');
            $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
            $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
            $this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
            $this->db->join('status st', 'st.status_id = sc.status_id', 'left');
            $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
            $this->db->where_in('sc.status_id', [1, 2, 7]); // Active, Due, and Under Renewal
            $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]); // Exclude Regular, Fixed, AUYVC, ALD
            if ($division_id) {
                $this->db->where('sc.division_id', $division_id);
            }
            if ($funder_id) {
                $this->db->where('sc.funder_id', $funder_id);
            }

            $active_staff = $this->db->get()->result();
            $staff_ids = array_column($active_staff, 'staff_id');

            if (empty($staff_ids)) return [];

            // Staff who have submitted PPAs but haven't submitted endterm reviews
            $this->db->select('pe.staff_id');
            $this->db->from('ppa_entries pe');
            if ($period) $this->db->where('pe.performance_period', $period);
            $this->db->where('pe.draft_status !=', 1); // PPA submitted
            $this->db->where_in('pe.staff_id', $staff_ids);
            $ppa_submitted = $this->db->get()->result();
            $ppa_submitted_ids = array_column($ppa_submitted, 'staff_id');

            // Get staff who have submitted endterms
            $this->db->select('pe.staff_id');
            $this->db->from('ppa_entries pe');
            if ($period) $this->db->where('pe.performance_period', $period);
            $this->db->where('pe.draft_status !=', 1); // PPA submitted
            $this->db->where('pe.endterm_draft_status !=', 1); // Submitted
            $this->db->where_in('pe.staff_id', $staff_ids);
            $endterm_submitted = $this->db->get()->result();
            $endterm_submitted_ids = array_column($endterm_submitted, 'staff_id');

            // Return staff who have PPAs but no endterms
            $without_endterm_ids = array_diff($ppa_submitted_ids, $endterm_submitted_ids);
            return array_filter($active_staff, fn($s) => in_array($s->staff_id, $without_endterm_ids));

        default:
            return [];
    }
}


public function get_endterm_dashboard_data($division_id = null, $funder_id = null, $period = null, $staff_id = null)
{
    // Get parameters from input if not provided
    if ($division_id === null) $division_id = $this->input->get('division_id');
    if ($funder_id === null) $funder_id = $this->input->get('funder_id');
    if ($period === null) $period = $this->input->get('period');
    
    $current_period = str_replace(' ', '-', current_period());
    $period = !empty($period) ? $period : $current_period;
    
    // Handle multiple periods (comma-separated)
    $periods = !empty($period) ? array_map('trim', explode(',', $period)) : [$current_period];
    $is_multiple_periods = count($periods) > 1;

    $user = $this->session->userdata('user');
    $is_restricted = ($user && isset($user->role) && $user->role == 17);
    if ($staff_id === null) {
        $staff_id = $is_restricted ? $user->staff_id : null;
    }

    // Get latest contract for each staff
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    // For completed reviews: count all regardless of contract status, but apply division/funder filters
    // Summary counts for endterm - only count entries with actual endterm data (regardless of contract status)
    // Use overall_end_term_status to match the staff list filter logic
    $this->db->select("
        COUNT(pe.entry_id) AS total,
        SUM(CASE WHEN pe.overall_end_term_status = 'Approved' THEN 1 WHEN pe.overall_end_term_status IS NOT NULL THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN pe.endterm_draft_status != 1 THEN 1 ELSE 0 END) AS submitted", false);
    $this->db->from('ppa_entries pe');
    // Join with contracts to apply division/funder filters (but no contract status filter)
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where('pe.draft_status !=', 1);
    if ($is_multiple_periods) {
        $this->db->where_in('pe.performance_period', $periods);
    } else {
        $this->db->where('pe.performance_period', $period);
    }
    $this->db->where('pe.endterm_draft_status !=', 1);
    $this->db->where('pe.endterm_updated_at IS NOT NULL', null, false); // Only entries with actual endterm data
    $summary = $this->db->get()->row();

    // Get active staff for "without_ppa" calculation (with contract status filter including Under Renewal)
    $this->db->select('s.staff_id');
    $this->db->from('staff s');
    $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->where_in('sc.status_id', [1, 2, 7]); // Active, Due, and Under Renewal
    $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]); // Exclude Regular, Fixed, AUYVC, ALD
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('s.staff_id', $staff_id);
    $staff_ids = array_column($this->db->get()->result(), 'staff_id');

    // Submission trend for endterm - only entries with actual endterm data (regardless of contract status)
    $this->db->select("DATE(pe.endterm_updated_at) AS date, COUNT(pe.entry_id) AS count");
    $this->db->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where('pe.draft_status !=', 1);
    $this->db->where('pe.endterm_draft_status !=', 1);
    $this->db->where('pe.endterm_updated_at IS NOT NULL', null, false); // Only entries with actual endterm data
    if ($is_multiple_periods) {
        $this->db->where_in('pe.performance_period', $periods);
    } else {
        $this->db->where('pe.performance_period', $period);
    }
    $this->db->group_by("DATE(pe.endterm_updated_at)");
    $this->db->order_by("DATE(pe.endterm_updated_at)", "ASC");
    $trend = array_map(function ($r) {
        return ['date' => $r->date, 'count' => (int)$r->count];
    }, $this->db->get()->result());

    // Average approval time for endterm - only entries with actual endterm data (regardless of contract status)
    $this->db->select("pe.endterm_updated_at AS submitted_date, latest.created_at AS approved_date");
    $this->db->from("ppa_entries pe");
    $this->db->join("(SELECT pat1.* FROM ppa_approval_trail_end_term pat1
        INNER JOIN (SELECT entry_id, MAX(id) AS max_id FROM ppa_approval_trail_end_term WHERE action = 'Approved' GROUP BY entry_id) latest
        ON pat1.id = latest.max_id) latest", "latest.entry_id COLLATE utf8mb4_general_ci = pe.entry_id COLLATE utf8mb4_general_ci", "left", false);
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.endterm_draft_status !=", 1);
    $this->db->where("pe.endterm_updated_at IS NOT NULL", null, false); // Only entries with actual endterm data
    if ($is_multiple_periods) {
        $this->db->where_in('pe.performance_period', $periods);
    } else {
        $this->db->where('pe.performance_period', $period);
    }
    $approvals = $this->db->get()->result();

    $total_days = 0;
    $count = 0;
    foreach ($approvals as $a) {
        if ($a->submitted_date && $a->approved_date) {
            $days = (strtotime($a->approved_date) - strtotime($a->submitted_date)) / 86400;
            $total_days += $days;
            $count++;
        }
    }
    $avg_days = $count ? round($total_days / $count, 1) : 0;

    // Division-wise endterm count - only entries with actual endterm data (regardless of contract status)
    $this->db->select("d.division_name, COUNT(pe.entry_id) AS count");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("divisions d", "d.division_id = sc.division_id", "left");
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.endterm_draft_status !=", 1);
    $this->db->where("pe.endterm_updated_at IS NOT NULL", null, false); // Only entries with actual endterm data
    if ($is_multiple_periods) {
        $this->db->where_in('pe.performance_period', $periods);
    } else {
        $this->db->where('pe.performance_period', $period);
    }
    $this->db->group_by("sc.division_id");
    $divisions = array_map(fn($r) => ['name' => $r->division_name, 'y' => (int)$r->count], $this->db->get()->result());

    // Contract types (based on latest contract only) for endterm - only entries with actual endterm data (regardless of contract status)
    $this->db->select("ct.contract_type, COUNT(pe.entry_id) AS total");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("contract_types ct", "ct.contract_type_id = sc.contract_type_id", "left");
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.endterm_draft_status !=", 1);
    $this->db->where("pe.endterm_updated_at IS NOT NULL", null, false); // Only entries with actual endterm data
    if ($is_multiple_periods) {
        $this->db->where_in('pe.performance_period', $periods);
    } else {
        $this->db->where('pe.performance_period', $period);
    }
    $this->db->group_by("ct.contract_type_id");
    $by_contract = array_map(fn($r) => ['name' => $r->contract_type, 'y' => (int)$r->total], $this->db->get()->result());

    // Staff with endterm - only entries with actual endterm data (regardless of contract status)
    $this->db->select("pe.staff_id")->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    if ($is_multiple_periods) {
        $this->db->where_in('pe.performance_period', $periods);
    } else {
        $this->db->where('pe.performance_period', $period);
    }
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.endterm_draft_status !=", 1);
    $this->db->where("pe.endterm_updated_at IS NOT NULL", null, false); // Only entries with actual endterm data
    $endterm_staff = array_column($this->db->get()->result(), 'staff_id');

    // Staff requiring calibration (overall_end_term_status = 'To be Calibrated') - only entries with actual endterm data (regardless of contract status)
    $this->db->select("pe.staff_id")->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.endterm_draft_status !=", 1);
    $this->db->where("pe.endterm_updated_at IS NOT NULL", null, false); // Only entries with actual endterm data
    if ($is_multiple_periods) {
        $this->db->where_in('pe.performance_period', $periods);
    } else {
        $this->db->where('pe.performance_period', $period);
    }
    $this->db->where("pe.overall_end_term_status", "To be Calibrated");
    $calibration_staff = array_column($this->db->get()->result(), 'staff_id');
    $calibration_staff = array_unique($calibration_staff);

    // Calculate staff without endterms (active staff with PPAs but without endterms)
    // Get staff who have endterm for the period - only entries with actual endterm data
    // First get all staff with endterm (regardless of contract status)
    $this->db->select("pe.staff_id");
    $this->db->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.performance_period", $period);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.endterm_draft_status !=", 1);
    $this->db->where("pe.endterm_updated_at IS NOT NULL", null, false); // Only entries with actual endterm data
    $all_staff_with_endterm = array_column($this->db->get()->result(), 'staff_id');
    // Filter to only active staff for "without" calculation
    $staff_with_endterm = array_intersect($all_staff_with_endterm, $staff_ids);
    
    // Staff without endterm = active staff - staff with endterm (ensure non-negative)
    $staff_without_endterm = array_diff($staff_ids, $staff_with_endterm);
    
    // Periods list (endterm) - only get distinct periods from actual endterm entries
    $this->db->distinct();
    $this->db->select("pe.performance_period");
    $this->db->from("ppa_entries pe");
    $this->db->where("pe.endterm_draft_status !=", 1); // Only non-draft endterms
    $this->db->where("pe.draft_status !=", 1); // Only non-draft PPAs
    if ($is_restricted) $this->db->where("pe.staff_id", $staff_id);
    $this->db->order_by("pe.performance_period", "DESC");
    $periods_result = $this->db->get()->result();
    $periods = array_column($periods_result, 'performance_period');
    $periods = array_unique($periods); // Ensure distinct values
    $current_period = !empty($periods) ? $periods[0] : $period;

    // Age groups for endterm (regardless of contract status)
    $age_groups = [
        'Under 30' => [null, 29],
        '30 - 39' => [30, 39],
        '40 - 49' => [40, 49],
        '50 - 59' => [50, 59],
        '60+' => [60, null],
    ];
    $age_data = [];
    foreach ($age_groups as $label => [$min, $max]) {
        $this->db->from("ppa_entries pe");
        $this->db->join("staff s", "s.staff_id = pe.staff_id", "left");
        $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
        if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
        $this->db->where("pe.performance_period", $period);
        $this->db->where("pe.draft_status !=", 1);
        $this->db->where("pe.endterm_draft_status !=", 1);
        if ($min !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) >=", $min);
        if ($max !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) <=", $max);
        $count = $this->db->count_all_results();
        $age_data[] = ['group' => $label, 'count' => $count];
    }

    // Calculate overall ratings for all endterm entries (regardless of contract status)
    $this->db->select("pe.entry_id, pe.endterm_objectives, pe.midterm_objectives, pe.objectives, d.division_name, f.funder");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("divisions d", "d.division_id = sc.division_id", "left");
    $this->db->join("funders f", "f.funder_id = sc.funder_id", "left");
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($funder_id) $this->db->where('sc.funder_id', $funder_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.endterm_draft_status !=", 1);
    $this->db->where("pe.endterm_updated_at IS NOT NULL", null, false);
    $this->db->where("pe.performance_period", $period);
    $endterm_entries = $this->db->get()->result();

    // Calculate ratings and score bands
    $total_score = 0;
    $score_count = 0;
    $score_bands = [
        'outstanding' => 0,  // 80-100
        'satisfactory' => 0, // 51-79
        'poor' => 0,         // 0-50
        'not_rated' => 0     // Not Rated
    ];
    $division_scores = []; // division_name => [total_score, count]
    $funder_scores = []; // funder_name => [total_score, count]

    foreach ($endterm_entries as $entry) {
        // Get objectives - try endterm first, then midterm, then original PPA
        $objectives = null;
        if (!empty($entry->endterm_objectives)) {
            $objectives = is_string($entry->endterm_objectives) ? json_decode($entry->endterm_objectives, true) : (array)$entry->endterm_objectives;
        } elseif (!empty($entry->midterm_objectives)) {
            $objectives = is_string($entry->midterm_objectives) ? json_decode($entry->midterm_objectives, true) : (array)$entry->midterm_objectives;
        } elseif (!empty($entry->objectives)) {
            $objectives = is_string($entry->objectives) ? json_decode($entry->objectives, true) : (array)$entry->objectives;
        }

        $rating_data = calculate_endterm_overall_rating($objectives);
        $score = $rating_data['score'];
        $category = $rating_data['category'] ?? 'not_rated';

        // Count by score band
        if ($score >= 80) {
            $score_bands['outstanding']++;
        } elseif ($score >= 51) {
            $score_bands['satisfactory']++;
        } elseif ($score > 0) {
            $score_bands['poor']++;
        } else {
            $score_bands['not_rated']++;
        }

        // Calculate average score (only for rated entries)
        if ($score > 0) {
            $total_score += $score;
            $score_count++;
        }

        // Calculate average per division
        $division_name = $entry->division_name ?? 'Unknown';
        if (!isset($division_scores[$division_name])) {
            $division_scores[$division_name] = ['total' => 0, 'count' => 0];
        }
        if ($score > 0) {
            $division_scores[$division_name]['total'] += $score;
            $division_scores[$division_name]['count']++;
        }

        // Calculate average per funder
        $funder_name = $entry->funder ?? 'Unknown';
        if (!isset($funder_scores[$funder_name])) {
            $funder_scores[$funder_name] = ['total' => 0, 'count' => 0];
        }
        if ($score > 0) {
            $funder_scores[$funder_name]['total'] += $score;
            $funder_scores[$funder_name]['count']++;
        }
    }

    $avg_score = $score_count > 0 ? round($total_score / $score_count, 2) : 0;

    // Format division averages
    $division_averages = [];
    foreach ($division_scores as $div_name => $data) {
        if ($data['count'] > 0) {
            $division_averages[] = [
                'name' => $div_name,
                'avg_score' => round($data['total'] / $data['count'], 2)
            ];
        }
    }
    // Sort by average score descending
    usort($division_averages, function($a, $b) {
        return $b['avg_score'] <=> $a['avg_score'];
    });

    // Format funder averages
    $funder_averages = [];
    foreach ($funder_scores as $funder_name => $data) {
        if ($data['count'] > 0) {
            $funder_averages[] = [
                'name' => $funder_name,
                'avg_score' => round($data['total'] / $data['count'], 2)
            ];
        }
    }
    // Sort by average score descending
    usort($funder_averages, function($a, $b) {
        return $b['avg_score'] <=> $a['avg_score'];
    });

    return [
        'total' => (int)($summary->total ?? 0),
        'approved' => (int)($summary->approved ?? 0),
        'submitted' => (int)($summary->submitted ?? 0),
        'trend' => $trend,
        'avg_approval_days' => $avg_days,
        'by_division' => $divisions,
        'by_contract' => $by_contract,
        'by_age' => $age_data,
        'avg_score' => $avg_score,
        'division_averages' => $division_averages,
        'funder_averages' => $funder_averages,
        'score_bands' => $score_bands,
        'staff_count' => count($staff_ids),
        'staff_without_endterms' => max(0, count($staff_without_endterm)),
        'staff_require_calibration' => count($calibration_staff),
        'periods' => $periods,
        'current_period' => $current_period,
    ];
}





public function get_supervisors_with_pending_endterms($period)
{
    $sql = "
        SELECT 
            DISTINCT s.staff_id AS supervisor_id,
            s.title,
            s.fname,
            s.lname,
            s.work_email
        FROM staff s
        JOIN ppa_entries p 
            ON s.staff_id = p.endterm_supervisor_1 OR s.staff_id = p.endterm_supervisor_2
        WHERE p.performance_period = ?
        AND p.endterm_draft_status = 0
        AND p.endterm_sign_off = 1
        AND p.entry_id NOT IN (
            SELECT entry_id FROM ppa_approval_trail_end_term WHERE action = 'Approved'
        )
        ORDER BY s.fname ASC
    ";

    return $this->db->query($sql, [$period])->result();
}

public function get_pending_by_supervisor_with_staff($supervisor_id)
{
    // Subquery: entries that have been approved in the endterm approval trail
    $subquery = $this->db->select('entry_id')
        ->from('ppa_approval_trail_end_term')
        ->where('action', 'Approved')
        ->get_compiled_select();

    $this->db->select("
        p.entry_id,
        p.staff_id,
        CONCAT(s.title, ' ', s.fname, ' ', s.lname) AS staff_name,
        p.performance_period,
        p.endterm_updated_at AS created_at
    ");
    $this->db->from('ppa_entries p');
    $this->db->join('staff s', 's.staff_id = p.staff_id', 'left');
    $this->db->group_start()
             ->where('p.endterm_supervisor_1', $supervisor_id)
             ->or_where('p.endterm_supervisor_2', $supervisor_id)
             ->group_end();
    $this->db->where('p.endterm_draft_status', 0);
    $this->db->where('p.endterm_sign_off', 1);
    $this->db->where("p.entry_id NOT IN ($subquery)", null, false);
    $this->db->order_by("p.endterm_updated_at", "DESC");

    return $this->db->get()->result();
}

public function get_all_ppas_filtered($filters, $limit = 40, $offset = 0)
{
    // Default to current performance period
    if (empty($filters['period'])) {
        $filters['period'] = str_replace(' ', '-', current_period());
    }

    $sql = "
        SELECT 
            p.*, 
            d.division_name,
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            CASE 
                WHEN p.endterm_draft_status = 1 THEN 'Draft'
                ELSE (
                    CASE
                        WHEN p.endterm_supervisor_2 IS NULL AND (
                            SELECT action FROM ppa_approval_trail_end_term 
                            WHERE entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND staff_id = p.endterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Approved'

                        WHEN p.endterm_supervisor_2 IS NOT NULL AND (
                            SELECT action FROM ppa_approval_trail_end_term 
                            WHERE entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND staff_id = p.endterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved' AND (
                            SELECT action FROM ppa_approval_trail_end_term 
                            WHERE entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND staff_id = p.endterm_supervisor_2 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Approved'

                        WHEN (
                            SELECT action FROM ppa_approval_trail_end_term 
                            WHERE entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND staff_id = p.endterm_supervisor_2 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Returned'
                        THEN 'Returned'

                        WHEN (
                            SELECT action FROM ppa_approval_trail_end_term 
                            WHERE entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND staff_id = p.endterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Returned'
                        THEN 'Returned'

                        WHEN p.endterm_supervisor_2 IS NOT NULL AND (
                            SELECT action FROM ppa_approval_trail_end_term 
                            WHERE entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci AND staff_id = p.endterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Pending Second Supervisor'

                        ELSE 'Pending First Supervisor'
                    END
                )
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id

        LEFT JOIN (
            SELECT sc.*
            FROM staff_contracts sc
            INNER JOIN (
                SELECT staff_id, MAX(staff_contract_id) AS max_id
                FROM staff_contracts
                GROUP BY staff_id
            ) latest ON latest.staff_id = sc.staff_id AND latest.max_id = sc.staff_contract_id
        ) sc ON sc.staff_id = p.staff_id

        LEFT JOIN divisions d ON d.division_id = sc.division_id

        WHERE p.performance_period = ? 

    ";

    $params = [$filters['period']];

    // Filters
    if (!empty($filters['staff_name'])) {
        $sql .= " AND (s.fname LIKE ? OR s.lname LIKE ?)";
        $params[] = '%' . $filters['staff_name'] . '%';
        $params[] = '%' . $filters['staff_name'] . '%';
    }

    if ($filters['draft_status'] !== '' && $filters['draft_status'] !== null) {
        $sql .= " AND p.endterm_draft_status = ?";
        $params[] = $filters['draft_status'];
    }

    if (!empty($filters['created_at'])) {
        $sql .= " AND DATE(p.endterm_created_at) = ?";
        $params[] = $filters['created_at'];
    }

    if (!empty($filters['division_id'])) {
        $sql .= " AND sc.division_id = ?";
        $params[] = $filters['division_id'];
    }

    $sql .= " ORDER BY p.endterm_created_at DESC";

    if ($limit > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = (int) $limit;
        $params[] = (int) $offset;
    }

    $query = $this->db->query($sql, $params);
    // Debug output
// log_message('debug', "SQL: " . $this->db->last_query());
// log_message('debug', "Results: " . json_encode($query->result_array()));

return $query->result_array();
}



public function count_ppas_filtered($filters)
{
    return count($this->get_all_ppas_filtered($filters, 0, 0));
}

/**
 * Returns staff who have submitted a PPA for the given period but have NOT submitted a endterm.
 * - Only considers staff with active contracts (status_id 1 or 2, not in excluded contract types).
 * - Only considers PPA entries that are not drafts (draft_status != 1).
 * - Returns staff details for those missing a endterm (endterm_draft_status is NULL or 1).
 */
public function get_staff_without_endterm($period = null, $division_id = null)
{
    // STEP 1: Get latest contract for each staff
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    $this->db->select('
        s.staff_id, s.title, s.fname, s.lname, s.oname, s.work_email, s.SAPNO,
        d.division_name, ct.contract_type, st.status
    ');
    $this->db->from('staff s');
    $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
    $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
    $this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
    $this->db->join('status st', 'st.status_id = sc.status_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->where_in('sc.status_id', [1, 2]); // Active or Due
    $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]); // Exclude Regular, Fixed, AUYVC, ALD (same as PPA dashboard)
    $this->db->where("TRIM(s.work_email) !=", '');
    $this->db->where("TRIM(s.work_email) !=", 'xx%');

    if ($division_id) {
        $this->db->where('sc.division_id', $division_id);
    }

    $active_staff = $this->db->get()->result();

    if (empty($active_staff)) return [];

    // STEP 2: Extract staff_ids from active staff
    $staff_ids = array_map('intval', array_column($active_staff, 'staff_id'));
    if (empty($staff_ids)) return [];

    // STEP 3: Get staff who have submitted a PPA (not draft) for the period
    $this->db->select('staff_id, entry_id');
    $this->db->from('ppa_entries');
    $this->db->where_in('staff_id', $staff_ids);
    $this->db->where('draft_status !=', 1); // exclude drafts
    if ($period) {
        $this->db->where('performance_period', $period);
    }
    $ppa_entries = $this->db->get()->result_array();

    // Map: staff_id => entry_id
    $ppa_staff_map = [];
    foreach ($ppa_entries as $row) {
        $ppa_staff_map[(int)$row['staff_id']] = $row['entry_id'];
    }

    if (empty($ppa_staff_map)) return [];

    // STEP 4: Get staff who have submitted a endterm (endterm_draft_status != 1) for the period
    $this->db->select('staff_id');
    $this->db->from('ppa_entries');
    $this->db->where_in('staff_id', array_keys($ppa_staff_map));
    if ($period) {
        $this->db->where('performance_period', $period);
    }
    $this->db->where('endterm_draft_status !=', 1); // Submitted (not draft)
    $endterm_submitted = $this->db->get()->result_array();
    $endterm_submitted_ids = array_map(fn($r) => (int)$r['staff_id'], $endterm_submitted);

    // STEP 5: Filter staff who have PPA but missing endterm (endterm_draft_status is NULL or 1)
    $missing_endterm_ids = array_diff(array_keys($ppa_staff_map), $endterm_submitted_ids);

    // Return staff details for those missing endterms
    return array_values(array_filter($active_staff, function ($staff) use ($missing_endterm_ids) {
        return in_array((int)$staff->staff_id, $missing_endterm_ids, true);
    }));
}

public function ppa_exists($entry_id){

}

public function get_recent_endterm_for_user($entry_id, $period)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,
            (
                SELECT a.action 
                FROM ppa_approval_trail_end_term a
                WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                ORDER BY a.id DESC LIMIT 1
            ) AS last_action,
            (
                CASE
                    WHEN p.endterm_draft_status = 1 THEN 'Draft'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_end_term a
                        WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Approved' 
                    -- Check if there's a 'Returned' action after the approval
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM ppa_approval_trail_end_term a_returned 
                        WHERE a_returned.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                        AND a_returned.action = 'Returned'
                        AND a_returned.id > (
                            SELECT MAX(a_approved.id) 
                            FROM ppa_approval_trail_end_term a_approved 
                            WHERE a_approved.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a_approved.action = 'Approved'
                        )
                    )
                    THEN 'Approved'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_end_term a
                        WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Returned' THEN 'Returned'
                    WHEN p.endterm_supervisor_2 IS NOT NULL AND
                        -- First supervisor has approved (most recent action is 'Approved')
                        COALESCE((
                            SELECT a1.action 
                            FROM ppa_approval_trail_end_term a1 
                            WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a1.staff_id = p.endterm_supervisor_1 
                            ORDER BY a1.id DESC LIMIT 1
                        ), '') = 'Approved'
                        -- AND there's no 'Returned' action after the approval
                        AND NOT EXISTS (
                            SELECT 1 
                            FROM ppa_approval_trail_end_term a_returned 
                            WHERE a_returned.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a_returned.action = 'Returned'
                            AND a_returned.id > (
                                SELECT MAX(a_approved.id) 
                                FROM ppa_approval_trail_end_term a_approved 
                                WHERE a_approved.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                                AND a_approved.staff_id = p.endterm_supervisor_1 
                                AND a_approved.action = 'Approved'
                            )
                        )
                        -- AND staff has consented
                        AND p.endterm_staff_consent_at IS NOT NULL
                    THEN 'Pending Second Supervisor'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_end_term a
                        WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Submitted' THEN 'Pending'
                    ELSE 'Pending First Supervisor'
                END
            ) AS endterm_status
        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.entry_id = ? AND p.performance_period = ?
        ORDER BY p.endterm_created_at DESC
        LIMIT 1
    ";
    return $this->db->query($sql, [$entry_id, $period])->row_array();
}

public function get_all_approved_endterms_for_user($staff_id)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,
            (
                SELECT a.action 
                FROM ppa_approval_trail_end_term a
                WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                ORDER BY a.id DESC LIMIT 1
            ) AS last_action,
            (
                CASE
                    WHEN p.endterm_draft_status = 1 THEN 'Draft'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_end_term a
                        WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Approved' THEN 'Approved'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_end_term a
                        WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Returned' THEN 'Returned'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_end_term a
                        WHERE a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Submitted' THEN 'Pending Supervisor'
                    ELSE 'Pending'
                END
            ) AS endterm_status
        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.staff_id = ?
        AND (
            p.endterm_created_at IS NOT NULL 
            OR p.endterm_updated_at IS NOT NULL 
            OR p.endterm_objectives IS NOT NULL
            OR p.endterm_draft_status = 1
        )
        ORDER BY COALESCE(p.endterm_created_at, p.endterm_updated_at, p.updated_at) DESC
    ";
    return $this->db->query($sql, [$staff_id])->result_array();
}

public function get_endterms_approved_by_supervisor($supervisor_id)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,
            a.created_at AS approval_date,
            a.comments,
            a.staff_id AS approver_id,
            (
                CASE
                    WHEN p.endterm_draft_status = 1 THEN 'Draft'
                    WHEN (
                        SELECT a2.action 
                        FROM ppa_approval_trail_end_term a2
                        WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Approved' THEN 'Approved'
                    WHEN (
                        SELECT a2.action 
                        FROM ppa_approval_trail_end_term a2
                        WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Returned' THEN 'Returned'
                    WHEN (
                        SELECT a2.action 
                        FROM ppa_approval_trail_end_term a2
                        WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Submitted' THEN 'Pending Supervisor'
                    ELSE 'Pending'
                END
            ) AS endterm_status
        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        JOIN ppa_approval_trail_end_term a ON a.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci
        WHERE a.action = 'Approved'
          AND a.staff_id = ?
        ORDER BY a.created_at DESC
    ";
    return $this->db->query($sql, [$supervisor_id])->result_array();
}






}
