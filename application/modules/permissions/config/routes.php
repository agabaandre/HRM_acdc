<?php
defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| For the permissions module, we map lowercase URLs to PascalCase controllers
*/

// Map lowercase 'userpermissions' to 'UserPermissions' controller
// In HMVC, routes are relative to the module
$route['userpermissions'] = 'UserPermissions/index';
$route['userpermissions/(:any)'] = 'UserPermissions/$1';

