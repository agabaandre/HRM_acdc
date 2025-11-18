<?php
defined('BASEPATH') or exit('No direct script access allowed');
#[AllowDynamicProperties]
class Auth_mdl extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->table = "user";
		$this->default_password = $this->argonhash->make(setting()->default_password);
		
		// Optional: Temporarily disable ONLY_FULL_GROUP_BY mode if needed
		// Uncomment the line below if you want to disable it temporarily
		// $this->db->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
	}
	public function login($postdata)
	{
	            $email = $this->security->xss_clean(trim($postdata['email']));
				$this->db->select('*'); // Select columns from the staff table
				$this->db->from('staff'); // Set the main table
				$this->db->join('user', 'user.auth_staff_id = staff.staff_id'); // Join with the users table
				$this->db->join('user_groups', 'user_groups.id = user.role'); // Join with the user_groups table
				$this->db->where('user.status', 1);
				$this->db->where(trim('staff.work_email'),$email); // Add condition for active users

				// Execute the query

				$qry = $this->db->get();

				// Check if any rows are returned
				if ($qry->num_rows() > 0) {
					// Fetch the first row as an object
					$person = $qry->row();
					//dd($person);
					return $person;
				} else {
					// Return false or an empty result if no rows are found
					return false;
				}
	}

	public function getAll($start, $limit, $key)
	{
		// Use explicit column selection to avoid GROUP BY issues
		$this->db->select('
			staff.staff_id, staff.fname, staff.lname, staff.title, staff.oname, staff.work_email, 
			staff.tel_1, staff.tel_2, staff.private_email, staff.whatsapp, staff.photo, staff.signature,
			staff.date_of_birth, staff.SAPNO,
			user.user_id, user.auth_staff_id, user.name, user.role, user.status, user.created_at,
			user_groups.id as group_id, user_groups.group_name
		');
		$this->db->from('staff'); // Set the main table
	
		// Add search conditions
		if (!empty($key)) {
			$this->db->group_start(); // Start a group for OR conditions
			$this->db->like("staff.work_email", "$key", "both");
			$this->db->or_like("staff.fname", "$key", "both");
			$this->db->or_like("staff.lname", "$key", "both");
			$this->db->group_end(); // End the group
		}
	
		// Join the staff table with user table
		$this->db->join('user', 'user.auth_staff_id = staff.staff_id');
	
		// Join the user_groups table
		$this->db->join('user_groups', 'user_groups.id = user.role');
		
		// Add limit and offset
		$this->db->limit( $start,$limit);
	
		// Execute the query
		$qry = $this->db->get();
		return $qry->result();
	}
	
	public function getAllFiltered($filters = [], $limit = false, $start = false)
	{
		// Use a subquery approach to avoid GROUP BY issues with ONLY_FULL_GROUP_BY mode
		$this->db->select('
			staff.staff_id, staff.fname, staff.lname, staff.title, staff.oname, staff.work_email, 
			staff.tel_1, staff.tel_2, staff.private_email, staff.whatsapp, staff.photo, staff.signature,
			staff.date_of_birth, staff.SAPNO, 
			user.user_id, user.auth_staff_id, user.name, user.role, user.status, user.created_at,
			user_groups.id as group_id, user_groups.group_name,
			divisions.division_name, jobs.job_name
		');
		$this->db->from('staff');
	
		// Add search conditions
		if (!empty($filters['search'])) {
			$this->db->group_start(); // Start a group for OR conditions
			$this->db->like("staff.work_email", $filters['search'], "both");
			$this->db->or_like("staff.fname", $filters['search'], "both");
			$this->db->or_like("staff.lname", $filters['search'], "both");
			$this->db->or_like("staff.staff_id", $filters['search'], "both");
			$this->db->group_end(); // End the group
		}
		
		// Filter by group
		if (!empty($filters['group_id'])) {
			$this->db->where('user.role', $filters['group_id']);
		}
		
		// Filter by status
		if (isset($filters['status']) && $filters['status'] !== '') {
			log_message('debug', 'Applying status filter: ' . $filters['status']);
			$this->db->where('user.status', $filters['status']);
		}
	
		// Join the staff table with user table
		$this->db->join('user', 'user.auth_staff_id = staff.staff_id');
	
		// Join the user_groups table
		$this->db->join('user_groups', 'user_groups.id = user.role');
		
		// Use a subquery to get the latest contract for each staff member
		$latest_contract_subquery = "
			SELECT sc1.staff_id, sc1.division_id, sc1.job_id
			FROM staff_contracts sc1
			INNER JOIN (
				SELECT staff_id, MAX(staff_contract_id) as max_contract_id
				FROM staff_contracts 
				WHERE status_id IN (1,2,7)
				GROUP BY staff_id
			) sc2 ON sc1.staff_contract_id = sc2.max_contract_id
		";
		
		// Join with the latest contract subquery
		$this->db->join("($latest_contract_subquery) as latest_contract", 'latest_contract.staff_id = staff.staff_id', 'left');
		
		// Join divisions table through latest contract
		$this->db->join('divisions', 'divisions.division_id = latest_contract.division_id', 'left');
		
		// Join jobs table through latest contract
		$this->db->join('jobs', 'jobs.job_id = latest_contract.job_id', 'left');
		
		// Order by name
		$this->db->order_by('staff.fname', 'ASC');
		$this->db->order_by('staff.lname', 'ASC');
		
		// Apply pagination if provided
		if ($limit !== false) {
			$this->db->limit($limit, $start);
		}
	
		// Execute the query
		$qry = $this->db->get();
		
		// Debug: Log the SQL query
		log_message('debug', 'SQL Query: ' . $this->db->last_query());
		
		$result = $qry->result();
		
		// Debug: Check for duplicates
		$userIds = array();
		foreach ($result as $user) {
			$userIds[] = $user->user_id;
		}
		$uniqueIds = array_unique($userIds);
		if (count($userIds) !== count($uniqueIds)) {
			log_message('warning', 'Duplicate users detected in getAllFiltered: ' . count($userIds) . ' total, ' . count($uniqueIds) . ' unique');
		}
		
		return $result;
	}
	public function count_Users($key)
{
    $this->db->select('COUNT(DISTINCT user.user_id) as total');
    $this->db->from('staff'); // Set the main table

    // Add search conditions
    if (!empty($key)) {
        $this->db->group_start(); // Start a group for OR conditions
        $this->db->like("staff.work_email", "$key", "both");
        $this->db->or_like("staff.fname", "$key", "both");
        $this->db->or_like("staff.lname", "$key", "both");
        $this->db->group_end(); // End the group
    }

    // Join the staff table with user table
	$this->db->join('user', 'user.auth_staff_id = staff.staff_id');

    // Execute the query
    $qry = $this->db->get();
    $result = $qry->row();
    return $result ? $result->total : 0;
}

	public function countFilteredUsers($filters = [])
	{
		$this->db->select('COUNT(DISTINCT user.user_id) as total');
		$this->db->from('staff'); // Set the main table
	
		// Add search conditions
		if (!empty($filters['search'])) {
			$this->db->group_start(); // Start a group for OR conditions
			$this->db->like("staff.work_email", $filters['search'], "both");
			$this->db->or_like("staff.fname", $filters['search'], "both");
			$this->db->or_like("staff.lname", $filters['search'], "both");
			$this->db->or_like("staff.staff_id", $filters['search'], "both");
			$this->db->group_end(); // End the group
		}
		
		// Filter by group
		if (!empty($filters['group_id'])) {
			$this->db->where('user.role', $filters['group_id']);
		}
		
		// Filter by status
		if (isset($filters['status']) && $filters['status'] !== '') {
			log_message('debug', 'Applying status filter in countFilteredUsers: ' . $filters['status']);
			$this->db->where('user.status', $filters['status']);
		}
	
		// Join the staff table with user table
		$this->db->join('user', 'user.auth_staff_id = staff.staff_id');
	
		// Join the user_groups table
		$this->db->join('user_groups', 'user_groups.id = user.role');
		
		// Use a subquery to get the latest contract for each staff member
		$latest_contract_subquery = "
			SELECT sc1.staff_id, sc1.division_id, sc1.job_id
			FROM staff_contracts sc1
			INNER JOIN (
				SELECT staff_id, MAX(staff_contract_id) as max_contract_id
				FROM staff_contracts 
				WHERE status_id IN (1,2,7)
				GROUP BY staff_id
			) sc2 ON sc1.staff_contract_id = sc2.max_contract_id
		";
		
		// Join with the latest contract subquery
		$this->db->join("($latest_contract_subquery) as latest_contract", 'latest_contract.staff_id = staff.staff_id', 'left');
		
		// Join divisions table through latest contract
		$this->db->join('divisions', 'divisions.division_id = latest_contract.division_id', 'left');
		
		// Join jobs table through latest contract
		$this->db->join('jobs', 'jobs.job_id = latest_contract.job_id', 'left');
		
		// Execute the query
		$qry = $this->db->get();
		
		// Debug: Log the SQL query
		log_message('debug', 'Count SQL Query: ' . $this->db->last_query());
		
		$result = $qry->row();
		return $result ? $result->total : 0;
	}

    public function get_logs($key = array(), $limit = 10, $offset = 0) {
        // Retrieve filter values with defaults
        $email     = isset($key['email']) ? $key['email'] : '';
        $name      = isset($key['name']) ? $key['name'] : '';
        $date_from = isset($key['date_from']) ? $key['date_from'] : '';
        $date_to   = isset($key['date_to']) ? $key['date_to'] : '';

        // Start building the query
        $this->db->select('*');
        $this->db->from('user_logs');
		$this->db->join('user','user.user_id=user_logs.user_id');
		$this->db->join('staff','user.auth_staff_id=staff.staff_id');

        // Add search conditions for email and name
        if (!empty($email) || !empty($name)) {
            $this->db->group_start();
            if (!empty($email)) {
                // Using "after" to search for emails that start with the given value
                $this->db->like('staff.work_email', $email, 'after');
            }
            if (!empty($name)) {
                // Using "after" to search for names that start with the given value
                $this->db->like('name', $name, 'after');
            }
            $this->db->group_end();
        }

        // Add date range filter if both dates are provided
        if (!empty($date_from) && !empty($date_to)) {
            $this->db->where('date_loged_in >=', $date_from);
            $this->db->where('date_loged_in <=', $date_to);
        }

        // Add limit and offset for pagination
        $this->db->limit($limit, $offset);

        // Execute the query and return the results
        $query = $this->db->get();
		//dd($this->db->last_query());
        return $query->result();
    }



public function count_logs($key)
{
$qry = $this->db->get('user_logs');
return $qry->num_rows();
}
	public function addUser($postdata)
	{

		$user = array(
			"email" => $postdata['email'],
			"contact" => $postdata['contact'],
			"username" => $postdata['username'],
			"password" => $this->default_password,
			"name" => $postdata['name'],
			"role" => $postdata['role'],
			"division_id" => $postdata['division'],
			"status" => "1"

		);
		$qry = $this->db->insert($this->table, $user);
		$last_id = $this->db->insert_id();

		//insert access levels
		$rows = $this->db->affected_rows();
		if ($qry) {
			return "Saved Successfully";
		} else {
			return "Operation failed";
		}
	}

	
	// update user's details
	public function updateUser($postdata)
	{
		if (!isset($postdata['user_id'])) {
			return "User ID is required.";
		}
		
		$uid = $postdata['user_id'];
		unset($postdata['user_id']); // Remove user_id from update fields
		
		$this->db->where('user_id', $uid);
		$this->db->update('user', $postdata);

		//dd($this->db->last_query());
		
		if ($this->db->affected_rows() > 0) {
			return "User details updated successfully.";
		} else {
			return "No changes made or user not found.";
		}
		
	}
	// change password
	public function changePass($postdata)
	{
		$oldpass = $postdata['oldpass'];
		$newpass = $this->argonhash->make($postdata['newpass']);
		$user = $this->session->userdata('user');
		$uid = $user->user_id;
		$this->db->select('password');
		$this->db->where('user_id', $uid);
		$qry = $this->db->get($this->table);
		$user = $qry->row();
		$dbpassword = $user->password;

		//dd($this->argonhash->check($oldpass, $dbpassword));
		if  ($this->argonhash->check($oldpass, $dbpassword)) {
			// change the password
			$data = array("password" => $newpass, "isChanged" => 1);
			$this->db->where('user_id', $uid);
			$query = $this->db->update($this->table, $data);

			if ($query) {
				$_SESSION['changed'] = 1;
				return "Password Change Successful";
			} else {
				return "Operation failed, try again";
			}
		} else {
			return "The old password you provided is wrong";
		}
	}
	public function updateProfile($data)
	{
		$user_id = $data['user_id'];
	
		$insert = [
			'auth_staff_id' => $data['staff_id'],
			'user_id'       => $user_id,
			'name'          => $data['name'],
			'langauge'      => $data['langauge']
		];
	
		$this->db->where('user_id', $user_id);
		$done = $this->db->update($this->table, $insert);
	
		if ($done && !empty($data['staff_id'])) {
			$staff_data = [
				'staff_id'       => $data['staff_id'],
				'tel_1'          => $data['tel_1'],
				'tel_2'          => $data['tel_2'],
				'private_email'  => $data['private_email'],
				'whatsapp'       => $data['whatsapp'],
			];
	
			if (!empty($data['photo'])) {
				$staff_data['photo'] = $data['photo'];
			}
	
			if (!empty($data['signature'])) {
				$staff_data['signature'] = $data['signature'];
			}
	        
			$staff_updated = $this->update_staff_table($staff_data);
			return $staff_updated ? "Update Successful" : "Staff Update Failed";
		}
	
		if (!$done) {
			log_message('error', 'User update failed: ' . $this->db->last_query());
			log_message('error', 'Error: ' . $this->db->_error_message());
		}
	
		return $done ? "Update Successful" : "Update Failed";
	}
	
	public function update_staff_table($staff_data)
	{
		$this->db->where('staff_id', $staff_data['staff_id']);
		$done = $this->db->update('staff', $staff_data);
	
		if (!$done) {
			log_message('error', 'STAFF update failed: ' . $this->db->last_query());
			log_message('error', 'Error: ' . $this->db->_error_message());
		} else {
			$session_user = $this->session->userdata('user');
	
			foreach (['tel_1', 'tel_2', 'private_email', 'whatsapp', 'langauge'] as $key) {
				if (!empty($staff_data[$key])) {
					$session_user->$key = $staff_data[$key];
				}
			}
	
			$this->session->set_userdata('user', $session_user);
		}
	
		return $done;
	}
	


	public function saveProfile($language, $username, $name, $email, $photo)
	{
		// Perform the necessary operations to save the profile data
		// Replace this with your actual logic to save the data to the database or any other storage

		// Example: Saving to a database table named 'profiles'
		$data = array(
			'language' => $language,
			'username' => $username,
			'name' => $name,
			'email' => $email,
			'photo' => $photo
		);

		$this->db->insert('user', $data);

		if ($this->db->affected_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	//block
	public function blockUser($postdata)
	{
		$uid = $postdata['user_id'];
		$data = array("status" => 0);
		$this->db->where('user_id', $uid);
		$done = $this->db->update($this->table, $data);

		if ($done) {
			return "User has been blocked";
		} else {
			return "Failed, Try Again";
		}
	}
	//unblock user
	public function unblockUser($postdata)
	{
		$uid = $postdata['user_id'];
		$data = array("status" => 1);
		$this->db->where('user_id', $uid);
		$done = $this->db->update($this->table, $data);
		if ($done) {
			return "User has been Unblocked";
		} else {
			return "Failed, Try Again";
		}
	}


	public function getPermissions()
	{
		$query = $this->db->get("permissions");
		$perms = $query->result();
		return $perms;
	}
	public function groupPermissions($group)
	{
		$query = $this->db->query("SELECT permissions.id, name, definition,id,group_permissions.permission_id from permissions,group_permissions where permissions.id=group_permissions.permission_id and id='$group'");
		$perms = $query->result_array();
		return $perms;
	}
	public function getGroupPerms($groupId = FALSE)
	{
		$this->db->where('id', $groupId);
		$this->db->join('permissions', 'permissions.id=group_permissions.permission_id');
		$qry = $this->db->get('group_permissions');
		return $qry->result();
	}
	public function getUserPerms($groupId)
	{
		$this->db->where('id', $groupId);
		$qry = $this->db->get('group_permissions');
		$permissions = $qry->result();
		$perms = array();
		foreach ($permissions as $perm) {
			array_push($perms, $perm->permission_id);
		}
		return $perms;
	}
	public function savePermissions($data)
	{
		$data['definition'] = ucwords($data['definition']);
		$data['name'] = strtolower(str_replace(" ", "", $data['name']));
		$save = $this->db->insert('permissions', $data);
		return $save;
	}
	public function assignPermissions($groupId, $data)
	{
		if (count($data) > 0) {
			$this->db->where('id', $groupId);
			$this->db->delete('group_permissions');
			$save = $this->db->insert_batch('group_permissions', $data);
			return $save;
		}
		return false;
	}

	/**
	 * Get all permissions for a user, including group and custom user permissions.
	 * @param int $role Group/role id
	 * @param int|false $user_id Optional user id for custom permissions
	 * @return array List of permission ids
	 */
	public function user_permissions($role, $user_id = false)
	{
		$perms = array();

		// Get group permissions
		$this->db->select('permission_id');
		$this->db->where("group_id", $role);
		$group_query = $this->db->get('group_permissions')->result();
		foreach ($group_query as $perm) {
			$perms[] = $perm->permission_id;
		}

		// Get custom user permissions if user_id is provided
		if ($user_id) {
			$this->db->select('permission_id');
			$this->db->where('user_id', $user_id);
			$user_perms_query = $this->db->get('user_permissions')->result();
			foreach ($user_perms_query as $perm) {
				$perms[] = $perm->permission_id;
			}
		}

		// Remove duplicates and reindex
		$perms = array_values(array_unique($perms));
		return $perms;
	}

	public function find_user($id)
	{
		$this->db->where('user_id', $id);
		return $this->db->get($this->table)->row();
	}

	// Reset password
	public function resetPass($postdata)
	{
		if (!isset($postdata['user_id']) || empty($postdata['user_id'])) {
			return "User ID is required";
		}

		$uid = $postdata['user_id'];
		
		// Get the password - use provided password or default password
		$password = isset($postdata['password']) ? $postdata['password'] : setting()->default_password;
		
		// Hash the password
		$hashedPassword = $this->argonhash->make($password);
		
		// Update the password
		$data = array(
			"password" => $hashedPassword,
			"isChanged" => 0 // Reset the isChanged flag since it's a reset
		);
		
		$this->db->where('user_id', $uid);
		$done = $this->db->update($this->table, $data);

		if ($done) {
			return "Password has been reset successfully";
		} else {
			return "Failed to reset password. Please try again";
		}
	}
}
