<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Models\StaffLeave;
use Modules\Leave\Models\StaffLeaveCompensatoryCredit;
use Modules\Leave\Services\HolidayCalendarService;
use Modules\Leave\Services\HolidayCompensatoryGrantService;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Leave\Services\LeavePolicyService;
use Modules\Leave\Services\LeaveRequestService;
use Tests\TestCase;

class LeaveHolidayCalendarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge();
        DB::reconnect();

        Carbon::setTestNow(Carbon::parse('2026-12-13 09:00:00'));
        $this->createTables();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_kenyan_in_addis_gets_et_holidays_plus_kenya_independence(): void
    {
        $dates = app(HolidayCalendarService::class)->holidayDatesForStaff(1, 2026);

        $this->assertContains('2026-01-01', $dates);
        $this->assertContains('2026-03-02', $dates);
        $this->assertContains('2026-12-25', $dates);
        $this->assertContains('2026-12-12', $dates);
        $this->assertNotContains('2026-10-01', $dates);
    }

    public function test_weekend_independence_day_grants_holiday_credit_once_and_respects_cap(): void
    {
        $grants = app(HolidayCompensatoryGrantService::class);

        $this->assertGreaterThanOrEqual(1, $grants->grantForStaff(1, 2026));

        $credit = StaffLeaveCompensatoryCredit::query()
            ->where('staff_id', 1)
            ->where('kind', 'holiday')
            ->whereDate('source_date', '2026-12-12')
            ->first();
        $this->assertNotNull($credit);
        $this->assertSame('2026-12-31', $credit->expires_on->toDateString());
        $this->assertSame(1.0, (float) $credit->days);

        $this->assertSame(0, $grants->grantForStaff(1, 2026));

        StaffLeaveCompensatoryCredit::query()->where('staff_id', 1)->update(['days' => 15]);
        DB::table('leave_holiday_rules')->insert([
            'name' => 'Saturday once',
            'recurrence' => 'once',
            'once_date' => '2026-01-03',
            'scope' => 'global',
            'grants_compensatory_if_weekend' => 1,
            'is_active' => 1,
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assertSame(0, $grants->grantForStaff(1, 2026));
    }

    public function test_meskel_weekend_credit_only_for_listed_stations(): void
    {
        $grants = app(HolidayCompensatoryGrantService::class);
        $grants->grantForStaff(1, 2026);
        $this->assertTrue(
            StaffLeaveCompensatoryCredit::query()
                ->where('staff_id', 1)
                ->whereDate('source_date', '2026-09-27')
                ->exists()
        );

        $this->assertSame(0, $grants->grantForStaff(3, 2026));
        $this->assertFalse(
            StaffLeaveCompensatoryCredit::query()
                ->where('staff_id', 3)
                ->whereDate('source_date', '2026-09-27')
                ->exists()
        );
    }

    public function test_skip_all_omits_weekday_holidays_from_working_days(): void
    {
        $policy = app(LeavePolicyService::class);
        $policy->save(['weekday_holiday_in_request' => 'skip_all']);
        $this->assertSame(4, app(LeaveRequestService::class)->workingDaysBetween('2026-03-02', '2026-03-06', 1));

        $policy->save(['weekday_holiday_in_request' => 'count_all']);
        $this->assertSame(5, app(LeaveRequestService::class)->workingDaysBetween('2026-03-02', '2026-03-06', 1));

        $this->assertSame(0, app(LeaveRequestService::class)->workingDaysBetween('2026-03-07', '2026-03-08', 1));
    }

    public function test_holiday_compensatory_available_comes_from_holiday_credits(): void
    {
        StaffLeaveCompensatoryCredit::query()->create([
            'staff_id' => 1,
            'kind' => 'holiday',
            'days' => 2,
            'days_used' => 0,
            'granted_on' => '2026-09-28',
            'expires_on' => '2026-12-31',
            'source_date' => '2026-09-27',
            'reason' => 'Meskel weekend',
        ]);
        StaffLeaveCompensatoryCredit::query()->create([
            'staff_id' => 1,
            'kind' => 'other',
            'days' => 3,
            'days_used' => 1,
            'granted_on' => '2026-11-01',
            'expires_on' => '2027-02-01',
            'reason' => 'Travel',
        ]);

        $holidayType = LeaveType::query()->where('code', 'HOLIDAY_COMPENSATORY')->first();
        $otherType = LeaveType::query()->where('code', 'COMPENSATORY')->first();
        $annual = LeaveType::query()->where('code', 'ANNUAL')->first();

        $balances = app(LeaveBalanceService::class);
        $holiday = $balances->snapshot(1, (int) $holidayType->leave_id, 2026);
        $other = $balances->snapshot(1, (int) $otherType->leave_id, 2026);
        $annualSnap = $balances->snapshot(1, (int) $annual->leave_id, 2026);

        $this->assertSame(2.0, $holiday['available']);
        $this->assertSame(2.0, $holiday['holiday_compensatory']);
        $this->assertSame(2.0, $other['available']);
        $this->assertSame(2.0, $other['compensatory']);
        $this->assertSame(2.0, $annualSnap['holiday_compensatory']);
        $this->assertSame(2.0, $annualSnap['compensatory']);
        $this->assertSame(28.0, $annualSnap['available']);
    }

    public function test_hod_approval_consumes_holiday_credits(): void
    {
        StaffLeaveCompensatoryCredit::query()->create([
            'staff_id' => 1,
            'kind' => 'holiday',
            'days' => 2,
            'days_used' => 0,
            'granted_on' => '2026-09-28',
            'expires_on' => '2026-12-31',
            'source_date' => '2026-09-27',
        ]);
        $type = LeaveType::query()->where('code', 'HOLIDAY_COMPENSATORY')->first();
        $leave = StaffLeave::query()->create([
            'staff_id' => 1,
            'start_date' => '2026-12-14',
            'end_date' => '2026-12-14',
            'leave_id' => $type->leave_id,
            'email_leave' => 'a@b.c',
            'mobile_leave' => '1',
            'supporting_staff' => '2',
            'requested_days' => 1,
            'leave_balance' => 1,
            'contract_id' => 1,
            'supervisor_id' => 0,
            'supervisor2_id' => 0,
            'division_head' => 0,
            'overall_status' => 'Pending',
            'approval_status' => 'Pending',
            'approval_status1' => 'Pending',
            'approval_status2' => 'Pending',
            'approval_status3' => 'Pending',
        ]);

        app(LeaveRequestService::class)->approve((int) $leave->request_id, 'hod', 'Approved');

        $credit = StaffLeaveCompensatoryCredit::query()->where('staff_id', 1)->where('kind', 'holiday')->first();
        $this->assertSame(1.0, (float) $credit->days_used);
    }

    protected function createTables(): void
    {
        Schema::create('nationalities', function (Blueprint $table): void {
            $table->integer('nationality_id')->primary();
            $table->string('nationality')->nullable();
            $table->string('iso2', 2)->nullable();
            $table->string('iso3', 3)->nullable();
            $table->unsignedTinyInteger('independence_month')->nullable();
            $table->unsignedTinyInteger('independence_day')->nullable();
        });
        Schema::create('duty_stations', function (Blueprint $table): void {
            $table->integer('duty_station_id')->primary();
            $table->string('duty_station_name')->nullable();
            $table->string('country')->nullable();
            $table->string('country_iso2', 2)->nullable();
        });
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->integer('nationality_id')->nullable();
        });
        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->integer('staff_contract_id')->primary();
            $table->integer('staff_id');
            $table->integer('duty_station_id')->nullable();
            $table->integer('status_id')->nullable();
        });
        Schema::create('leave_holiday_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->nullable();
            $table->string('name', 150);
            $table->string('recurrence', 20);
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('day')->nullable();
            $table->date('once_date')->nullable();
            $table->string('scope', 20)->default('country');
            $table->string('country_iso2', 2)->nullable();
            $table->unsignedInteger('duty_station_id')->nullable();
            $table->boolean('grants_compensatory_if_weekend')->default(true);
            $table->json('compensatory_duty_station_ids')->nullable();
            $table->string('source', 20)->default('manual');
            $table->string('openholidays_id', 64)->nullable();
            $table->boolean('is_movable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('staff_leave_compensatory_credits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('staff_id');
            $table->string('kind', 20)->default('other');
            $table->decimal('days', 8, 2);
            $table->decimal('days_used', 8, 2)->default(0);
            $table->string('reason', 500)->nullable();
            $table->date('granted_on');
            $table->date('expires_on')->nullable();
            $table->unsignedInteger('granted_by_user_id')->nullable();
            $table->unsignedInteger('source_holiday_rule_id')->nullable();
            $table->date('source_date')->nullable();
            $table->timestamps();
            $table->unique(['staff_id', 'kind', 'source_date'], 'staff_comp_credit_holiday_unique');
        });
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->increments('leave_id');
            $table->string('leave_name');
            $table->string('code', 40)->nullable();
            $table->integer('leave_days')->default(0);
            $table->integer('is_accrued')->default(0);
            $table->float('accrual_rate')->default(0);
            $table->boolean('is_active')->default(true);
        });
        Schema::create('leave_policy_settings', function (Blueprint $table): void {
            $table->string('setting_key', 80)->primary();
            $table->json('setting_value');
            $table->timestamps();
        });
        Schema::create('staff_leave', function (Blueprint $table): void {
            $table->increments('request_id');
            $table->integer('staff_id');
            $table->date('start_date');
            $table->integer('leave_id');
            $table->date('end_date');
            $table->string('email_leave')->nullable();
            $table->string('mobile_leave')->nullable();
            $table->string('supporting_staff')->nullable();
            $table->integer('requested_days')->default(0);
            $table->integer('leave_balance')->default(0);
            $table->text('remarks')->nullable();
            $table->integer('contract_id')->default(0);
            $table->integer('supervisor_id')->default(0);
            $table->integer('supervisor2_id')->default(0);
            $table->integer('division_head')->default(0);
            $table->text('reject_reason')->nullable();
            $table->text('supporting_documentation')->nullable();
            $table->string('approval_status')->default('Pending');
            $table->string('approval_status1')->default('Pending');
            $table->string('approval_status2')->default('Pending');
            $table->string('approval_status3')->default('Pending');
            $table->string('overall_status')->default('Pending');
            $table->timestamps();
        });
        Schema::create('staff_leave_opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('leave_id');
            $table->unsignedSmallInteger('calendar_year');
            $table->decimal('opening_days', 8, 2)->default(0);
            $table->decimal('carried_forward_days', 8, 2)->default(0);
            $table->decimal('compensatory_days', 8, 2)->default(0);
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    protected function seedFixtures(): void
    {
        DB::table('nationalities')->insert([
            ['nationality_id' => 1, 'nationality' => 'Kenya', 'iso2' => 'KE', 'iso3' => 'KEN', 'independence_month' => 12, 'independence_day' => 12],
            ['nationality_id' => 2, 'nationality' => 'Nigeria', 'iso2' => 'NG', 'iso3' => 'NGA', 'independence_month' => 10, 'independence_day' => 1],
            ['nationality_id' => 3, 'nationality' => 'Ethiopia', 'iso2' => 'ET', 'iso3' => 'ETH', 'independence_month' => null, 'independence_day' => null],
        ]);
        DB::table('duty_stations')->insert([
            ['duty_station_id' => 10, 'duty_station_name' => 'HQ', 'country' => 'Ethiopia', 'country_iso2' => 'ET'],
            ['duty_station_id' => 20, 'duty_station_name' => 'Abuja', 'country' => 'Nigeria', 'country_iso2' => 'NG'],
            ['duty_station_id' => 30, 'duty_station_name' => 'RCC East', 'country' => 'Ethiopia', 'country_iso2' => 'ET'],
        ]);
        DB::table('staff')->insert([
            ['staff_id' => 1, 'fname' => 'Ken', 'lname' => 'Addis', 'nationality_id' => 1],
            ['staff_id' => 2, 'fname' => 'Ngo', 'lname' => 'Addis', 'nationality_id' => 2],
            ['staff_id' => 3, 'fname' => 'Et', 'lname' => 'East', 'nationality_id' => 3],
        ]);
        DB::table('staff_contracts')->insert([
            ['staff_contract_id' => 1, 'staff_id' => 1, 'duty_station_id' => 10, 'status_id' => 1],
            ['staff_contract_id' => 2, 'staff_id' => 2, 'duty_station_id' => 10, 'status_id' => 1],
            ['staff_contract_id' => 3, 'staff_id' => 3, 'duty_station_id' => 30, 'status_id' => 1],
        ]);
        DB::table('leave_holiday_rules')->insert([
            [
                'code' => 'global_new_year',
                'name' => 'New Year',
                'recurrence' => 'yearly_md',
                'month' => 1,
                'day' => 1,
                'scope' => 'global',
                'country_iso2' => null,
                'grants_compensatory_if_weekend' => 1,
                'compensatory_duty_station_ids' => null,
                'is_active' => 1,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'global_christmas',
                'name' => 'International Christmas',
                'recurrence' => 'yearly_md',
                'month' => 12,
                'day' => 25,
                'scope' => 'global',
                'country_iso2' => null,
                'grants_compensatory_if_weekend' => 1,
                'compensatory_duty_station_ids' => null,
                'is_active' => 1,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'et_adwa',
                'name' => 'Adwa',
                'recurrence' => 'yearly_md',
                'month' => 3,
                'day' => 2,
                'scope' => 'country',
                'country_iso2' => 'ET',
                'grants_compensatory_if_weekend' => 1,
                'compensatory_duty_station_ids' => null,
                'is_active' => 1,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ng_democracy',
                'name' => 'Nigeria Democracy Day',
                'recurrence' => 'yearly_md',
                'month' => 6,
                'day' => 12,
                'scope' => 'country',
                'country_iso2' => 'NG',
                'grants_compensatory_if_weekend' => 1,
                'compensatory_duty_station_ids' => null,
                'is_active' => 1,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'et_meskel',
                'name' => 'Meskel',
                'recurrence' => 'yearly_md',
                'month' => 9,
                'day' => 27,
                'scope' => 'country',
                'country_iso2' => 'ET',
                'grants_compensatory_if_weekend' => 1,
                'compensatory_duty_station_ids' => json_encode([10]),
                'is_active' => 1,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('leave_types')->insert([
            ['leave_name' => 'Annual Leave', 'code' => 'ANNUAL', 'leave_days' => 28, 'is_accrued' => 0, 'accrual_rate' => 0, 'is_active' => 1],
            ['leave_name' => 'Holiday compensatory leave', 'code' => 'HOLIDAY_COMPENSATORY', 'leave_days' => 0, 'is_accrued' => 0, 'accrual_rate' => 0, 'is_active' => 1],
            ['leave_name' => 'Compensatory Leave', 'code' => 'COMPENSATORY', 'leave_days' => 0, 'is_accrued' => 0, 'accrual_rate' => 0, 'is_active' => 1],
        ]);
    }
}
