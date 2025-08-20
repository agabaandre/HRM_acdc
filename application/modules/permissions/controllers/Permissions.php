<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Permissions extends MX_Controller
{
  public function __construct()
  {
    parent::__construct();

    $this->load->model('permissions_mdl', 'perms_mdl');
    $this->module = "permissions";
  }
  public function index()
  {
    $data['module'] = $this->module;
    $data['title'] = $data['uptitle'] = "User Permissions";
    
    // Use the render function like the rest of the project
    render('groups', $data);
  }


  //permissions management

  public function getUserGroups()
  {
    $groups = $this->perms_mdl->getUserGroups();
    return $groups;
  }

  public function addPermissions()
  {
    $data['view'] = "add_permissions";
    $data['title'] = "Add Permission";
    $data['module'] = "permissions";
    echo Modules::run('templates/main', $data);
  }
  public function getPermissions()
  {
    $perms = $this->perms_mdl->getPermissions();
    return $perms;
  }
  public function groupPermissions($group = FALSE)
  {
    $fperms = array();
    $perms = $this->perms_mdl->groupPermissions($group);
    foreach ($perms as $perm) {
      $perm['id'];
      array_push($fperms, $perm['id']);
    }
    return $fperms;
  }
  public function getGroupPerms($groupId = FALSE)
  {
    $perms = $this->perms_mdl->getGroupPerms($groupId);
    return $perms;
    //print_r($perms);
  }
  public function savePermissions()
  {
    $data = $this->input->post();
    
    // Validate input
    if (empty($data['definition']) || empty($data['name'])) {
      $msg = "Error: Both permission description and name are required.";
      Modules::run('utility/setFlash', $msg, 'error');
      redirect('permissions');
      return;
    }
    
    // Validate permission name format
    if (!preg_match('/^[a-zA-Z_]+$/', $data['name'])) {
      $msg = "Error: Permission name can only contain letters and underscores.";
      Modules::run('utility/setFlash', $msg, 'error');
      redirect('permissions');
      return;
    }
    
    // Check if permission already exists
    $existing = $this->perms_mdl->checkPermissionExists($data['name']);
    if ($existing) {
      $msg = "Error: Permission '{$data['name']}' already exists.";
      Modules::run('utility/setFlash', $msg, 'error');
      redirect('permissions');
      return;
    }
    
    $post_d = $this->perms_mdl->savePermissions($data);
    if ($post_d) {
      $msg = "Permission '{$data['name']}' has been created successfully!";
      Modules::run('utility/setFlash', $msg, 'success');
    } else {
      $msg = "Error: Failed to create permission. Please try again.";
      Modules::run('utility/setFlash', $msg, 'error');
    }
    redirect('permissions');
  }
  
  public function addGroup()
  {
    $data = $this->input->post();
    
    // Validate input
    if (empty($data['group_name'])) {
      $msg = "Error: Group name is required.";
      Modules::run('utility/setFlash', $msg, 'error');
      redirect('permissions');
      return;
    }
    
    if (strlen($data['group_name']) < 3) {
      $msg = "Error: Group name must be at least 3 characters long.";
      Modules::run('utility/setFlash', $msg, 'error');
      redirect('permissions');
      return;
    }
    
    // Check if group already exists
    $existing = $this->perms_mdl->checkGroupExists($data['group_name']);
    if ($existing) {
      $msg = "Error: Group '{$data['group_name']}' already exists.";
      Modules::run('utility/setFlash', $msg, 'error');
      redirect('permissions');
      return;
    }
    
    $post_d = $this->perms_mdl->addGroup($data);
    if ($post_d) {
      $msg = "Group '{$data['group_name']}' has been created successfully!";
      Modules::run('utility/setFlash', $msg, 'success');
    } else {
      $msg = "Error: Failed to create group. Please try again.";
      Modules::run('utility/setFlash', $msg, 'error');
    }
    redirect('permissions');
  }
  
  public function assignPermissions()
  {
    $groupId = $this->input->post('group');
    $this->session->set_flashdata('group', $groupId);
    
    if (!empty($this->input->post('assign'))) {
      $data = $this->input->post();
      $permissions = isset($data['permissions']) ? $data['permissions'] : [];
      
      if (empty($permissions)) {
        $msg = "Warning: No permissions were selected. Group permissions have been cleared.";
        Modules::run('utility/setFlash', $msg, 'warning');
      } else {
        $insert_data = array();
        foreach ($permissions as $perm) {
          $row = array("group_id" => $groupId, "permission_id" => $perm);
          array_push($insert_data, $row);
        }
        
        $post_d = $this->perms_mdl->assignPermissions($groupId, $insert_data);
        if ($post_d) {
          $groupName = $this->perms_mdl->getGroupName($groupId);
          $msg = "Permissions for group '{$groupName}' have been updated successfully!";
          Modules::run('utility/setFlash', $msg, 'success');
        } else {
          $msg = "Error: Failed to update permissions. Please try again.";
          Modules::run('utility/setFlash', $msg, 'error');
        }
      }
    } else {
      $msg = "Info: Permission assignment is disabled. No changes were made.";
      Modules::run('utility/setFlash', $msg, 'info');
    }
    
    		redirect('permissions');
	}
	
	public function groupDetails($groupId)
	{
		$data['module'] = $this->module;
		$data['title'] = "Group Details";
		
		// Get group information
		$data['group'] = $this->perms_mdl->getGroupById($groupId);
   
		if (!$data['group']) {
			show_404();
		}
		
		// Get group users
		$data['users'] = $this->perms_mdl->getGroupUsers($groupId);
		$data['userCount'] = count($data['users']);
		
		// Get group permissions
		$data['permissions'] = $this->perms_mdl->getGroupPerms($groupId);
		
		// Ensure arrays are initialized if empty
		if (empty($data['users'])) {
			$data['users'] = array();
		}
		
		if (empty($data['permissions'])) {
			$data['permissions'] = array();
		}
   	render('group_details', $data);
	}
	
	public function updateGroup()
	{
		$data = $this->input->post();
		
		// Validate input
		if (empty($data['group_name'])) {
			$msg = "Error: Group name is required.";
			Modules::run('utility/setFlash', $msg, 'error');
			redirect('permissions');
			return;
		}
		
		if (strlen($data['group_name']) < 3) {
			$msg = "Error: Group name must be at least 3 characters long.";
			Modules::run('utility/setFlash', $msg, 'error');
			redirect('permissions');
			return;
		}
		
		// Check if group name already exists (excluding current group)
		$existing = $this->perms_mdl->checkGroupExistsExcluding($data['group_name'], $data['group_id']);
		if ($existing) {
			$msg = "Error: Group name '{$data['group_name']}' already exists.";
			Modules::run('utility/setFlash', $msg, 'error');
			redirect('permissions');
			return;
		}
		
		$post_d = $this->perms_mdl->updateGroup($data);
		if ($post_d) {
			$msg = "Group '{$data['group_name']}' has been updated successfully!";
			Modules::run('utility/setFlash', $msg, 'success');
		} else {
			$msg = "Error: Failed to update group. Please try again.";
			Modules::run('utility/setFlash', $msg, 'error');
		}
		redirect('permissions');
	}
	
	public function addUserToGroup()
	{
		$data = $this->input->post();
		
		// Validate input
		if (empty($data['user_id']) || empty($data['group_id'])) {
			$msg = "Error: User and group are required.";
			Modules::run('utility/setFlash', $msg, 'error');
			redirect('permissions');
			return;
		}
		
		$post_d = $this->perms_mdl->addUserToGroup($data);
		if ($post_d) {
			$msg = "User has been added to the group successfully!";
			Modules::run('utility/setFlash', $msg, 'success');
		} else {
			$msg = "Error: Failed to add user to group. Please try again.";
			Modules::run('utility/setFlash', $msg, 'error');
		}
		redirect('permissions');
	}
	
	public function getAvailableUsersAjax()
	{
		$groupId = $this->input->get('group_id');
		$users = $this->perms_mdl->getAvailableUsers($groupId);
		
		$response = array();
		foreach ($users as $user) {
			$response[] = array(
				'id' => $user->id,
				'name' => $user->name,
				'email' => $user->email
			);
		}
		
		header('Content-Type: application/json');
		echo json_encode($response);
	}
}
