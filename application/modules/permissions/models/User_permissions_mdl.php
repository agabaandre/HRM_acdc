<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User_permissions_mdl extends CI_Model
{
    protected $user_permissions_table;
    protected $user_table;
    protected $permissions_table;
    protected $user_groups_table;
    
    public function __construct()
    {
        parent::__construct();
        $this->user_permissions_table = "user_permissions";
        $this->user_table = "user";
        $this->permissions_table = "permissions";
        $this->user_groups_table = "user_groups";
    }

    public function getAllUsersWithPermissions($limit = false, $start = false, $filters = false)
    {
        $this->db->select('user.*, user_groups.group_name, COUNT(user_permissions.permission_id) as permission_count');
        $this->db->from($this->user_table);
        $this->db->join('user_groups', 'user_groups.id = user.role', 'left');
        $this->db->join('user_permissions', 'user_permissions.user_id = user.user_id', 'left');
        
        // Apply filters if provided
        if ($filters && is_array($filters)) {
            // Search by name
            if (!empty($filters['search'])) {
                $this->db->group_start();
                $this->db->like('user.name', $filters['search'], 'both');
                $this->db->or_like('user.email', $filters['search'], 'both');
                $this->db->or_like('user.auth_staff_id', $filters['search'], 'both');
                $this->db->group_end();
            }
            
            // Filter by group
            if (!empty($filters['group_id'])) {
                $this->db->where('user.role', $filters['group_id']);
            }
        }
        
        $this->db->group_by('user.user_id');
        $this->db->order_by('user.name', 'ASC');
        
        // Apply pagination if limit is provided
        if ($limit) {
            $this->db->limit($limit, $start);
        }
        
        $query = $this->db->get();
        
        $results = $query->result();
        
        // Ensure all user fields have default values if null
        foreach ($results as $user) {
            $user->name = $user->name ?? 'Unknown User';
            $user->user_id = $user->user_id ?? 0;
            $user->group_name = $user->group_name ?? 'No Group';
            $user->permission_count = $user->permission_count ?? 0;
            $user->status = $user->status ?? 0;
        }
        
        return $results;
    }

    public function getTotalUsersCount($filters = false)
    {
        $this->db->select('COUNT(DISTINCT user.user_id) as total');
        $this->db->from($this->user_table);
        $this->db->join('user_groups', 'user_groups.id = user.role', 'left');
        
        // Apply filters if provided
        if ($filters && is_array($filters)) {
            // Search by name
            if (!empty($filters['search'])) {
                $this->db->group_start();
                $this->db->like('user.name', $filters['search'], 'both');
                $this->db->or_like('user.email', $filters['search'], 'both');
                $this->db->or_like('user.auth_staff_id', $filters['search'], 'both');
                $this->db->group_end();
            }
            
            // Filter by group
            if (!empty($filters['group_id'])) {
                $this->db->where('user.role', $filters['group_id']);
            }
        }
        
        $query = $this->db->get();
        $result = $query->row();
        return $result ? $result->total : 0;
    }

    public function getUserById($userId)
    {
        $this->db->select('user.*, user_groups.group_name');
        $this->db->from($this->user_table);
        $this->db->join('user_groups', 'user_groups.id = user.role', 'left');
        $this->db->where('user.user_id', $userId);
        $query = $this->db->get();
        $user = $query->row();
        
        if ($user) {
            // Ensure all user fields have default values if null
            $user->name = $user->name ?? 'Unknown User';
            $user->user_id = $user->user_id ?? 0;
            $user->group_name = $user->group_name ?? 'No Group';
            $user->status = $user->status ?? 0;
        }
        
        return $user;
    }

    public function getUserPermissions($userId)
    {
        $this->db->select('user_permissions.*, permissions.name, permissions.definition, permissions.module');
        $this->db->from($this->user_permissions_table);
        $this->db->join('permissions', 'permissions.id = user_permissions.permission_id');
        $this->db->where('user_permissions.user_id', $userId);
        $this->db->order_by('permissions.module', 'ASC');
        $this->db->order_by('permissions.name', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    public function getUserPermissionIds($userId)
    {
        $this->db->select('permission_id');
        $this->db->from($this->user_permissions_table);
        $this->db->where('user_id', $userId);
        $query = $this->db->get();
        
        $permissionIds = array();
        foreach ($query->result() as $row) {
            $permissionIds[] = $row->permission_id;
        }
        return $permissionIds;
    }

    public function clearUserPermissions($userId)
    {
        $this->db->where('user_id', $userId);
        return $this->db->delete($this->user_permissions_table);
    }

    public function insertUserPermissions($data)
    {
        if (empty($data)) {
            return false;
        }
        return $this->db->insert_batch($this->user_permissions_table, $data);
    }

    public function removeUserPermission($userId, $permissionId)
    {
        $this->db->where('user_id', $userId);
        $this->db->where('permission_id', $permissionId);
        return $this->db->delete($this->user_permissions_table);
    }

    public function addUserPermission($userId, $permissionId)
    {
        $data = array(
            'user_id' => $userId,
            'permission_id' => $permissionId,
            'last_updated' => date('Y-m-d H:i:s')
        );
        
        // Check if permission already exists
        $this->db->where('user_id', $userId);
        $this->db->where('permission_id', $permissionId);
        $exists = $this->db->get($this->user_permissions_table)->num_rows();
        
        if ($exists) {
            // Update last_updated
            $this->db->where('user_id', $userId);
            $this->db->where('permission_id', $permissionId);
            return $this->db->update($this->user_permissions_table, array('last_updated' => date('Y-m-d H:i:s')));
        } else {
            // Insert new permission
            return $this->db->insert($this->user_permissions_table, $data);
        }
    }

    public function getUserPermissionsByModule($userId)
    {
        $this->db->select('user_permissions.*, permissions.name, permissions.definition, permissions.module');
        $this->db->from($this->user_permissions_table);
        $this->db->join('permissions', 'permissions.id = user_permissions.permission_id');
        $this->db->where('user_permissions.user_id', $userId);
        $this->db->order_by('permissions.module', 'ASC');
        $this->db->order_by('permissions.name', 'ASC');
        $query = $this->db->get();
        
        $permissionsByModule = array();
        foreach ($query->result() as $perm) {
            $module = $perm->module ?? 'General';
            $permissionsByModule[$module][] = $perm;
        }
        
        return $permissionsByModule;
    }

    public function checkUserHasPermission($userId, $permissionName)
    {
        $this->db->select('user_permissions.*');
        $this->db->from($this->user_permissions_table);
        $this->db->join('permissions', 'permissions.id = user_permissions.permission_id');
        $this->db->where('user_permissions.user_id', $userId);
        $this->db->where('permissions.name', $permissionName);
        $query = $this->db->get();
        return $query->num_rows() > 0;
    }

    public function getUsersWithPermission($permissionName)
    {
        $this->db->select('user.*, user_groups.group_name');
        $this->db->from($this->user_table);
        $this->db->join('user_groups', 'user_groups.id = user.role', 'left');
        $this->db->join('user_permissions', 'user_permissions.user_id = user.user_id');
        $this->db->join('permissions', 'permissions.id = user_permissions.permission_id');
        $this->db->where('permissions.name', $permissionName);
        $this->db->order_by('user.name', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }

    public function getPermissionUsageStats()
    {
        $this->db->select('permissions.name, permissions.definition, permissions.module, COUNT(user_permissions.user_id) as user_count');
        $this->db->from('permissions');
        $this->db->join('user_permissions', 'user_permissions.permission_id = permissions.id', 'left');
        $this->db->group_by('permissions.id');
        $this->db->order_by('user_count', 'DESC');
        $this->db->order_by('permissions.module', 'ASC');
        $query = $this->db->get();
        return $query->result();
    }
}
