<?php

use App\Models\ActivityReport;
use App\Services\ActivityReport\ActivityReportAutofillService;
use App\Services\ActivityReport\ActivityReportService;
use App\Services\ActivityReport\ActivityReportShareService;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('activity_report_shares');
    Schema::dropIfExists('activity_report_related');
    Schema::dropIfExists('activity_reports');

    $create = require __DIR__.'/../../../database/migrations/2026_10_12_100000_create_activity_reports_tables.php';
    $create->up();
    $shares = require __DIR__.'/../../../database/migrations/2026_10_12_210000_create_activity_report_shares_table.php';
    $shares->up();
});

it('rejects invalid share targets', function () {
    $svc = new ActivityReportShareService(new ActivityReportService(new ActivityReportAutofillService));
    $report = ActivityReport::withoutEvents(fn () => ActivityReport::query()->create([
        'source_id' => 1,
        'source_type' => 'App\\Models\\Activity',
        'overall_status' => 'draft',
    ]));

    $svc->share($report, ['pra'], 1);
})->throws(InvalidArgumentException::class);

it('records shares for rba and rbm', function () {
    $svc = new ActivityReportShareService(new ActivityReportService(new ActivityReportAutofillService));
    $report = ActivityReport::withoutEvents(fn () => ActivityReport::query()->create([
        'source_id' => 2,
        'source_type' => 'App\\Models\\Activity',
        'overall_status' => 'approved',
        'has_been_submitted' => true,
        'activity_name_code' => 'AWP-9',
    ]));

    $rows = $svc->share($report, ['rba', 'rbm'], 99, 'sync');

    expect($rows)->toHaveCount(2)
        ->and(collect($rows)->pluck('target')->sort()->values()->all())->toBe(['rba', 'rbm'])
        ->and($rows[0]['status'])->toBe('sent');
});
