<?php
defined('BASEPATH') or exit('No direct script access allowed');

class UserPermissions extends MX_Controller
{
    protected $module;
    public function __construct()
    {
        parent::__construct();
        $this->load->model('permissions_mdl', 'perms_mdl');
        $this->load->model('user_permissions_mdl', 'user_perms_mdl');
        $this->module = "permissions";
    }

    public function index()
    {
        $data['module'] = $this->module;
        $data['title'] = "User Permissions Management";
        
        // For server-side pagination, we only need to pass the view
        // Data will be loaded via AJAX
        render('user_permissions/index', $data);
    }

    public function getUsersAjax()
    {
        // Get simple pagination parameters
        $page = $this->input->post('page') ?: 1;
        $pageSize = $this->input->post('pageSize') ?: 25;
        $start = ($page - 1) * $pageSize;
        
        // Get search and filter parameters
        $search = $this->input->post('search') ?: '';
        $group_id = $this->input->post('group_id') ?: '';
        
        // Prepare filters
        $filters = [
            'search' => $search,
            'group_id' => $group_id
        ];
        
        // Get total count for pagination
        $total_records = $this->user_perms_mdl->getTotalUsersCount($filters);
        
        // Get filtered and paginated data
        $users = $this->user_perms_mdl->getAllUsersWithPermissions($pageSize, $start, $filters);
        
        // Prepare response for simple pagination
        $response = [
            'data' => $users,
            'recordsTotal' => $total_records,
            'currentPage' => $page,
            'pageSize' => $pageSize,
            'totalPages' => ceil($total_records / $pageSize)
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function userDetails($userId)
    {
        $data['module'] = $this->module;
        $data['title'] = "User Permissions Details";
        
        // Get user information
        $data['user'] = $this->user_perms_mdl->getUserById($userId);
        if (!$data['user']) {
            show_404();
        }
        
        // Get user's current permissions
        $data['userPermissions'] = $this->user_perms_mdl->getUserPermissions($userId);
        
        // Get all available permissions for assignment
        $data['allPermissions'] = $this->perms_mdl->getPermissions();
        
        // Get user's group permissions for reference
        $data['groupPermissions'] = $this->perms_mdl->getGroupPerms($data['user']->role);
        
        render('user_permissions/user_details', $data);
    }

    public function assignPermissions()
    {
        $data = $this->input->post();
        
        // Validate input
        if (empty($data['user_id']) || empty($data['permissions'])) {
            $msg = "Error: User and permissions are required.";
            Modules::run('utility/setFlash', $msg, 'error');
            redirect('permissions/userpermissions/userDetails/' . $data['user_id']);
            return;
        }
        
        $userId = $data['user_id'];
        $permissions = $data['permissions'];
        
        // Clear existing user permissions
        $this->user_perms_mdl->clearUserPermissions($userId);
        
        // Insert new permissions
        $insertData = array();
        foreach ($permissions as $permissionId) {
            $insertData[] = array(
                'user_id' => $userId,
                'permission_id' => $permissionId,
                'last_updated' => date('Y-m-d H:i:s')
            );
        }
        
        if (!empty($insertData)) {
            $result = $this->user_perms_mdl->insertUserPermissions($insertData);
            if ($result) {
                $msg = "User permissions have been updated successfully!";
                Modules::run('utility/setFlash', $msg, 'success');
            } else {
                $msg = "Error: Failed to update user permissions. Please try again.";
                Modules::run('utility/setFlash', $msg, 'error');
            }
        } else {
            $msg = "Info: All user permissions have been removed.";
            Modules::run('utility/setFlash', $msg, 'info');
        }
        
        redirect('permissions/userpermissions/userDetails/' . $userId);
    }

    public function copyGroupPermissions()
    {
        $data = $this->input->post();
        
        if (empty($data['user_id'])) {
            $msg = "Error: User ID is required.";
            Modules::run('utility/setFlash', $msg, 'error');
            redirect('permissions/userpermissions');
            return;
        }
        
        $userId = $data['user_id'];
        
        // Get user's group permissions
        $user = $this->user_perms_mdl->getUserById($userId);
        if (!$user) {
            $msg = "Error: User not found.";
            Modules::run('utility/setFlash', $msg, 'error');
            redirect('permissions/userpermissions');
            return;
        }
        
        $groupPermissions = $this->perms_mdl->getGroupPerms($user->role);
        
        // Clear existing user permissions
        $this->user_perms_mdl->clearUserPermissions($userId);
        
        // Copy group permissions to user permissions
        $insertData = array();
        foreach ($groupPermissions as $perm) {
            $insertData[] = array(
                'user_id' => $userId,
                'permission_id' => $perm->permission_id,
                'last_updated' => date('Y-m-d H:i:s')
            );
        }
        
        if (!empty($insertData)) {
            $result = $this->user_perms_mdl->insertUserPermissions($insertData);
            if ($result) {
                $msg = "Group permissions have been copied to user successfully!";
                Modules::run('utility/setFlash', $msg, 'success');
            } else {
                $msg = "Error: Failed to copy group permissions. Please try again.";
                Modules::run('utility/setFlash', $msg, 'error');
            }
        } else {
            $msg = "Info: No group permissions to copy.";
            Modules::run('utility/setFlash', $msg, 'info');
        }
        
        redirect('permissions/userpermissions/userDetails/' . $userId);
    }

    public function removeUserPermission()
    {
        $data = $this->input->post();
        
        if (empty($data['user_id']) || empty($data['permission_id'])) {
            $msg = "Error: User ID and Permission ID are required.";
            Modules::run('utility/setFlash', $msg, 'error');
            redirect('permissions/userpermissions');
            return;
        }
        
        $result = $this->user_perms_mdl->removeUserPermission($data['user_id'], $data['permission_id']);
        
        if ($result) {
            $msg = "Permission has been removed successfully!";
            Modules::run('utility/setFlash', $msg, 'success');
        } else {
            $msg = "Error: Failed to remove permission. Please try again.";
            Modules::run('utility/setFlash', $msg, 'error');
        }
        
        redirect('permissions/userpermissions/userDetails/' . $data['user_id']);
    }

    public function getUserPermissionsAjax()
    {
        $userId = $this->input->get('user_id');
        $permissions = $this->user_perms_mdl->getUserPermissions($userId);
        
        $response = array();
        foreach ($permissions as $perm) {
            $response[] = array(
                'id' => $perm->permission_id,
                'name' => $perm->name,
                'definition' => $perm->definition,
                'module' => $perm->module
            );
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public function refreshCSRF()
    {
        $response = [
            'csrf_token' => $this->security->get_csrf_hash()
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
    }
}
