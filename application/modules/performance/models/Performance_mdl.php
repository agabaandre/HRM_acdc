<?php

class Performance_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
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

    public function get_plan_by_entry_id($entry_id)
    {
        $query = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id]);
        $result = $query->row();
    
        if ($result) {
            // Decode only if the field is not null
            $result->objectives = $result->objectives ? json_decode($result->objectives) : [];
            $result->required_skills = $result->required_skills ? json_decode($result->required_skills) : [];
        }
    
        return $result;
    }
    
public function get_approval_trail($entry_id)
{
    if (!$entry_id) return [];
    // genrate cuurent employees trails.
    $this->db->where("entry_id","$entry_id");
    return $this->db->get('ppa_approval_trail')->result();

}

public function get_pending_ppa($staff_id)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Supervisor 1 last action
            (
                SELECT a1.action 
                FROM ppa_approval_trail a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,

            -- Supervisor 2 last action
            (
                SELECT a2.action 
                FROM ppa_approval_trail a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,

            -- Compute overall status
            CASE 
                WHEN p.draft_status = 1 THEN 'Pending (Draft)'
                WHEN p.supervisor2_id IS NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Approved'

                WHEN p.supervisor2_id IS NOT NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved' AND
                    (
                        SELECT a2.action 
                        FROM ppa_approval_trail a2 
                        WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id 
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Approved'

                WHEN (
                    SELECT a2.action 
                    FROM ppa_approval_trail a2 
                    WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id 
                    ORDER BY a2.id DESC LIMIT 1
                ) = 'Returned'
                THEN 'Returned'

                WHEN (
                    SELECT a1.action 
                    FROM ppa_approval_trail a1 
                    WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                    ORDER BY a1.id DESC LIMIT 1
                ) = 'Returned'
                THEN 'Returned'

                WHEN p.supervisor2_id IS NOT NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Pending Second Supervisor'

                ELSE 'Pending First Supervisor'
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.draft_status = 0
        AND (
            -- First supervisor
            (
                p.supervisor_id = ? AND (
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                        ORDER BY a1.id DESC LIMIT 1
                    ) IS NULL OR
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                        ORDER BY a1.id DESC LIMIT 1
                    ) != 'Approved'
                )
            )
            OR
            -- Second supervisor (after first has approved)
            (
                p.supervisor2_id = ? AND (
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                    AND (
                        (
                            SELECT a2.action
                            FROM ppa_approval_trail a2
                            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                            ORDER BY a2.id DESC LIMIT 1
                        ) IS NULL OR
                        (
                            SELECT a2.action
                            FROM ppa_approval_trail a2
                            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                            ORDER BY a2.id DESC LIMIT 1
                        ) != 'Approved'
                    )
                )
            )
        )
        ORDER BY p.created_at DESC
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
        
        -- Last action by Supervisor 1
        (
            SELECT a1.action 
            FROM ppa_approval_trail a1
            WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
            ORDER BY a1.id DESC LIMIT 1
        ) AS supervisor1_action,
        
        -- Last action by Supervisor 2
        (
            SELECT a2.action 
            FROM ppa_approval_trail a2
            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
            ORDER BY a2.id DESC LIMIT 1
        ) AS supervisor2_action

    FROM ppa_entries p
    JOIN staff s ON s.staff_id = p.staff_id
    WHERE p.staff_id = ?

    AND (
        -- Case 1: Only one supervisor and they approved
        (p.supervisor2_id IS NULL AND
         (
            SELECT a1.action 
            FROM ppa_approval_trail a1
            WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
            ORDER BY a1.id DESC LIMIT 1
         ) = 'Approved')

        -- Case 2: Both supervisors and both approved
        OR (
            p.supervisor2_id IS NOT NULL AND
            (
                SELECT a1.action 
                FROM ppa_approval_trail a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                ORDER BY a1.id DESC LIMIT 1
            ) = 'Approved' AND
            (
                SELECT a2.action 
                FROM ppa_approval_trail a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                ORDER BY a2.id DESC LIMIT 1
            ) = 'Approved'
        )
    )

    ORDER BY p.created_at DESC
    ";

    return $this->db->query($sql, [$staff_id])->result_array();
}

