<?php
defined('BASEPATH') or exit('No direct script access allowed');
#[AllowDynamicProperties]
class Permissions_mdl extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->permissions_table = "permissions";
		$this->group_permissions_table = "group_permissions";
		$this->user_groups_table = "user_groups";
		$default_password = setting()->default_password;
	}
	public function getUserGroups()
	{
		$qry = $this->db->get($this->user_groups_table);
		$groups = $qry->result();
		return $groups;
	}
	public function getPermissions()
	{
		$query = $this->db->get($this->permissions_table);
		$perms = $query->result();
		return $perms;
	}
	public function groupPermissions($group)
	{
		$query = $this->db->query("SELECT permissions.id, name, definition,group_id,group_permissions.permission_id from permissions,group_permissions where permissions.id=group_permissions.permission_id and group_id='$group'");
		$perms = $query->result_array();
		return $perms;
	}
	public function getGroupPerms($groupId = FALSE)
	{
		$this->db->where('group_id', $groupId);
		$this->db->join('permissions', 'permissions.id=group_permissions.permission_id');
		$qry = $this->db->get('group_permissions');
		return $qry->result();
	}
	public function getUserPerms($groupId)
	{
		$this->db->where('group_id', $groupId);
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
			$this->db->where('group_id', $groupId);
			$this->db->delete('group_permissions');
			$save = $this->db->insert_batch('group_permissions', $data);
			return $save;
		}
		return false;
	}
	public function addGroup($data)
	{
		if (count($data) > 0) {
			$save = $this->db->insert('user_groups', $data);
			return $save;
		}
		return false;
	}
	
	public function checkPermissionExists($name)
	{
		$this->db->where('name', $name);
		$query = $this->db->get($this->permissions_table);
		return $query->num_rows() > 0;
	}
	
	public function checkGroupExists($groupName)
	{
		$this->db->where('group_name', $groupName);
		$query = $this->db->get($this->user_groups_table);
		return $query->num_rows() > 0;
	}
	
	public function getGroupName($groupId)
	{
		$this->db->where('id', $groupId);
		$query = $this->db->get($this->user_groups_table);
		$result = $query->row();
		return $result ? $result->group_name : 'Unknown Group';
	}
	
	public function getGroupUserCount($groupId)
	{
		$this->db->where('role', $groupId);
		$query = $this->db->get('user');
		return $query->num_rows();
	}
	
	public function getGroupUsers($groupId)
	{
		$this->db->select('user.*, user_groups.group_name');
		$this->db->from('user');
		$this->db->join('user_groups', 'user_groups.id = user.role');
		$this->db->where('user.role', $groupId);
		$this->db->order_by('user.name', 'ASC');
		$query = $this->db->get();
		return $query->result();
	}
	
	public function getGroupById($groupId)
	{
		$this->db->where('id', $groupId);
		$query = $this->db->get($this->user_groups_table);
		return $query->row();
	}
	
	public function checkGroupExistsExcluding($groupName, $excludeId)
	{
		$this->db->where('group_name', $groupName);
		$this->db->where('id !=', $excludeId);
		$query = $this->db->get($this->user_groups_table);
		return $query->num_rows() > 0;
	}
	
	public function updateGroup($data)
	{
		$updateData = array(
			'group_name' => $data['group_name']
		);
		
		$this->db->where('id', $data['group_id']);
		return $this->db->update($this->user_groups_table, $updateData);
	}
	
	public function addUserToGroup($data)
	{
		// Update user's role to the specified group
		$this->db->where('id', $data['user_id']);
		return $this->db->update('user', array('role' => $data['group_id']));
	}
	
	public function getAvailableUsers($excludeGroupId = null)
	{
		$this->db->select('user.*');
		$this->db->from('user');
		
		if ($excludeGroupId) {
			$this->db->where('user.role !=', $excludeGroupId);
			$this->db->or_where('user.role IS NULL');
		}
		
		$this->db->order_by('user.name', 'ASC');
		$query = $this->db->get();
		return $query->result();
	}
}
