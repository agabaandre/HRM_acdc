<?php

declare(strict_types=1);

/**
 * After Apache rewrites /staff/{app} → /staff/modules/{app}, SCRIPT_NAME / PHP_SELF
 * still contain /modules/. Laravel uses those to compute the app base path, so routes
 * 404 unless we map back to the public URL prefixes.
 */
(static function (): void {
    $replacements = [
        '#^/staff/modules/apm(?:/public)?#' => '/staff/apm',
        '#^/staff/modules/finance(?:/public)?#' => '/staff/finance',
        // Helpdesk Laravel API is mounted at /staff/helpdesk/backend (not /staff/helpdesk).
        '#^/staff/modules/helpdesk/backend(?:/public)?#' => '/staff/helpdesk/backend',
        '#^/staff/modules/staff-portal/backend(?:/public)?#' => '/staff/backend',
    ];

    foreach (['SCRIPT_NAME', 'PHP_SELF'] as $key) {
        $val = $_SERVER[$key] ?? '';
        if ($val === '') {
            continue;
        }
        foreach ($replacements as $pattern => $publicRoot) {
            if (preg_match($pattern, $val) === 1) {
                $_SERVER[$key] = preg_replace($pattern, $publicRoot, $val, 1) ?? $val;
                break;
            }
        }
    }
})();