public function get_recent_ppas_for_user($staff_id, $period)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Last action by Supervisor 1
            (
                SELECT a1.action 
                FROM ppa_approval_trail a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,
            
            -- Last action by Supervisor 2
            (
                SELECT a2.action 
                FROM ppa_approval_trail a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,
            
            -- Final status decision with draft consideration
            CASE 
                WHEN p.draft_status = 1 THEN 'Pending (Draft)'
                ELSE (
                    CASE
                        WHEN p.supervisor2_id IS NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN p.supervisor2_id IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved' AND
                            (
                                SELECT a2.action 
                                FROM ppa_approval_trail a2
                                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN (
                                SELECT a2.action 
                                FROM ppa_approval_trail a2
                                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN p.supervisor2_id IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
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
        ORDER BY p.created_at DESC
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
            p.created_at,
            CONCAT(st.fname, ' ', st.lname) AS staff_name,
            a.created_at AS approval_date,
            a.comments

        FROM ppa_entries p
        JOIN staff st ON st.staff_id = p.staff_id
        JOIN ppa_approval_trail a ON a.id = (
            SELECT MAX(id) 
            FROM ppa_approval_trail 
            WHERE entry_id = p.entry_id
            AND action = 'Approved'
        )
        WHERE 
            (p.supervisor_id = ? OR p.supervisor2_id = ?)
        ORDER BY a.created_at DESC
    ";

    return $this->db->query($sql, [$supervisor_id, $supervisor_id])->result_array();
}

public function get_staff_by_type($type, $division_id = null, $period = null)
{
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    // Base staff query
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
    $this->db->where_in('sc.status_id', [1, 2]);
    $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]);
    if ($division_id) {
        $this->db->where('sc.division_id', $division_id);
    }

    $active_staff = $this->db->get()->result();
    $staff_ids = array_column($active_staff, 'staff_id');

    if (empty($staff_ids)) return [];

    // Map staff entry_id if needed
    switch ($type) {
        case 'total':
            $this->db->select('staff_id, entry_id');
            $this->db->from('ppa_entries');
            if ($period) $this->db->where('performance_period', $period);
            $this->db->where_in('staff_id', $staff_ids);
            $ppa_entries = $this->db->get()->result();
            $ppa_map = [];
            foreach ($ppa_entries as $row) {
                $ppa_map[$row->staff_id] = $row->entry_id;
            }
            return array_filter(array_map(function ($staff) use ($ppa_map) {
                if (isset($ppa_map[$staff->staff_id])) {
                    $staff->entry_id = $ppa_map[$staff->staff_id];
                    return $staff;
                }
                return null;
            }, $active_staff));

        case 'approved':
            $this->db->select('pe.staff_id, pe.entry_id');
            $this->db->from('ppa_entries pe');
            $this->db->join("(
                SELECT entry_id, MAX(id) AS max_id
                FROM ppa_approval_trail
                GROUP BY entry_id
            ) latest", "latest.entry_id = pe.entry_id");
            $this->db->join("ppa_approval_trail pat", "pat.id = latest.max_id");
            $this->db->where('pat.action', 'Approved');
            if ($period) $this->db->where('pe.performance_period', $period);
            $this->db->where_in('pe.staff_id', $staff_ids);
            $approved_entries = $this->db->get()->result();
            $approved_map = [];
            foreach ($approved_entries as $row) {
                $approved_map[$row->staff_id] = $row->entry_id;
            }
            return array_filter(array_map(function ($staff) use ($approved_map) {
                if (isset($approved_map[$staff->staff_id])) {
                    $staff->entry_id = $approved_map[$staff->staff_id];
                    return $staff;
                }
                return null;
            }, $active_staff));

        case 'with_pdp':
            $this->db->select('staff_id');
            $this->db->from('ppa_entries');
            $this->db->where('training_recommended', 'Yes');
            if ($period) $this->db->where('performance_period', $period);
            $this->db->where_in('staff_id', $staff_ids);
            $pdp_ids = array_column($this->db->get()->result(), 'staff_id');
            return array_filter($active_staff, fn($s) => in_array($s->staff_id, $pdp_ids));

        case 'without_ppa':
            $this->db->select('staff_id');
            $this->db->from('ppa_entries');
            if ($period) $this->db->where('performance_period', $period);
            $this->db->where_in('staff_id', $staff_ids);
            $ppa_ids = array_column($this->db->get()->result(), 'staff_id');
            return array_filter($active_staff, fn($s) => !in_array($s->staff_id, $ppa_ids));

        default:
            return [];
    }
}





	

}
