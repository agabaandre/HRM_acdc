<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| ISO/IEC 27001-oriented staff audit log (user_logs) controls - map in your Statement of Applicability.
|
| - integrity_chain: requires application/sql/add_user_logs_iso_audit_columns.sql; uses GET_LOCK + SHA-256 chain.
| - retention_guidance_days: policy reference only (purge via DBA / scheduled job; not auto-deleted here).
| - log_audit_repository_access: tags auth/logs views as event_type audit_repository (see LogUserAccess hook).
| - revert_permission_id: null = same permission as viewing logs (permission 17 today).
| - prune_get_access_logs_enabled: when true (default), scheduled job may DELETE GET rows from user_logs (see user_logs_prune_get_access in staff jobs schedule; default weekly Tuesday 00:00).
| Operations (outside this app): reliable time (NTP), DB user cannot UPDATE/DELETE user_logs except
|   this application role, backups and retention owned by policy.
*/
$config['staff_audit_iso'] = array(
    'integrity_chain' => true,
    'retention_guidance_days' => 365,
    'log_audit_repository_access' => true,
    'revert_permission_id' => null,
    'prune_get_access_logs_enabled' => true,
);
