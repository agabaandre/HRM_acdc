<?php

use App\Models\ActivityReport;
use App\Services\ActivityReport\ActivityReportRelatedSearchService;
use Carbon\Carbon;

it('resolves quarter bounds from the report start date', function () {
    $report = new ActivityReport([
        'start_date' => '2026-10-15',
        'end_date' => '2026-10-20',
    ]);

    [$start, $end] = (new ActivityReportRelatedSearchService)->quarterBoundsForReport($report);

    expect($start->toDateString())->toBe('2026-10-01')
        ->and($end->toDateString())->toBe('2026-12-31')
        ->and($start)->toBeInstanceOf(Carbon::class);
});

it('falls back to current quarter when report has no dates', function () {
    $report = new ActivityReport([]);
    [$start, $end] = (new ActivityReportRelatedSearchService)->quarterBoundsForReport($report);

    expect($start->lessThanOrEqualTo($end))->toBeTrue()
        ->and($start->month)->toBeIn([1, 4, 7, 10]);
});
