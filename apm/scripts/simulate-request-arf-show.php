<?php

declare(strict_types=1);

/**
 * Simulate request-arf show() Activity load path (CLI — same autoload as web).
 *
 *   cd apm && php scripts/simulate-request-arf-show.php 111
 *
 * If this passes but the browser still errors, PHP-FPM OPcache or a different
 * deploy path is serving stale/different code (restart FPM; verify vhost root).
 */

$apmRoot = dirname(__DIR__);
$arfId = (int) ($argv[1] ?? 0);

if ($arfId <= 0) {
    fwrite(STDERR, "Usage: php scripts/simulate-request-arf-show.php <arf_id>\n");
    exit(1);
}

require $apmRoot.'/vendor/autoload.php';

$app = require $apmRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChangeRequest;
use App\Models\RequestARF;

$canonical = realpath($apmRoot.'/app/Models/Activity.php');
echo "Simulating request-arf show for ARF #{$arfId}\n";
echo "Activity.php: ".($canonical ?: 'missing')."\n\n";

try {
    $requestARF = RequestARF::with([
        'approvalTrails.staff',
        'approvalTrails.oicStaff',
        'approvalTrails.approverRole',
        'funder',
        'source',
    ])->find($arfId);

    if (! $requestARF) {
        fwrite(STDERR, "error: ARF #{$arfId} not found in database.\n");
        exit(1);
    }

    $requestARF->load(['staff', 'fundType', 'responsiblePerson', 'source']);
    $sourceModel = $requestARF->getSourceModel();

    if ($sourceModel && $requestARF->model_type === 'App\\Models\\Activity') {
        $sourceModel->load(['matrix.division.divisionHead', 'staff', 'activity_budget']);
    }

    $originatingChangeRequest = ChangeRequest::where('request_arf_id', $requestARF->id)->first();
    if ($originatingChangeRequest && function_exists('parent_based_disclaimer_data')) {
        parent_based_disclaimer_data(
            $requestARF->source_id,
            $requestARF->model_type,
            'arf',
            $requestARF->id,
            $sourceModel,
        );
    }

    $activityIncludes = array_values(array_filter(
        get_included_files(),
        static fn (string $path): bool => basename($path) === 'Activity.php'
    ));

    echo "OK — show load path completed.\n";
    echo 'model_type: '.($requestARF->model_type ?? 'null')."\n";
    echo 'source_id: '.($requestARF->source_id ?? 'null')."\n";
    echo 'Activity.php included '.count($activityIncludes)." time(s)\n";
    foreach ($activityIncludes as $path) {
        echo "  {$path}\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "FAILED: ".$e->getMessage()."\n");
    fwrite(STDERR, $e->getFile().':'.$e->getLine()."\n");
    exit(1);
}
