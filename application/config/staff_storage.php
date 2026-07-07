<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Host-side upload paths for the Staff ecosystem.
 *
 * Set STAFF_DATA_ROOT or STAFF_PORTAL_UPLOADS_ROOT in .env for production.
 * See docs/STORAGE.md and scripts/storage/migrate-all.sh.
 */
$config['uploads_root'] = (static function (): string {
    if (class_exists(\Staff\Shared\StaffStorage::class)) {
        return \Staff\Shared\StaffStorage::ciUploadsRoot(defined('FCPATH') ? rtrim(FCPATH, '/\\') : null);
    }

    $explicit = trim((string) (getenv('STAFF_PORTAL_UPLOADS_ROOT') ?: ''));
    if ($explicit !== '') {
        return rtrim($explicit, '/\\');
    }

    return rtrim(FCPATH, '/\\').DIRECTORY_SEPARATOR.'uploads';
})();
