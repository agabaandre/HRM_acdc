<?php

/**
 * Paths shared with the CodeIgniter staff portal (sibling app).
 * Signature files live under uploads/staff/signature/ and are no longer web-public (secure_upload).
 */
return [
    'uploads_root' => env(
        'STAFF_PORTAL_UPLOADS_ROOT',
        dirname(base_path()) . DIRECTORY_SEPARATOR . 'uploads'
    ),
];
