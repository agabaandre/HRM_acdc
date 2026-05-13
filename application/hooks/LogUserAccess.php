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

    if (!function_exists('log_user_action')) {
        $CI->load->helper('custom');
    }

    $httpMethod = strtoupper((string) ($CI->input->server('REQUEST_METHOD') ?: 'GET'));
    $context = array(
        'http_method' => $httpMethod,
        'request_uri' => $uri,
    );
    if ($controller === 'auth' && $method === 'logs' && user_logs_audit_columns_active()) {
        if (isset($CI->config)) {
            $CI->config->load('audit_log', true);
            $iso = $CI->config->item('staff_audit_iso', 'audit_log');
            if (is_array($iso) && !empty($iso['log_audit_repository_access'])) {
                $context['event_type'] = 'audit_repository';
            }
        }
    }
    if (in_array($httpMethod, array('POST', 'PUT', 'PATCH', 'DELETE'), true) && function_exists('staff_user_audit_sanitize_payload')) {
        $payload = staff_user_audit_sanitize_payload();
        if ($payload !== array()) {
            $context['mutation_payload'] = $payload;
        }
    }

    log_user_action($log_message, $context);
}
