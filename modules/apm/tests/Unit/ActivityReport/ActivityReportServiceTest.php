<?php

use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\NonTravelMemo;
use App\Services\ActivityReport\ActivityReportAutofillService;
use App\Services\ActivityReport\ActivityReportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('activity_report_related');
    Schema::dropIfExists('activity_reports');
    Schema::dropIfExists('activity_report_test_sources');

    $createTables = require __DIR__.'/../../../database/migrations/2026_10_12_100000_create_activity_reports_tables.php';
    $createTables->up();

    Schema::create('activity_report_test_sources', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('staff_id')->nullable();
        $table->unsignedBigInteger('responsible_person_id')->nullable();
        $table->unsignedBigInteger('division_id')->nullable();
        $table->string('activity_title')->nullable();
        $table->string('workplan_activity_code')->nullable();
        $table->date('date_from')->nullable();
        $table->date('date_to')->nullable();
        $table->text('background')->nullable();
        $table->timestamps();
    });
});

it('autofills dates duration and title from source', function () {
    $source = new class extends Model
    {
        protected $table = 'activity_report_test_sources';

        protected $guarded = [];
    };
    $source->forceFill([
        'staff_id' => 5,
        'responsible_person_id' => 6,
        'division_id' => 2,
        'activity_title' => 'Field visit',
        'workplan_activity_code' => 'AWP-1',
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-03',
        'background' => 'Objectives text',
    ])->save();

    $attrs = (new ActivityReportAutofillService)->fromSource($source->fresh());

    expect($attrs['start_date'])->toBe('2026-10-01')
        ->and($attrs['end_date'])->toBe('2026-10-03')
        ->and($attrs['duration_days'])->toBe(3)
        ->and($attrs['activity_name_code'])->toContain('AWP-1')
        ->and($attrs['objectives'])->toBe('Objectives text');
});

it('fills empty report fields from parent source without overwriting', function () {
    $source = new class extends Model
    {
        protected $table = 'activity_report_test_sources';

        protected $guarded = [];
    };
    $source->forceFill([
        'staff_id' => 5,
        'activity_title' => 'Parent',
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-02',
        'background' => 'From parent',
    ])->save();

    $report = new ActivityReport([
        'objectives' => 'Keep mine',
        'location_venue' => null,
    ]);

    $changed = (new ActivityReportAutofillService)->fillEmptyFromSource($report, $source->fresh());

    expect($changed)->toBeTrue()
        ->and($report->objectives)->toBe('Keep mine')
        ->and(optional($report->start_date)->toDateString())->toBe('2026-10-01')
        ->and($report->activity_name_code)->toContain('Parent');
});

it('marks non-travel as without participants and external funded without budget', function () {
    $autofill = new ActivityReportAutofillService;
    $nt = new NonTravelMemo(['fund_type_id' => 1]);
    $external = new Activity(['fund_type_id' => 3]);

    expect($autofill->supportsParticipants($nt))->toBeFalse()
        ->and($autofill->supportsBudget($external))->toBeFalse()
        ->and($autofill->isExternalFunded($external))->toBeTrue();
});

it('find or create returns the same report twice', function () {
    $source = new class extends Model
    {
        protected $table = 'activity_report_test_sources';

        protected $guarded = [];
    };
    $source->forceFill([
        'staff_id' => 5,
        'activity_title' => 'Once',
        'date_from' => '2026-10-01',
        'date_to' => '2026-10-01',
    ])->save();

    $svc = new ActivityReportService(new ActivityReportAutofillService);
    $first = $svc->findOrCreateForSource($source->fresh(), 5);
    $second = $svc->findOrCreateForSource($source->fresh(), 5);

    expect($first->id)->toBe($second->id)
        ->and(ActivityReport::query()->count())->toBe(1);
});
