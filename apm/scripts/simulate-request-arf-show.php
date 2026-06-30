<?php

declare(strict_types=1);

/**
 * Simulate request-arf show() Activity load path (CLI — same autoload as web).
 *
 *   cd apm && php scripts/simulate-request-arf-show.php 111
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
use App\Support\ActivityModelWarmup;

$canonical = realpath($apmRoot.'/app/Models/Activity.php');
echo "Simulating request-arf show for ARF #{$arfId}\n";
echo "Activity.php: ".($canonical ?: 'missing')."\n";
echo 'Activity loaded after bootstrap: '.(class_exists(\App\Models\Activity::class, false) ? 'yes' : 'no')."\n\n";

$step = static function (string $label, callable $fn): void {
    echo "==> {$label}... ";
    $fn();
    echo "OK\n";
};

try {
    $step('ActivityModelWarmup', static function (): void {
        ActivityModelWarmup::run();
    });

    $requestARF = null;
    $step('RequestARF::find (no source eager load)', static function () use ($arfId, &$requestARF): void {
        $requestARF = RequestARF::with([
            'approvalTrails.staff',
            'approvalTrails.oicStaff',
            'approvalTrails.approverRole',
            'funder',
        ])->find($arfId);

        if (! $requestARF) {
            throw new RuntimeException("ARF #{$arfId} not found");
        }
    });

    $step('Load ARF relations', static function () use (&$requestARF): void {
        $requestARF->load(['staff', 'fundType', 'responsiblePerson']);
    });

    $sourceModel = null;
    $step('getSourceModel()', static function () use (&$requestARF, &$sourceModel): void {
        $rawType = (string) ($requestARF->model_type ?? '');
        echo "\n    model_type=".json_encode($rawType).' normalized='
            .json_encode(\App\Support\ArfSourceModelResolver::normalizeModelType($rawType))
            .' activity_loaded='.(class_exists(\App\Models\Activity::class, false) ? 'yes' : 'no')
            .' source_id='.$requestARF->source_id.' ';
        $sourceModel = $requestARF->getSourceModel();
    });

    if ($sourceModel && $requestARF->model_type === 'App\\Models\\Activity') {
        $step('Activity source relations', static function () use (&$sourceModel): void {
            $sourceModel->load(['matrix.division.divisionHead', 'staff', 'activity_budget']);
        });
    }

    $originatingChangeRequest = null;
    $step('originating ChangeRequest lookup', static function () use (&$requestARF, &$originatingChangeRequest): void {
        $originatingChangeRequest = ChangeRequest::where('request_arf_id', $requestARF->id)->first();
    });

    if ($originatingChangeRequest && function_exists('parent_based_disclaimer_data')) {
        $step('parent_based_disclaimer_data', static function () use (&$requestARF, &$sourceModel): void {
            parent_based_disclaimer_data(
                $requestARF->source_id,
                $requestARF->model_type,
                'arf',
                $requestARF->id,
                $sourceModel,
            );
        });
    }

    $activityIncludes = array_values(array_filter(
        get_included_files(),
        static fn (string $path): bool => basename($path) === 'Activity.php'
    ));

    echo "\nDone — Activity.php included ".count($activityIncludes)." time(s)\n";
    foreach ($activityIncludes as $path) {
        echo "  {$path}\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nFAILED at step: ".$e->getMessage()."\n");
    fwrite(STDERR, $e->getFile().':'.$e->getLine()."\n");
    exit(1);
}
