<?php

declare(strict_types=1);

/**
 * After Apache rewrites /{webRoot}/{app} → /{webRoot}/modules/{app}, SCRIPT_NAME /
 * PHP_SELF still contain /modules/. Laravel uses those to compute the app base
 * path, so routes 404 unless we map back to the public URL prefixes.
 *
 * Supports any Alias folder (staff, demo_staff, cbp, cbpdemo, …).
 */
(static function (): void {
    // Capture group 1 = public web-root segment (staff, demo_staff, …).
    $replacements = [
        '#^/([^/]+)/modules/apm(?:/public)?#' => '/$1/apm',
        '#^/([^/]+)/modules/finance(?:/public)?#' => '/$1/finance',
        // Helpdesk Laravel API is mounted at /{root}/helpdesk/backend.
        '#^/([^/]+)/modules/helpdesk/backend(?:/public)?#' => '/$1/helpdesk/backend',
        '#^/([^/]+)/modules/staff-portal/backend(?:/public)?#' => '/$1/backend',
        // Legacy physical path …/staff-portal/backend before modules/ layout.
        '#^/([^/]+)/staff-portal/backend(?:/public)?#' => '/$1/backend',
        // DocumentRoot = deploy folder (no /{webRoot} prefix in SCRIPT_NAME).
        '#^/modules/staff-portal/backend(?:/public)?#' => '/backend',
        '#^/modules/helpdesk/backend(?:/public)?#' => '/helpdesk/backend',
        '#^/modules/apm(?:/public)?#' => '/apm',
        '#^/modules/finance(?:/public)?#' => '/finance',
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
