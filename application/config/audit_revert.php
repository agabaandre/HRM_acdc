<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Revert (staff user_logs): only tables/columns listed here can be restored from old_values JSON.
| Extend carefully — reverting wrong columns can break auth or data integrity.
*/
$config['staff_audit_revert_tables'] = [
    'user' => [
        'pk' => 'user_id',
        'columns' => ['name', 'role', 'status', 'allow_email_login'],
    ],
];
