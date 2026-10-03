<?php

use App\Support\StaffDivisionContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('staff');
    Schema::dropIfExists('divisions');

    Schema::create('divisions', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->string('division_name')->nullable();
        $table->string('division_short_name')->nullable();
        $table->unsignedBigInteger('division_head')->nullable();
        $table->unsignedBigInteger('focal_person')->nullable();
        $table->unsignedBigInteger('admin_assistant')->nullable();
        $table->unsignedBigInteger('finance_officer')->nullable();
        $table->unsignedBigInteger('finance_officer_oic_id')->nullable();
        $table->unsignedBigInteger('directorate_id')->nullable();
        $table->unsignedBigInteger('head_oic_id')->nullable();
        $table->date('head_oic_start_date')->nullable();
        $table->date('head_oic_end_date')->nullable();
        $table->unsignedBigInteger('director_id')->nullable();
        $table->unsignedBigInteger('director_oic_id')->nullable();
        $table->date('director_oic_start_date')->nullable();
        $table->date('director_oic_end_date')->nullable();
        $table->string('category')->nullable();
        $table->timestamps();
    });

    Schema::create('staff', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('staff_id')->unique();
        $table->string('work_email')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->unsignedBigInteger('division_id')->nullable();
        $table->string('division_name')->nullable();
        $table->json('associated_divisions')->nullable();
        $table->string('status')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    StaffDivisionContext::clearActive();
});

afterEach(function () {
    StaffDivisionContext::clearActive();
    Schema::dropIfExists('staff');
    Schema::dropIfExists('divisions');
});

function seedDivisionForContext(int $id, string $name, array $attrs = []): void
{
    DB::table('divisions')->insert(array_merge([
        'id' => $id,
        'division_name' => $name,
        'division_short_name' => substr($name, 0, 8),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attrs));
}

function seedStaffForContext(int $staffId, int $primaryDivisionId, array $associated = []): void
{
    DB::table('staff')->insert([
        'staff_id' => $staffId,
        'work_email' => "ctx{$staffId}@example.test",
        'fname' => 'Ctx',
        'lname' => (string) $staffId,
        'division_id' => $primaryDivisionId,
        'division_name' => 'Primary',
        'associated_divisions' => json_encode(array_values($associated)),
        'status' => 'Active',
        'active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('switchable includes primary and associated but not finance-officer-only division', function () {
    seedDivisionForContext(910010, 'Primary Div');
    seedDivisionForContext(910020, 'Associated Div');
    seedDivisionForContext(910030, 'Finance Only', ['finance_officer' => 910501]);
    seedStaffForContext(910501, 910010, [910020]);

    $ids = StaffDivisionContext::switchableIds(910501);

    expect($ids)->toContain(910010)
        ->toContain(910020)
        ->not->toContain(910030);
});

test('listDivisionIds returns the full switchable union for my-division lists', function () {
    seedDivisionForContext(910610, 'Primary Div');
    seedDivisionForContext(910620, 'Associated Div');
    seedDivisionForContext(910630, 'Focal Div', ['focal_person' => 910506]);
    seedStaffForContext(910506, 910610, [910620]);

    expect(StaffDivisionContext::listDivisionIds(910506))
        ->toContain(910610)
        ->toContain(910620)
        ->toContain(910630);
});

test('switchable includes division where staff is focal person', function () {
    seedDivisionForContext(910110, 'Home');
    seedDivisionForContext(910140, 'Focal Div', ['focal_person' => 910502]);
    seedStaffForContext(910502, 910110, []);

    expect(StaffDivisionContext::switchableIds(910502))
        ->toContain(910110)
        ->toContain(910140);
});

test('setActive rejects division outside switchable set', function () {
    seedDivisionForContext(910210, 'Only');
    seedStaffForContext(910503, 910210, []);

    expect(StaffDivisionContext::setActive(999999, 910503))->toBeFalse();
    expect(session(StaffDivisionContext::SESSION_ACTIVE_ID))->toBeNull();
});

test('setActive accepts associated division', function () {
    seedDivisionForContext(910310, 'P');
    seedDivisionForContext(910320, 'A');
    seedStaffForContext(910504, 910310, [910320]);

    expect(StaffDivisionContext::setActive(910320, 910504))->toBeTrue();
    expect((int) session(StaffDivisionContext::SESSION_ACTIVE_ID))->toBe(910320);
    expect(StaffDivisionContext::activeDivisionId(910504))->toBe(910320);
});

test('user_session division_id prefers active_division_id when switchable', function () {
    seedDivisionForContext(910410, 'Primary Div');
    seedDivisionForContext(910420, 'Associated Div');
    seedStaffForContext(910505, 910410, [910420]);

    session(['user' => ['staff_id' => 910505, 'division_id' => 910410, 'division_name' => 'Primary Div']]);
    expect((int) user_session('division_id'))->toBe(910410);

    expect(StaffDivisionContext::setActive(910420, 910505))->toBeTrue();
    expect((int) user_session('division_id'))->toBe(910420);
    expect((string) user_session('division_name'))->toBe('Associated Div');
});
