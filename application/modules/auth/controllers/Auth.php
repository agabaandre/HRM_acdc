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
    // IMPORTANT: Prevent new session creation after logout
    // CodeIgniter may create a new session when this page loads
    // If there's a new session with no user data, delete it immediately
    $sessionDriver = $this->config->item('sess_driver');
    $sessionTable = $this->config->item('sess_save_path');
    $cookieName = $this->config->item('sess_cookie_name');
    $sessionId = $this->input->cookie($cookieName);
    
    // Check if this is a newly created empty session (no user data)
    $user = $this->session->userdata('user');
    if ($sessionDriver === 'database' && !empty($sessionTable) && !empty($sessionId)) {
        // Check if session exists in database
        $sessionExists = $this->db->query("SELECT id FROM `{$sessionTable}` WHERE id = ?", [$sessionId])->row();
        
        // If session exists but has no user data, it's a newly created empty session - delete it
        if ($sessionExists && (empty($user) || !isset($user->staff_id))) {
            // This is likely a session created after logout - delete it
            $this->db->query("DELETE FROM `{$sessionTable}` WHERE id = ?", [$sessionId]);
            setcookie($cookieName, '', time() - 3600, '/');
            log_message('debug', 'Auth index: Deleted newly created empty session - ID: ' . $sessionId);
            $this->session->sess_destroy();
        }
    }
    
    // Check if user is already logged in
    $user = $this->session->userdata('user');
    if (!empty($user) && isset($user->staff_id)) {
      // User is already logged in, redirect to home
      redirect('home/index');
      return;
    }

    $apm_base = $this->config->item('apm_base_url');
    if (empty($apm_base)) {
      $apm_base = rtrim(base_url(), '/') . '/apm';
    }
    $apm_base = rtrim($apm_base, '/');
    $this->load->view("login/login", [
      'apm_base_url' => $apm_base,
    ]);
  }

  public function login() {
    // Check if user is already logged in
    $user = $this->session->userdata('user');
    if (!empty($user) && isset($user->staff_id)) {
      // User is already logged in, redirect to home
      redirect('home/index');
      return;
    }

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
    
    // Get current user from session
    $staff = $this->session->userdata('user');
    
    // Get comprehensive contract information
    $this->load->model('staff/staff_mdl');
    $contract = $this->staff_mdl->get_latest_contracts($staff->staff_id);
    
    // Get supervisor information if available
    $supervisor = null;
    if (!empty($contract) && !empty($contract->first_supervisor)) {
      $this->db->select('staff_id, title, fname, lname, work_email, photo');
      $this->db->where('staff_id', $contract->first_supervisor);
      $supervisor = $this->db->get('staff')->row();
    }
    
    // Get second supervisor if available
    $second_supervisor = null;
    if (!empty($contract) && !empty($contract->second_supervisor)) {
      $this->db->select('staff_id, title, fname, lname, work_email, photo');
      $this->db->where('staff_id', $contract->second_supervisor);
      $second_supervisor = $this->db->get('staff')->row();
    }
    
    // Get directorate information if available
    $directorate = null;
    if (!empty($contract) && !empty($contract->directorate_id)) {
      $this->db->where('directorate_id', $contract->directorate_id);
      $directorate = $this->db->get('directorates')->row();
    }
    
    // Pass data to view
    $data['contract'] = $contract;
    $data['supervisor'] = $supervisor;
    $data['second_supervisor'] = $second_supervisor;
    $data['directorate'] = $directorate;

    render("users/profile", $data);

  }
  public function logout()
  {
    // Get session configuration
    $sessionDriver = $this->config->item('sess_driver');
    $sessionTable = $this->config->item('sess_save_path');
    $cookieName = $this->config->item('sess_cookie_name'); // Should be 'africacdc_cbp_session'
    
    // Get session ID from CodeIgniter's session library (most reliable method)
    // CodeIgniter stores session ID internally and provides it via __get('session_id')
    $sessionId = $this->session->session_id;
    
    // Fallback: Get from cookie if session ID not available
    if (empty($sessionId)) {
        $sessionId = $this->input->cookie($cookieName);
    }
    
    // Fallback: Get from PHP session
    if (empty($sessionId) && session_status() === PHP_SESSION_ACTIVE) {
        $sessionId = session_id();
    }
    
    log_message('debug', 'Logout: Session ID: ' . ($sessionId ?: 'NOT FOUND'));
   
    // Also destroy Laravel APM session if it exists (do this FIRST before destroying CI session)
   // Get all cookies from the request
   $allCookies = $this->input->server('HTTP_COOKIE');
   
   // Try to invalidate Laravel session via API call if APM is accessible
   // This must be done BEFORE clearing CI session to pass the Laravel session cookie
   try {
       $apmBaseUrl = base_url('apm');
       $ch = curl_init($apmBaseUrl . '/api/logout');
       curl_setopt_array($ch, [
           CURLOPT_RETURNTRANSFER => true,
           CURLOPT_FOLLOWLOCATION => true,
           CURLOPT_TIMEOUT => 5,
           CURLOPT_POST => true,
           CURLOPT_POSTFIELDS => json_encode(['destroy_session' => true]),
           CURLOPT_HTTPHEADER => [
               'Content-Type: application/json',
               'X-Requested-With: XMLHttpRequest',
               'Accept: application/json'
           ],
           CURLOPT_COOKIE => $allCookies, // Pass all cookies including Laravel session
           CURLOPT_SSL_VERIFYPEER => false,
           CURLOPT_SSL_VERIFYHOST => false,
       ]);
       
       $response = curl_exec($ch);
       $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
       curl_close($ch);
       
       // Log if there was an issue
       if ($httpCode >= 400) {
           log_message('error', 'Laravel session logout API returned error: ' . $httpCode);
       }
   } catch (Exception $e) {
       // Log error but don't fail logout
       log_message('error', 'Failed to call Laravel logout API: ' . $e->getMessage());
   }
    
    // Get user data before clearing (for cleanup)
    $user = $this->session->userdata('user');
    $userId = isset($user->user_id) ? $user->user_id : null;
    
    // IMPORTANT: For database sessions, we MUST delete from database BEFORE calling sess_destroy()
    // CodeIgniter's destroy() method requires a lock, so we'll delete directly using raw SQL
    if ($sessionDriver === 'database' && !empty($sessionTable) && !empty($sessionId)) {
        // Get database connection directly to ensure we can delete
        $mysqli = $this->db->conn_id;
        
        // Escape the table name and session ID for safety
        $tableName = $this->db->escape_str($sessionTable);
        $escapedSessionId = $this->db->escape_str($sessionId);
        
        // First, verify the session exists
        $checkSql = "SELECT id, ip_address, timestamp FROM `{$tableName}` WHERE id = '{$escapedSessionId}'";
        $checkResult = $this->db->query($checkSql);
        //dd($checkResult->result());
        $sessionExists = $checkResult->row();
        
        if ($sessionExists) {
            log_message('debug', 'Logout: Found session in database - ID: ' . $sessionId . ' | IP: ' . ($sessionExists->ip_address ?? 'N/A'));
            
            // Delete using direct SQL query - bypass CodeIgniter's query builder to avoid lock issues
            $deleteSql = "DELETE FROM `{$tableName}` WHERE id = '{$escapedSessionId}'";
            $deleteResult = $this->db->query($deleteSql);
            $deletedRows = $this->db->affected_rows();
            
            log_message('debug', 'Logout: DELETE query executed - ID: ' . $sessionId . ' | Rows affected: ' . $deletedRows);
            
            if ($deletedRows == 0) {
                // Try using mysqli directly as fallback
                if ($mysqli && is_object($mysqli)) {
                    $stmt = $mysqli->prepare("DELETE FROM `{$tableName}` WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("s", $sessionId);
                        $stmt->execute();
                        $deletedRows = $stmt->affected_rows;
                        $stmt->close();
                        log_message('debug', 'Logout: Direct mysqli delete - ID: ' . $sessionId . ' | Rows affected: ' . $deletedRows);
                    }
                }
                
                if ($deletedRows == 0) {
                    log_message('error', 'Logout: FAILED to delete session - ID: ' . $sessionId . ' | All methods failed');
                }
            }
        } else {
            log_message('debug', 'Logout: Session not found in database - ID: ' . $sessionId);
        }
        
        // Also delete any other sessions for this user (cleanup)
        if ($userId) {
            $escapedUserId = $this->db->escape_str($userId);
            $userSessionsSql = "SELECT id FROM `{$tableName}` WHERE data LIKE '%user_id\";i:{$escapedUserId}%'";
            $userSessionsResult = $this->db->query($userSessionsSql);
            $userSessions = $userSessionsResult->result();
            
            foreach ($userSessions as $sess) {
                $escapedSessId = $this->db->escape_str($sess->id);
                $this->db->query("DELETE FROM `{$tableName}` WHERE id = '{$escapedSessId}'");
                log_message('debug', 'Logout: Deleted user session - ID: ' . $sess->id);
            }
            
            if (count($userSessions) > 0) {
                log_message('debug', 'Logout: Deleted ' . count($userSessions) . ' user sessions for User ID: ' . $userId);
            }
        }
    }
    
    // Clear all session data first
    $this->session->unset_userdata('user');
    $this->session->unset_userdata('original_user');
    $this->session->unset_userdata('impersonation_start');
    
    // Now destroy the session - this will call the handler's destroy() method
    // For database driver, it will try to delete from database (but we've already done it)
    $this->session->sess_destroy();
    
    // Clear PHP session superglobal
    $_SESSION = array();
    
    // IMPORTANT: Delete session from database AGAIN after sess_destroy (safety measure)
    // CodeIgniter's sess_destroy() requires a lock which might not be active, so we delete directly
    if ($sessionDriver === 'database' && !empty($sessionTable) && !empty($sessionId)) {
        $tableName = $this->db->escape_str($sessionTable);
        $escapedSessionId = $this->db->escape_str($sessionId);
        
        // Check if session still exists
        $checkSql = "SELECT id FROM `{$tableName}` WHERE id = '{$escapedSessionId}'";
        $checkResult = $this->db->query($checkSql);
        
        if ($checkResult->num_rows() > 0) {
            // Force delete using direct SQL
            $deleteSql = "DELETE FROM `{$tableName}` WHERE id = '{$escapedSessionId}'";
            $this->db->query($deleteSql);
            $postDeletedRows = $this->db->affected_rows();
            
            log_message('debug', 'Logout: Post-destroy cleanup - Session ID: ' . $sessionId . ' | Rows affected: ' . $postDeletedRows);
            
            // If still not deleted, try mysqli directly
            if ($postDeletedRows == 0) {
                $mysqli = $this->db->conn_id;
                if ($mysqli && is_object($mysqli)) {
                    $stmt = $mysqli->prepare("DELETE FROM `{$tableName}` WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("s", $sessionId);
                        $stmt->execute();
                        $postDeletedRows = $stmt->affected_rows;
                        $stmt->close();
                        log_message('debug', 'Logout: Post-destroy mysqli delete - Rows affected: ' . $postDeletedRows);
                    }
                }
                
                if ($postDeletedRows == 0) {
                    log_message('error', 'Logout: Post-destroy cleanup FAILED - Session ID: ' . $sessionId);
                }
            }
        } else {
            log_message('debug', 'Logout: Post-destroy check - Session already deleted: ' . $sessionId);
        }
    }
    
    // Remove the session cookie using the correct cookie name (africacdc_cbp_session)
    $cookiePath = $this->config->item('cookie_path') ?: '/';
    $cookieDomain = $this->config->item('cookie_domain') ?: '';
    $cookieSecure = $this->config->item('cookie_secure') ?: false;
    $cookieHttpOnly = $this->config->item('cookie_httponly') ?: true;
    
    // Clear the session cookie with correct name
    if (!empty($cookieName)) {
        // Clear with domain
        setcookie($cookieName, '', time() - 42000, $cookiePath, $cookieDomain, $cookieSecure, $cookieHttpOnly);
        // Clear with empty domain
        setcookie($cookieName, '', time() - 42000, $cookiePath, '', $cookieSecure, $cookieHttpOnly);
        // Clear with root path
        setcookie($cookieName, '', time() - 42000, '/', $cookieDomain, $cookieSecure, $cookieHttpOnly);
        setcookie($cookieName, '', time() - 42000, '/', '', $cookieSecure, $cookieHttpOnly);
    }
    
    // Also clear using PHP session cookie params as backup
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie($cookieName, '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
        setcookie($cookieName, '', time() - 42000,
            $params["path"], '',
            $params["secure"], $params["httponly"]
        );
    }
   
   // Also clear Laravel session cookie manually as backup
   // Try different cookie names and paths
   $laravelCookieNames = ['laravel_session', 'laravel_session_' . md5(base_url())];
   $cookiePaths = ['/apm', '/'];
   
   foreach ($laravelCookieNames as $laravelCookieName) {
       foreach ($cookiePaths as $cookiePath) {
           // Clear cookie for current domain
           setcookie($laravelCookieName, '', time() - 3600, $cookiePath, '', isset($_SERVER['HTTPS']), true);
           // Also try with domain
           if (!empty($_SERVER['HTTP_HOST'])) {
               setcookie($laravelCookieName, '', time() - 3600, $cookiePath, $_SERVER['HTTP_HOST'], isset($_SERVER['HTTPS']), true);
           }
           // Try with empty domain
           setcookie($laravelCookieName, '', time() - 3600, $cookiePath, '', isset($_SERVER['HTTPS']), true);
       }
   }
   
   // Prevent browser caching (important!)
   header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
   header("Pragma: no-cache");
   header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
   
   // Force session to close and prevent write
   session_write_close();
   
   // IMPORTANT: Use direct header redirect instead of CodeIgniter's redirect()
   // This prevents CodeIgniter from initializing a new session on the redirect
   // CodeIgniter's redirect() function triggers session initialization
   $loginUrl = base_url('auth');
   
   // Log logout completion
   log_message('debug', 'Logout: Completed - Redirecting to login without creating new session');
   
   // Use direct header redirect to prevent session creation
   header("Location: " . $loginUrl);
   exit(); // Exit immediately to prevent any further code execution
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
    try {
      $postdata = $this->input->post();
      
      if (empty($postdata)) {
        echo json_encode(['message' => 'No data received']);
        return;
      }
      
      $res = $this->auth_mdl->resetPass($postdata);
      echo json_encode(['message' => $res]);
    } catch (Exception $e) {
      log_message('error', 'Reset password error: ' . $e->getMessage());
      echo json_encode(['message' => 'Error resetting password: ' . $e->getMessage()]);
    }
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
    $upload_errors = array();
    $this->load->library('upload');

    $photo = isset($_FILES['photo']) ? $_FILES['photo'] : null;
    $signature = isset($_FILES['signature']) ? $_FILES['signature'] : null;

    $staff_upload_path = FCPATH . 'uploads/staff';
    $signature_upload_path = FCPATH . 'uploads/staff/signature';
    if (!is_dir($staff_upload_path)) {
      @mkdir($staff_upload_path, 0755, true);
    }
    if (!is_dir($signature_upload_path)) {
      @mkdir($signature_upload_path, 0755, true);
    }

    if (!empty($photo['name'])) {
      $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
      $safe_name = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_\-.]/', '', $data['name']));
      $photo_file_name = $safe_name . '_' . time() . '.' . ($ext ? $ext : 'jpg');
      $config = array(
        'upload_path'   => './uploads/staff/',
        'allowed_types' => 'gif|jpg|png|jpeg',
        'file_name'     => $photo_file_name,
        'max_size'      => 1024,
        'overwrite'     => false
      );
      $this->upload->initialize($config);
      if (!$this->upload->do_upload('photo')) {
        $upload_errors[] = 'Profile photo: ' . $this->upload->display_errors('', '');
      } else {
        $data['photo'] = $this->upload->data('file_name');
      }
    }

    if (!empty($signature['name'])) {
      $ext = pathinfo($signature['name'], PATHINFO_EXTENSION);
      $safe_name = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9_\-.]/', '', $data['name']));
      $signature_file_name = $safe_name . '_sig_' . time() . '.' . ($ext ? $ext : 'png');
      $config = array(
        'upload_path'   => './uploads/staff/signature/',
        'allowed_types' => 'gif|jpg|png|jpeg',
        'file_name'     => $signature_file_name,
        'max_size'      => 1024,
        'overwrite'     => false
      );
      $this->upload->initialize($config);
      if (!$this->upload->do_upload('signature')) {
        $upload_errors[] = 'Signature: ' . $this->upload->display_errors('', '');
      } else {
        $data['signature'] = $this->upload->data('file_name');
      }
    }

    $res = $this->auth_mdl->updateProfile($data);

    $user = $this->session->userdata('user');
    if ($user) {
      if (!empty($data['photo'])) {
        $user->photo = $data['photo'];
      }
      if (!empty($data['signature'])) {
        $user->signature = $data['signature'];
      }
      if (isset($data['langauge'])) {
        $user->langauge = $data['langauge'];
      }
      $this->session->set_userdata('user', $user);
    }

    if (!empty($upload_errors)) {
      $error_text = implode(' ', $upload_errors);
      Modules::run('utility/setFlash', array('msg' => $error_text, 'type' => 'error'));
      redirect('auth/profile');
      return;
    }

    if ($res) {
      Modules::run('utility/setFlash', array('msg' => is_string($res) ? $res : 'Profile updated successfully.', 'type' => 'success'));
    } else {
      Modules::run('utility/setFlash', array('msg' => 'Profile update failed. Please try again.', 'type' => 'error'));
    }
    redirect('auth/profile');
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
