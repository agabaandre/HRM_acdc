<?php

use App\Models\ReportingRegistryStatus;
use App\Services\ActivityReport\ActivityReportRegistryService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('reporting_registry_statuses');
    Schema::create('reporting_registry_statuses', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('source_id');
        $table->string('source_type');
        $table->timestamp('started_at')->nullable();
        $table->unsignedBigInteger('started_by')->nullable();
        $table->timestamp('processed_at')->nullable();
        $table->unsignedBigInteger('processed_by')->nullable();
        $table->timestamps();
        $table->unique(['source_id', 'source_type']);
    });
});

it('computes current quarter bounds for october as Q4', function () {
    $svc = new ActivityReportRegistryService;
    $bounds = $svc->currentQuarterBounds(Carbon::parse('2026-10-12'));

    expect($bounds['quarter'])->toBe(4)
        ->and($bounds['start']->toDateString())->toBe('2026-10-01')
        ->and($bounds['end']->toDateString())->toBe('2026-12-31');
});

it('mark processed also sets started when missing', function () {
    $svc = new ActivityReportRegistryService;
    $row = $svc->markProcessed('App\\Models\\NonTravelMemo', 42, 7);

    expect($row)->toBeInstanceOf(ReportingRegistryStatus::class)
        ->and($row->started_at)->not->toBeNull()
        ->and($row->processed_at)->not->toBeNull()
        ->and((int) $row->processed_by)->toBe(7);
});
