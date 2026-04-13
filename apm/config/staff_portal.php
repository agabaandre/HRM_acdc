<?php

/**
 * Paths shared with the CodeIgniter staff portal (sibling app).
 * Staff files live under uploads/staff/ (photos), uploads/staff/signature/, etc.
 * CI3 blocks direct /uploads/staff/*; APM serves photos via staff-uploads/photo (session/JWT).
 */
return [
    'uploads_root' => env(
        'STAFF_PORTAL_UPLOADS_ROOT',
        dirname(base_path()) . DIRECTORY_SEPARATOR . 'uploads'
    ),
];
