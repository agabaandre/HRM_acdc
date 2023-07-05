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
		$username = $postdata['username'];
		if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
			//login using username
			$this->db->where("username", $username);
			$this->db->where("status", 1);
			$this->db->join('user_groups', 'user_groups.id=user.role');
			$qry = $this->db->get($this->table);
			$rows = $qry->num_rows();
			if ($rows !== 0) {
				$person = $qry->row();
				return $person;
			}
		} else {
			//login using email
			$this->db->where("email", $username);
			$this->db->where("status", 1);
			$this->db->join('user_groups', 'user_groups.id=user.role');
			$qry = $this->db->get($this->table);
			$rows = $qry->num_rows();
			if ($rows !== 0) {
				$person = $qry->row();
				return $person;
			}
		}
	}

	public function getAll($start, $limit, $key)
	{
		if (!empty($key)) {
			$this->db->like("username", "$key", "both");
			$this->db->or_like("name", "$key", "both");
		}
		$this->db->limit($start, $limit);
		$this->db->join('user_groups', 'user_groups.id=user.role', 'left');
		$qry = $this->db->get($this->table);
		return $qry->result();
	}
	public function count_Users($key)
	{
		if (!empty($key)) {
			$this->db->like("username", "$key", "both");
			$this->db->or_like("name", "$key", "both");
		}
		$qry = $this->db->get($this->table);
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
		$uid = $postdata['id'];
		$this->db->where('user_id', $uid);
		$query = $this->db->update($this->table, $postdata);
		if ($query) {
			return "User details updated";
		} else {
			return "No changes made";
		}
	}
	// change password
	public function changePass($postdata)
	{
		$oldpass = md5($postdata['oldpass']);
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
		$this->db->where('user_id', $user_id);
		$done = $this->db->update($this->table, $data);

		if ($done) {
			if ($data['staff_id'] != 0):
				$staff_data['staff_id'] = $data['staff_id'];
				$staff_data['photo'] = $data['photo'];
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
