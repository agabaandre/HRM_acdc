<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function log_user_route_access()
{
    $CI =& get_instance();

    // Skip if user not logged in
    $user = $CI->session->userdata('user');
    if (!$user) return;

    $controller = $CI->router->fetch_class();
    $method = $CI->router->fetch_method();
    $uri = uri_string();

    $log_message = "Accessed route: {$controller}/{$method} [URI: {$uri}]";

    // Call your existing helper
    if (!function_exists('log_user_action')) {
        $CI->load->helper('custom'); // Load your helper if not autoloaded
    }

    log_user_action($log_message);
}
