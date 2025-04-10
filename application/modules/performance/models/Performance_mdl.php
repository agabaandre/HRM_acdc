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
    $this->db->where_in('sc.status_id', [1, 2]); // Active & Due
    $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]); // Exclude Regular, Fixed, AUYVC, ALD
    if ($division_id) {
        $this->db->where('sc.division_id', $division_id);
    }

    $active_staff = $this->db->get()->result();
    $staff_ids = array_column($active_staff, 'staff_id');

    if (empty($staff_ids)) return [];

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
            $this->db->select('staff_id, entry_id, required_skills');
            $this->db->from('ppa_entries');
            $this->db->where('training_recommended', 'Yes');
            if ($period) $this->db->where('performance_period', $period);
            $this->db->where_in('staff_id', $staff_ids);
            $pdp_entries = $this->db->get()->result();

            // Map skills
            $this->db->select('id, skill');
            $skills_map = [];
            foreach ($this->db->get('training_skills')->result() as $s) {
                $skills_map[$s->id] = $s->skill;
            }

            $pdp_map = [];
            foreach ($pdp_entries as $entry) {
                $skill_ids = json_decode($entry->required_skills ?? '[]', true);
                $skill_names = array_map(fn($id) => $skills_map[$id] ?? '', $skill_ids);
                $pdp_map[$entry->staff_id] = [
                    'entry_id' => $entry->entry_id,
                    'skills' => array_filter($skill_names)
                ];
            }

            return array_filter(array_map(function ($staff) use ($pdp_map) {
                if (isset($pdp_map[$staff->staff_id])) {
                    $staff->entry_id = $pdp_map[$staff->staff_id]['entry_id'];
                    $staff->training_skills = $pdp_map[$staff->staff_id]['skills'];
                    return $staff;
                }
                return null;
            }, $active_staff));

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


