<?php

use utils\HttpUtils;
defined('BASEPATH') or exit('No direct script access allowed');
use TheNetworg\OAuth2\Client\Provider\Azure;

class Auth extends MX_Controller
{
  private $provider;
  private $http;
  private $client_id;
  private $client_secret;
  private $tenant_id;
  private $redirect_uri;
  public function __construct()
  {
    parent::__construct();
    $this->load->model('auth_mdl');
    $this->module = "auth";
    $this->client_id = $_ENV['CLIENT_ID'];
    $this->client_secret = $_ENV['CLIENT_SEC_VALUE'];
    $this->tenant_id = $_ENV['TENANT_ID'];
    $this->redirect_uri = base_url('auth/callback');
  
  }
   
  
  public function index()
  {

    $this->load->view("login/login");
  }

  public function login() {
    $authorize_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/authorize";
    $params = [
        'client_id'     => $this->client_id,
        'response_type' => 'code',
        'redirect_uri'  => $this->redirect_uri,
        'response_mode' => 'query',
        'scope'         => 'openid profile email offline_access User.Read'
    ];
    redirect($authorize_url . '?' . http_build_query($params));
}

public function callback() {
    if (!$this->input->get('code')) {
        if ($this->input->get('error')) {
            exit('Error: ' . htmlspecialchars($this->input->get('error')));
        } else {
            exit('Invalid request');
        }
    }

    $token = $this->get_access_token($this->input->get('code'));
   // dd($token);
    if (!$token) {
        exit('Error retrieving access token.');
    }

    $user = $this->get_user_data($token);
    //dd($user);
    if ($user) {
        $email = $user['mail'] ?? $user['userPrincipalName']; // Use mail or userPrincipalName if mail is missing
        $name = $user['displayName'];

        // Check if email exists in the database
        $postdata = ['email' => $email];
        $data['users'] = $this->auth_mdl->login($postdata);

        //dd($data['users']);

        if (!empty($data['users'])) {
            // Proceed with login
            $this->handle_login($data['users'], $email);
            
        } else {

            // Reject login
            $this->session->set_flashdata('error', 'Staff profile missing. Contact HR.');
            redirect('auth');
        }
    } else {
        exit('Failed to fetch user details.');
    }
}

private function get_access_token($auth_code) {
    $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";

    $post_data = [
        'client_id'     => $this->client_id,
        'client_secret' => $this->client_secret,
        'code'          => $auth_code,
        'redirect_uri'  => $this->redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $headers = ['Content-Type: application/x-www-form-urlencoded'];

    $response = curl_send_post($token_url, $post_data, $headers);
    return $response->access_token ?? null;
}

private function get_user_data($access_token) {
    $url = "https://graph.microsoft.com/v1.0/me";
    $headers = ['Authorization: Bearer ' . $access_token, 'Accept: application/json'];

    $response = curl_send_get($url, $headers);
    return $response ?? null;
}

// private function handle_login($user_data, $email) {
//   //dd($user_data->auth_staff_id);
//   //dd($user_data);
//     $data['contract'] = $this->staff_mdl->get_latest_contracts($user_data->auth_staff_id);
//     $users_array = (array) $user_data;
//     $contract_array = (array) $data['contract'];
//     $users = array_merge($users_array, $contract_array);
    
//     $role = $user_data->role;

//     unset($users['password']);
//     $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
//     $users['is_admin'] = false;
//     $_SESSION['user'] = (object) $users;

//     //dd($data);

//     if (!empty($user_data)&& $role!=17 ) {
//       unset($users['password']);
//          $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
//           $users['is_admin'] = false;
//           $_SESSION['user'] = (object)$users;
//           //dd($_SESSION['user']);
//           $log_message = "User Logged in Successfully using MS SSO";
//           log_user_action($log_message);
//           redirect('dashboard/index');

       
      
//   }
//   else if (!empty($user_data)&& $role==17 ) {
//     unset($users['password']);
//        $users['permissions'] = $this->auth_mdl->user_permissions($users['role']);
//         $users['is_admin'] = false;
//         $_SESSION['user'] = (object)$users;
//         $log_message = "User Logged in Successfully using MS SSO";
//         log_user_action($log_message);
//         redirect('auth/profile');
    
// }
//   else {
  
//       redirect('auth');
//   }
// }

// public function logout() {
//     $this->session->sess_destroy();
//     redirect('https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/logout?post_logout_redirect_uri=' . base_url());
// }
private function handle_login($user_data, $email) {
  if (empty($user_data)) {
      $this->session->set_flashdata('error', 'Authentication failed. Please try again.');
      redirect('auth', 'refresh');
      exit;
  }

  $contract = $this->staff_mdl->get_latest_contracts($user_data->auth_staff_id);
  $users = array_merge((array)$user_data, (array)$contract);
  unset($users['password']);

  $users['permissions'] = $this->auth_mdl->user_permissions($users['role'],$users['user_id']);
  $users['is_admin'] = false;

  $this->session->set_userdata('user', (object)$users);


  if ($users['role']) {
      redirect('home/index', 'refresh');
  }
  else{
    $this->session->set_flashdata('error', 'Incorrect password. Please try again.');
        redirect('auth'); // Redirect back to login page
  }

 
}




public function cred_login()
{
    $postdata = $this->input->post();
    $post_password = trim($this->input->post('password'));

    // Fetch user data
    $data['users'] = $this->auth_mdl->login($postdata);

    // Check if user exists
    if (empty($data['users'])) {
        $this->session->set_flashdata('error', 'Invalid email or user does not exist.');
        redirect('auth'); // Redirect back to login page
        return;
    }

    $data['contract'] = $this->staff_mdl->get_latest_contracts($data['users']->staff_id);
    //dd( $data['contract']);

    $users_array = (array)$data['users'];
    $contract_array = (array)$data['contract'];
    $users = array_merge($users_array, $contract_array);
    
    // Use the stored hash from the database
    $dbpassword = $data['users']->password;
    $role = $data['users']->role;
    $auth = $this->validate_password($post_password, $dbpassword);

    if ($auth && !empty($data['users'])) {
        unset($users['password']);
        $users['permissions'] = $this->auth_mdl->user_permissions($users['role'],$users['user_id']);
        $users['is_admin'] = false;
        $_SESSION['user'] = (object)$users;
        $log_message = "User Logged in Successfully using Email and Password";
        log_user_action($log_message);
        redirect('home');
    }else{
        $this->session->set_flashdata('error', 'Incorrect password. Please try again.');
        redirect('auth'); // Redirect back to login page
    }
}

public function impersonate($user_id)
{
    // Check if current user is admin
    $current_user = $this->session->userdata('user');
    if (!$current_user || $current_user->role != 10) {
        show_error('You are not authorized to impersonate users.', 403);
    }

    // Prevent impersonating yourself
    if ($current_user->user_id == $user_id) {
        $this->session->set_flashdata('error', 'You cannot impersonate yourself.');
        redirect('dashboard');
    }

    // Store current session as "original user"
    $this->session->set_userdata('original_user', $current_user);
    $this->session->set_userdata('impersonation_start', time());

    // Fetch the user to impersonate
    $user = $this->auth_mdl->find_user($user_id);

    if (empty($user)) {
        $this->session->set_flashdata('error', 'User not found.');
        redirect('dashboard');
    }

    // Merge contract details
    $contract = $this->staff_mdl->get_latest_contracts($user->auth_staff_id);
    $user_array = (array)$user;
    $contract_array = (array)$contract;
    $merged = array_merge($user_array, $contract_array);

    unset($merged['password']);
    $merged['permissions'] = $this->auth_mdl->user_permissions($merged['role'],$merged['user_id']);
    $merged['is_admin'] = false;
    $merged['is_impersonated'] = true; // Flag to indicate impersonation

    $this->session->set_userdata('user', (object)$merged);
    $this->session->mark_as_temp('user', 300); // 5 minutes lifespan
    session_write_close(); // force session save

    // Log the impersonation action
    $log_message = "Admin " . $current_user->name . " (ID: " . $current_user->user_id . ") is now impersonating " . $user->name . " (ID: " . $user->user_id . ")";
        log_user_action($log_message);
  
    // Set success message
    $this->session->set_flashdata('success', 'You are now impersonating ' . $user->name . '. Click "Revert to Admin" to return to your account.');

    redirect('dashboard');
}


public function revert()
{
    $original = $this->session->userdata('original_user');

    if ($original) {
        $this->session->set_userdata('user', $original);
        $this->session->unset_userdata('original_user');
        $this->session->unset_userdata('impersonation_start'); // Clear impersonation start time

        $msg = [
            'msg' => 'You have returned to your admin session.',
            'type' => 'success'
        ];
    } else {
        $msg = [
            'msg' => 'You are not impersonating any user.',
            'type' => 'warning'
        ];
    }

    Modules::run('utility/setFlash', $msg);
    $log_message = "Reverted back to personal account";
    log_user_action($log_message);
    redirect('dashboard');
}


  public function validate_password($post_password,$dbpassword){
    $auth = ($this->argonhash->check($post_password, $dbpassword));
     if ($auth) {
       return TRUE;
     }
     else{
       return FALSE;
     }
     
   }

  public function profile()
  {
    $data['module'] = "auth";
    $data['view'] = "profile";
    $data['title'] = "My Profile";

    render("users/profile", $data);

  }
  public function logout()
  {
    // Clear session
   // Unset all session variables
   $log_message = "Logged Out";
   //log_user_action($log_message);
   $this->session->unset_userdata('user');
   $this->session->sess_destroy();

   // Clear session variables manually
   $_SESSION = array();
   
   // Remove the session cookie (if set)
   if (ini_get("session.use_cookies")) {
       $params = session_get_cookie_params();
       setcookie(session_name(), '', time() - 42000,
           $params["path"], $params["domain"],
           $params["secure"], $params["httponly"]
       );
   }
   
   // Prevent browser caching (important!)
   header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
   header("Pragma: no-cache");
   header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
  redirect("auth");
  }

  public function getUserByid($id)
  {
    $userrow = $this->auth_mdl->getUser($id);
    //print_r($userrow);
    return $userrow;
  }

  public function users()
  {
    $searchkey = $this->input->get('search_key');
    if (empty($searchkey)) {
      $searchkey = "";
    }
    $this->load->library('pagination');
    $config = array();
    $config['base_url'] = base_url() . "auth/users";
    $config['total_rows'] = $this->auth_mdl->count_Users($searchkey);
    $config['per_page'] = 20; //records per page
    $config['uri_segment'] = 3; //segment in url  
    //pagination links styling
    $config['full_tag_open'] = '<ul class="pagination">';
    $config['full_tag_close'] = '</ul>';
    $config['attributes'] = ['class' => 'page-link'];
    $config['first_link'] = false;
    $config['last_link'] = false;
    $config['first_tag_open'] = '<li class="page-item">';
    $config['first_tag_close'] = '</li>';
    $config['prev_link'] = '&laquo';
    $config['prev_tag_open'] = '<li class="page-item">';
    $config['prev_tag_close'] = '</li>';
    $config['next_link'] = '&raquo';
    $config['next_tag_open'] = '<li class="page-item">';
    $config['next_tag_close'] = '</li>';
    $config['last_tag_open'] = '<li class="page-item">';
    $config['last_tag_close'] = '</li>';
    $config['cur_tag_open'] = '<li class="page-item active"><a href="#" class="page-link">';
    $config['cur_tag_close'] = '<span class="sr-only">(current)</span></a></li>';
    $config['num_tag_open'] = '<li class="page-item">';
    $config['num_tag_close'] = '</li>';
    $config['use_page_numbers'] = false;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0; //default starting point for limits 
    $data['links'] = $this->pagination->create_links();
    $data['users'] = $this->auth_mdl->getAll($config['per_page'], $page, $searchkey);
    $data['divisions'] = $this->db->get('divisions')->result();
    $data['module'] = "auth";
    $data['title'] = "User Management";
    $data['uptitle'] = "User Management";
    render("users/add_users", $data);
  }

  public function fetch_users_ajax()
  {
      $searchkey = $this->input->get('search');
      $group_id = $this->input->get('group_id');
      $status = $this->input->get('status');
      $page = $this->input->get('page') ? (int)$this->input->get('page') : 1;
      $pageSize = $this->input->get('pageSize') ? (int)$this->input->get('pageSize') : 25;
      
      // Calculate start position for pagination
      $start = ($page - 1) * $pageSize;
      
      // Build filters
      $filters = [];
      if (!empty($searchkey)) $filters['search'] = $searchkey;
      if (!empty($group_id)) $filters['group_id'] = $group_id;
      if ($status !== '' && $status !== null) $filters['status'] = $status;
      
      try {
          // Get total count for pagination
          $totalUsers = $this->auth_mdl->countFilteredUsers($filters);
          
          // Get paginated users
          $users = $this->auth_mdl->getAllFiltered($filters, $pageSize, $start);
      $usergroups = Modules::run("permissions/getUserGroups");
          
          // Set proper JSON content type header
          header('Content-Type: application/json; charset=utf-8');
  
      echo json_encode([
          'users' => $users,
              'usergroups' => $usergroups,
              'totalUsers' => $totalUsers,
              'currentPage' => $page,
              'pageSize' => $pageSize,
              'totalPages' => ceil($totalUsers / $pageSize)
          ]);
      } catch (Exception $e) {
          // Return error response
          http_response_code(500);
          header('Content-Type: application/json; charset=utf-8');
          echo json_encode([
              'error' => 'Database error occurred',
              'message' => $e->getMessage()
          ]);
      }
  }
  
  public function export_users_excel()
  {
      // Get filter parameters
      $search = $this->input->get('search');
      $group_id = $this->input->get('group_id');
      $status = $this->input->get('status');
      
      // Build filters
      $filters = [];
      if (!empty($search)) $filters['search'] = $search;
      if (!empty($group_id)) $filters['group_id'] = $group_id;
      if ($status !== '' && $status !== null) $filters['status'] = $status;
      
      try {
          // Get filtered users
          $users = $this->auth_mdl->getAllFiltered($filters);
      
      // Set filename
      $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
      
      // Set headers for download
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="' . $filename . '"');
      header('Cache-Control: max-age=0');
      
      // Create output stream
      $output = fopen('php://output', 'w');
      
      // Add BOM for UTF-8
      fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
      
      // CSV Headers
      $headers = [
          'User ID',
          'Staff ID', 
          'Full Name',
          'Work Email',
          'Phone Number',
          'User Group',
          'Status',
          'Division',
          'Job Title',
          'Created Date'
      ];
      
      // Write headers
      fputcsv($output, $headers);
      
      // Write data rows
      foreach ($users as $user) {
          $row = [
              $user->user_id ?? 'N/A',
              $user->staff_id ?? 'N/A',
              $user->name ?? 'N/A',
              $user->work_email ?? 'N/A',
              $user->tel_1 ?? 'N/A',
              $user->group_name ?? 'N/A',
              ($user->status == 1) ? 'Active' : 'Inactive',
              $user->division_name ?? 'N/A',
              $user->job_name ?? 'N/A',
              isset($user->created_at) ? date('Y-m-d', strtotime($user->created_at)) : 'N/A'
          ];
          
          fputcsv($output, $row);
      }
      
      // Close output stream
      fclose($output);
      exit;
      } catch (Exception $e) {
          // Return error response
          http_response_code(500);
          echo 'Error exporting users: ' . $e->getMessage();
      }
  }
  
  public function refreshCSRF()
  {
      $response = [
          'csrf_token' => $this->security->get_csrf_hash()
      ];
      
      header('Content-Type: application/json');
      echo json_encode($response);
  }
  


  public function logs()
  {
    $searchkey = $this->input->get();
    if (empty($searchkey)) {
      $searchkey = "";
    }
    $this->load->library('pagination');
    $config = array();
    $config['base_url'] = base_url() . "auth/logs";
    $config['total_rows'] = $this->auth_mdl->count_logs($searchkey);
    $config['per_page'] = 50; //records per page
    $config['uri_segment'] = 3; //segment in url  
    //pagination links styling
    $config['full_tag_open'] = '<ul class="pagination">';
    $config['full_tag_close'] = '</ul>';
    $config['attributes'] = ['class' => 'page-link'];
    $config['first_link'] = false;
    $config['last_link'] = false;
    $config['first_tag_open'] = '<li class="page-item">';
    $config['first_tag_close'] = '</li>';
    $config['prev_link'] = '&laquo';
    $config['prev_tag_open'] = '<li class="page-item">';
    $config['prev_tag_close'] = '</li>';
    $config['next_link'] = '&raquo';
    $config['next_tag_open'] = '<li class="page-item">';
    $config['next_tag_close'] = '</li>';
    $config['last_tag_open'] = '<li class="page-item">';
    $config['last_tag_close'] = '</li>';
    $config['cur_tag_open'] = '<li class="page-item active"><a href="#" class="page-link">';
    $config['cur_tag_close'] = '<span class="sr-only">(current)</span></a></li>';
    $config['num_tag_open'] = '<li class="page-item">';
    $config['num_tag_close'] = '</li>';
    $config['use_page_numbers'] = false;
    $this->pagination->initialize($config);
    $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0; //default starting point for limits 
    $data['links'] = $this->pagination->create_links();
    $data['logs'] = $this->auth_mdl->get_logs($searchkey, $config['per_page'], $page);

   // dd($this->db->last_query());
    $data['module'] = "auth";
    $data['title'] = "User Access Logs";
    $data['uptitle'] = "User Access Logs";
    render("users/user_logs", $data);
  }
  public function addUser()
  {
    
    $postdata = $this->input->post();
    $res = $this->auth_mdl->addUser($postdata);
    echo $res;
  }
  public function contract_info($staff_id){
    $current_contract = current_contract($staff_id);
    $this->db->where('staff_contract_id',$current_contract);
    $this->db->join('jobs', 'staff_contracts.job_id=jobs.job_id');
    $this->db->join('staff', 'staff.staff_id=staff_contracts.staff_id');
    $this->db->join('jobs_acting', 'staff_contracts.job_acting_id=jobs_acting.job_acting_id');
    $data=  $this->db->get('staff_contracts')->row();
    return $data;
  }
  public function updateUser()
  {
    
    $postdata = $this->input->post();
    
      $res = $this->auth_mdl->updateUser($postdata);
    echo json_encode(['message' => $res]);
    //no photo
  }

  public function acdc_users($job=FALSE){
  $final=array();
  $staffs =  $this->db->query("SELECT staff.*, staff_contracts.division_id,staff_contracts.staff_contract_id from staff join staff_contracts on staff.staff_id=staff_contracts.staff_id where work_email!='' and staff_contracts.status_id in (1,2,7) and staff.staff_id not in (SELECT DISTINCT auth_staff_id from user)")->result();
    foreach ($staffs as $staff):
      $users['name'] = $staff->lname . ' ' . $staff->fname;
      $users['status'] = 1;
      $users['auth_staff_id'] = $staff->staff_id;
      $users['password'] =$this->argonhash->make(setting()->default_password);
      $users['role'] = 17;
      $this->db->replace('user', $users);
    endforeach;
     $accts = $this->db->affected_rows();
   

    $msg = array(
      'msg' => $accts .'Staff Accounts Created .',
      'type' => 'info'
    );
    Modules::run('utility/setFlash', $msg);
    if (!$job) {
      redirect('auth/users');
    }
    


  }

  public function changePass()
{
    
    $postdata = $this->input->post();

    $res = $this->auth_mdl->changePass($postdata);

    // Set flash message based on result
    if ($res) {
        Modules::run('utility/setFlash', [
            'msg'  => $res,
            'type' => 'info'
        ]);
    } 

    redirect('auth/change_password');
}


  public function change_password(){
    $data['title'] = "Change Password";
    $data['module'] = "auth";
    render('users/change_pass', $data);
  }
  public function resetPass()
  {
    $postdata = $this->input->post();
    $res = $this->auth_mdl->resetPass($postdata);
    echo json_encode(['message' => $res]);
  }
  public function blockUser()
  {
    $postdata = $this->input->post();
    $res = $this->auth_mdl->blockUser($postdata);
    echo json_encode(['message' => $res]);
  }
  public function unblockUser()
  {
    $postdata = $this->input->post();
    $res = $this->auth_mdl->unblockUser($postdata);
    echo json_encode(['message' => $res]);
  }
  public function update_profile()
  {
    
    $data = $this->input->post();
    $is_error = false;
    // Load the Upload library
    $this->load->library('upload');
    // For each get the file name and upload it
    // Get the cover
    $photo = $_FILES['photo'];
    $signature = $_FILES['signature'];
    //passport
    if (!empty($photo['name'])) {
      // Chnage the file name to cover with the extension and timestamp
      $photo['name'] = str_replace(' ', '_', $data['name']) . time() . pathinfo($photo['name'], PATHINFO_EXTENSION);
      $config['upload_path']   = './uploads/staff/';
      $config['allowed_types'] = 'gif|jpg|png|jpeg';
      $config['file_name']     = $photo['name'];

      $this->upload->initialize($config);
      // If the upload fails, set the error message
      if (!$this->upload->do_upload('photo')) {
        $this->session->set_flashdata('error', $this->upload->display_errors());
        $is_error = true;
      } else {
        // If the upload is successful, get the file name
        $photo = $this->upload->data('file_name');
        $data['photo'] = $photo;
        $this->session->userdata('user')->photo = $photo;
      }
    }

    //passport
    if (!empty($signature['name'])) {
      // Chnage the file name to cover with the extension and timestamp
      $signature['name'] = str_replace(' ', '_', $data['name']) . time() . pathinfo($signature['name'], PATHINFO_EXTENSION);
      $config['upload_path']   = './uploads/staff/signature/';
      $config['allowed_types'] = 'gif|jpg|png|jpeg';
      $config['file_name']     = $signature['name'];

      $this->upload->initialize($config);
      // If the upload fails, set the error message
      if (!$this->upload->do_upload('signature')) {
        $this->session->set_flashdata('error', $this->upload->display_errors());
        $is_error = true;
        //dd($this->upload->display_errors());
      } else {
        // If the upload is successful, get the file name
        $signature = $this->upload->data('file_name');
        $data['signature'] = $signature;
        $this->session->userdata('user')->signature = $signature;
      }
    }
  


    $this->session->userdata('user')->langauge = $data['langauge'];
    $res = $this->auth_mdl->updateProfile($data);

    if ($res) {
      $msg = array(
        'msg' => $res,
        'type' => 'success'
      );
      Modules::run('utility/setFlash', $msg);
      redirect('auth/profile');
    } else {
      $msg = array(
        'msg' => $res,
        'type' => 'error'
      );
      redirect('auth/profile');
    }
  }
  public function photoMark($imagepath)
  {
    $config['image_library'] = 'gd2';
    $config['source_image'] = $imagepath;
    //$config['wm_text'] = ' Uganda';
    $config['wm_type'] = 'overlay';
    $config['wm_overlay_path'] = './assets/images/daswhite.png';
    //$config['wm_font_color'] = 'ffffff';
    $config['wm_opacity'] = 40;
    $config['wm_vrt_alignment'] = 'bottom';
    $config['wm_hor_alignment'] = 'left';
    //$config['wm_padding'] = '50';
    $this->load->library('image_lib');
    $this->image_lib->initialize($config);
    $this->image_lib->watermark();
  }
  //permissions management

  /**
   * Callback handler for Microsoft OAuth and message/webhook notifications
   * Handles both authentication callbacks and message notifications
   * Works with both localhost and production URLs
   */
  public function message_callback()
  {
      // Set response headers for JSON
      header('Content-Type: application/json');
      
      // Get the request method
      $method = $this->input->server('REQUEST_METHOD');
      
      // Handle GET requests (OAuth authorization callback)
      if ($method === 'GET') {
          return $this->handle_oauth_callback();
      }
      
      // Handle POST requests (Webhook notifications from Microsoft Graph)
      if ($method === 'POST') {
          return $this->handle_webhook_notification();
      }
      
      // Handle validation requests (for webhook subscription)
      if ($method === 'OPTIONS') {
          // CORS preflight or webhook validation
          header('Access-Control-Allow-Origin: *');
          header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
          header('Access-Control-Allow-Headers: Content-Type, Authorization');
          http_response_code(200);
          exit;
      }
      
      // Unsupported method
      http_response_code(405);
      echo json_encode(['error' => 'Method not allowed']);
      exit;
  }

  /**
   * Handle OAuth authorization callback
   */
  private function handle_oauth_callback()
  {
      // Check for authorization code
      $code = $this->input->get('code');
      $error = $this->input->get('error');
      $state = $this->input->get('state');
      
      // Handle OAuth errors
      if ($error) {
          $error_description = $this->input->get('error_description');
          log_message('error', 'OAuth callback error: ' . $error . ' - ' . $error_description);
          
          http_response_code(400);
          echo json_encode([
              'error' => $error,
              'error_description' => $error_description
          ]);
          exit;
      }
      
      // Validate authorization code
      if (!$code) {
          http_response_code(400);
          echo json_encode(['error' => 'Missing authorization code']);
          exit;
      }
      
      // Exchange authorization code for access token
      $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";
      
      // Determine redirect URI based on environment
      $redirect_uri = $this->get_callback_url();
      
      $post_data = [
          'client_id'     => $this->client_id,
          'client_secret' => $this->client_secret,
          'code'          => $code,
          'redirect_uri'  => $redirect_uri,
          'grant_type'    => 'authorization_code',
          'scope'         => 'https://outlook.office365.com/.default offline_access'
      ];
      
      $headers = ['Content-Type: application/x-www-form-urlencoded'];
      
      $response = curl_send_post($token_url, $post_data, $headers);
      
      if (isset($response->access_token)) {
          // Store token securely (you may want to store this in database or cache)
          log_message('info', 'OAuth token obtained successfully via message_callback');
          
          // Return success response
          http_response_code(200);
          echo json_encode([
              'success' => true,
              'message' => 'Authorization successful',
              'token_received' => true
          ]);
          exit;
      } else {
          log_message('error', 'Failed to obtain OAuth token: ' . json_encode($response));
          
          http_response_code(500);
          echo json_encode([
              'error' => 'Failed to obtain access token',
              'details' => $response
          ]);
          exit;
      }
  }

  /**
   * Handle webhook notifications from Microsoft Graph
   */
  private function handle_webhook_notification()
  {
      // Get the raw input for signature validation
      $payload = file_get_contents('php://input');
      $headers = getallheaders();
      
      // Log the incoming webhook for debugging
      log_message('info', 'Webhook notification received: ' . $payload);
      
      // Check for validation token (Microsoft Graph sends this during subscription)
      // Can come as GET parameter or in POST body
      $validation_token = $this->input->get('validationToken');
      if (!$validation_token && !empty($payload)) {
          $data = json_decode($payload, true);
          $validation_token = $data['validationToken'] ?? null;
      }
      
      if ($validation_token) {
          // Return validation token immediately (required by Microsoft)
          // Must return within 10 seconds and be plain text
          header('Content-Type: text/plain');
          echo $validation_token;
          exit;
      }
      
      // Parse notification data
      $data = json_decode($payload, true);
      
      if (!$data) {
          http_response_code(400);
          echo json_encode(['error' => 'Invalid JSON payload']);
          exit;
      }
      
      // Handle different notification types
      if (isset($data['value'])) {
          // This is a notification from Microsoft Graph
          foreach ($data['value'] as $notification) {
              $this->process_notification($notification);
          }
          
          // Return 202 Accepted (required by Microsoft)
          http_response_code(202);
          echo json_encode(['success' => true, 'message' => 'Notification processed']);
          exit;
      }
      
      // Handle other notification formats
      $this->process_notification($data);
      
      http_response_code(200);
      echo json_encode(['success' => true, 'message' => 'Notification received']);
      exit;
  }

  /**
   * Process individual notification
   */
  private function process_notification($notification)
  {
      // Log the notification
      log_message('info', 'Processing notification: ' . json_encode($notification));
      
      // Handle different notification types
      if (isset($notification['resource'])) {
          $resource = $notification['resource'];
          $change_type = $notification['changeType'] ?? 'unknown';
          
          // Handle email/message notifications
          if (strpos($resource, '/messages') !== false || strpos($resource, '/Mail') !== false) {
              $this->handle_email_notification($resource, $change_type);
          }
          
          // Handle subscription notifications
          if (isset($notification['subscriptionId'])) {
              $this->handle_subscription_notification($notification);
          }
      }
      
      // You can add more notification processing logic here
  }

  /**
   * Handle email-related notifications
   */
  private function handle_email_notification($resource, $changeType)
  {
      log_message('info', "Email notification: {$changeType} - {$resource}");
      
      // Process the email notification
      // You can fetch the email details using Microsoft Graph API if needed
      // Example: GET https://graph.microsoft.com/v1.0/{resource}
      
      // Add your email processing logic here
      // For example, update email_notifications table, trigger email processing, etc.
  }

  /**
   * Handle subscription-related notifications
   */
  private function handle_subscription_notification($notification)
  {
      $subscription_id = $notification['subscriptionId'] ?? null;
      $lifecycle_event = $notification['lifecycleEvent'] ?? null;
      
      log_message('info', "Subscription notification: {$subscription_id} - {$lifecycle_event}");
      
      // Handle subscription lifecycle events
      // Example: reauthorize, revalidate, or delete subscription
  }

  /**
   * Get the callback URL based on environment
   * Works for both localhost and production
   */
  private function get_callback_url()
  {
      // Check if we're in production or localhost
      $base_url = base_url();
      $host = $_SERVER['HTTP_HOST'] ?? '';
      
      // Check for localhost or local development
      if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
          // Use localhost callback URL
          return $base_url . 'auth/message_callback';
      }
      
      // Production URL - use the production callback URL
      // You can also use environment variable for production URL
      $production_url = $_ENV['PRODUCTION_CALLBACK_URL'] ?? $base_url;
      return rtrim($production_url, '/') . '/auth/message_callback';
  }

}
