<?php

declare(strict_types=1);

/**
 * Production-safe Activity model integrity check (no Pest / PHPUnit required).
 *
 *   cd apm && php scripts/validate-activity-model.php
 *   composer validate-activity-model
 */

$apmRoot = dirname(__DIR__);
$appRoot = $apmRoot.'/app';

require $apmRoot.'/vendor/autoload.php';

use App\Support\ActivityModelIntegrityValidator;

$errors = ActivityModelIntegrityValidator::errors($appRoot);

if ($errors !== []) {
    fwrite(STDERR, "Activity model integrity check FAILED:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    exit(1);
}

// Autoload must not double-declare the class.
if (! class_exists(\App\Models\Activity::class, false)) {
    class_exists(\App\Models\Activity::class);
}

echo "Activity model integrity check passed.\n";
exit(0);
