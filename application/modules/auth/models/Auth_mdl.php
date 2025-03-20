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
	}
	public function login($postdata)
	{
	            $email = trim($postdata['email']);
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
		$this->db->select('staff.*, user.*, user_groups.*'); // Select required columns
		$this->db->from('staff'); // Set the main table
	
		// Add search conditions
		if (!empty($key)) {
			$this->db->group_start(); // Start a group for OR conditions
			$this->db->like("staff.work_email", "$key", "both");
			$this->db->or_like("staff.fname", "$key", "both");
			$this->db->or_like("staff.lname", "$key", "both");
			$this->db->group_end(); // End the group
		}
	
		// Join the staff table with a unique alias (staff1)
		$this->db->join('user', 'user.auth_staff_id = staff.staff_id');
	
		// Join the user_groups table
		$this->db->join('user_groups', 'user_groups.id = user.role');
	
		// Add limit and offset
		$this->db->limit( $start,$limit);
	
		// Execute the query
		$qry = $this->db->get();
		return $qry->result();
	}
	public function count_Users($key)
{
    $this->db->from('staff'); // Set the main table

    // Add search conditions
    if (!empty($key)) {
        $this->db->group_start(); // Start a group for OR conditions
        $this->db->like("staff.work_email", "$key", "both");
        $this->db->or_like("staff.fname", "$key", "both");
        $this->db->or_like("staff.lname", "$key", "both");
        $this->db->group_end(); // End the group
    }

    // Join the staff table with a unique alias (staff1)
	$this->db->join('user', 'user.auth_staff_id = staff.staff_id');

    // Execute the query
    $qry = $this->db->get();
    return $qry->num_rows();
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

        // Add search conditions for email and name
        if (!empty($email) || !empty($name)) {
            $this->db->group_start();
            if (!empty($email)) {
                // Using "after" to search for emails that start with the given value
                $this->db->like('email', $email, 'after');
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
		$oldpass = $this->argon->make($postdata['oldpass']);
		$newpass = md5($postdata['newpass']);
		$user = $this->session->get_userdata();
		$uid = $user['user_id'];
		$this->db->select('password');
		$this->db->where('user_id', $uid);
		$qry = $this->db->get($this->table);
		$user = $qry->row();
		if ($user->password == $oldpass) {
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
		$insert['auth_staff_id'] = $data['staff_id'];
		$insert['user_id'] = $data['user_id'];
		$insert['name'] = $data['name'];
		$insert['langauge'] = $data['langauge'];
		
	
		
		unset($data['staff_id']);
		$this->db->where('user_id', $user_id);
		$done = $this->db->update($this->table, $insert);

		if ($done) {
			$data['staff_id'] = $data['auth_staff_id'];
	
			if ($data['staff_id'] != 0):
				$staff_data['tel_1'] = $this->input->post('tel_1');
				$staff_data['tel_2'] = $this->input->post('tel_2');
				$staff_data['private_email'] = $this->input->post('private_email');
				$staff_data['whatsapp'] = $this->input->post('whatsapp');
				$staff_data['staff_id'] = $data['staff_id'];
				$staff_data['photo'] = $data['photo'];
				$staff_data['signature'] = $data['signature'];
			$this->update_staff_table($staff_data);
			endif;
			return "Update Successful";
		} else {
			return "Update Failed";
		}
	}
	public function update_staff_table($staff_data){
		
		$this->db->where('staff_id', $staff_data['staff_id']);
		$done = $this->db->update('staff', $staff_data);

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

	public function user_permissions($role)
	{
		 $this->db->select('permission_id');
		 $this->db->where("group_id", $role);
		$query = $this->db->get('group_permissions')->result();

		$perms = array();
		foreach ($query as $perm) {
			array_push($perms, $perm->permission_id);
		}
		return $perms;
	}

	public function find_user($id)
	{
		$this->db->where('user_id', $id);
		return $this->db->get($this->table, $id)->row();
	}
}
