<?php

use Staff\Shared\StaffStorage;

/**
 * Paths shared with the CodeIgniter staff portal (sibling app).
 * Staff files live under uploads/staff/ (photos), uploads/staff/signature/, etc.
 * CI3 blocks direct /uploads/staff/*; APM serves photos via staff-uploads/photo (session/JWT).
 *
 * Production: set STAFF_DATA_ROOT or STAFF_PORTAL_UPLOADS_ROOT — see docs/STORAGE.md
 */
return [
    'uploads_root' => StaffStorage::ciUploadsRoot(dirname(base_path())),
];