public function get_dashboard_data()
{
    $division_id = $this->input->get('division_id');
    $period = $this->input->get('period');
    $current_period = str_replace(' ', '-', current_period());
    $period = !empty($period) ? $period : $current_period;

    $user = $this->session->userdata('user');
    $is_restricted = ($user && isset($user->role) && $user->role == 17);
    $staff_id = $is_restricted ? $user->staff_id : null;

    // Get active staff
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    $this->db->select('s.staff_id');
    $this->db->from('staff s');
    $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->where_in('sc.status_id', [1, 2]);
    $this->db->where_not_in('sc.contract_type_id', [1, 3, 5, 7]);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('s.staff_id', $staff_id);
    $staff_ids = array_column($this->db->get()->result(), 'staff_id');
    if (empty($staff_ids)) return [];

    // Summary counts
    $this->db->select("
        COUNT(pe.entry_id) AS total,
        SUM(CASE WHEN latest.action = 'Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN latest.action = 'Submitted' THEN 1 ELSE 0 END) AS submitted", false);
    $this->db->from('ppa_entries pe');
    $this->db->join("(SELECT pat1.* FROM ppa_approval_trail pat1
        INNER JOIN (SELECT entry_id, MAX(id) AS max_id FROM ppa_approval_trail GROUP BY entry_id) latest
        ON pat1.id = latest.max_id) latest", 'latest.entry_id = pe.entry_id', 'left');
    $this->db->where_in('pe.staff_id', $staff_ids);
    $this->db->where('pe.performance_period', $period);
    $summary = $this->db->get()->row();

    // Submission trend
    $this->db->select("DATE(created_at) AS date, COUNT(entry_id) AS count");
    $this->db->from("ppa_entries");
    $this->db->where_in("staff_id", $staff_ids);
    $this->db->where("performance_period", $period);
    $this->db->group_by("DATE(created_at)");
    $this->db->order_by("DATE(created_at)", "ASC");
    $trend = array_map(function ($r) {
        return ['date' => $r->date, 'count' => (int)$r->count];
    }, $this->db->get()->result());

    // Average approval time
    $this->db->select("pe.created_at AS submitted_date, latest.created_at AS approved_date");
    $this->db->from("ppa_entries pe");
    $this->db->join("(SELECT pat1.* FROM ppa_approval_trail pat1
        INNER JOIN (SELECT entry_id, MAX(id) AS max_id FROM ppa_approval_trail WHERE action = 'Approved' GROUP BY entry_id) latest
        ON pat1.id = latest.max_id) latest", "latest.entry_id = pe.entry_id", "left");
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.performance_period", $period);
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

    // Division-wise PPA count
    $this->db->select("d.division_name, COUNT(pe.entry_id) AS count");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("divisions d", "d.division_id = sc.division_id", "left");
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("sc.division_id");
    $divisions = array_map(fn($r) => ['name' => $r->division_name, 'y' => (int)$r->count], $this->db->get()->result());

    // Contract types (based on latest contract only)
    $this->db->select("ct.contract_type, COUNT(pe.entry_id) AS total");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("contract_types ct", "ct.contract_type_id = sc.contract_type_id", "left");
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ct.contract_type_id");
    $by_contract = array_map(fn($r) => ['name' => $r->contract_type, 'y' => (int)$r->total], $this->db->get()->result());

    // Staff with PPA
    $this->db->select("staff_id")->from("ppa_entries");
    $this->db->where_in("staff_id", $staff_ids);
    $this->db->where("performance_period", $period);
    $ppa_staff = array_column($this->db->get()->result(), 'staff_id');

    // Staff with PDP
    $this->db->select("staff_id")->from("ppa_entries");
    $this->db->where_in("staff_id", $staff_ids);
    $this->db->where("training_recommended", "Yes");
    $this->db->where("performance_period", $period);
    $pdp_staff = array_column($this->db->get()->result(), 'staff_id');

    // Periods list
    $this->db->distinct()->select("performance_period")->from("ppa_entries");
    if ($is_restricted) $this->db->where("staff_id", $staff_id);
    $this->db->order_by("created_at", "DESC");
    $periods = array_column($this->db->get()->result(), 'performance_period');
    $current_period = $periods[0] ?? $period;

    // Age groups
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
        $this->db->where_in("pe.staff_id", $staff_ids);
        $this->db->where("pe.performance_period", $period);
        if ($min !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) >=", $min);
        if ($max !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) <=", $max);
        $count = $this->db->count_all_results();
        $age_data[] = ['group' => $label, 'count' => $count];
    }

    // Training categories
    $this->db->select("tc.category_name AS name, COUNT(*) AS y", false);
    $this->db->from("ppa_entries pe");
    $this->db->join("training_skills ts", "JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')", "inner", false);
    $this->db->join("training_categories tc", "tc.id = ts.category_id", "left");
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ts.category_id");
    $training_categories = $this->db->get()->result();

    // Top 10 training skills
    $this->db->select("ts.skill AS name, COUNT(*) AS y", false);
    $this->db->from("ppa_entries pe");
    $this->db->join("training_skills ts", "JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')", "inner", false);
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ts.id");
    $this->db->order_by("y DESC");
    $this->db->limit(10);
    $training_skills = $this->db->get()->result();

    return [
        'total' => (int)$summary->total,
        'approved' => (int)$summary->approved,
        'submitted' => (int)$summary->submitted,
        'trend' => $trend,
        'avg_approval_days' => $avg_days,
        'by_division' => $divisions,
        'by_contract' => $by_contract,
        'by_age' => $age_data,
        'training_categories' => $training_categories,
        'training_skills' => $training_skills,
        'staff_count' => count($staff_ids),
        'staff_without_ppas' => count($staff_ids) - count($ppa_staff),
        'staff_with_pdps' => count($pdp_staff),
        'periods' => $periods,
        'current_period' => $current_period,
    ];
}





}
