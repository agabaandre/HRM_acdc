<?php

use Staff\Shared\StaffStorage;

// env() here so config:cache bakes host paths from .env (StaffStorage uses getenv, which
// is empty once configuration is cached).
$staffPublicRoot = env('STAFF_PORTAL_MODULE_FILES_ROOT');
if (! is_string($staffPublicRoot) || $staffPublicRoot === '') {
    $dataRoot = env('STAFF_DATA_ROOT');
    if (is_string($dataRoot) && $dataRoot !== '') {
        $staffPublicRoot = rtrim($dataRoot, '/\\').'/staff-portal';
    } else {
        $staffPublicRoot = StaffStorage::staffPortalModuleRoot(base_path());
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => $staffPublicRoot,
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => $staffPublicRoot,
    ],

];
