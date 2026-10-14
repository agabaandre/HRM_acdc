<?php

namespace Tests\Unit;

use App\Services\BudgetExecutionScope;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetExecutionScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'logging.default' => 'array',
        ]);
        DB::purge();
        DB::reconnect();

        Schema::create('divisions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('division_name');
            $table->unsignedBigInteger('directorate_id')->nullable();
            $table->unsignedBigInteger('director_id')->nullable();
            $table->unsignedBigInteger('finance_officer')->nullable();
            $table->unsignedBigInteger('finance_officer_oic_id')->nullable();
            $table->date('finance_officer_oic_start_date')->nullable();
            $table->date('finance_officer_oic_end_date')->nullable();
        });
        Schema::create('staff', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->primary();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->json('associated_divisions')->nullable();
        });
        Schema::create('directorates', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('director_id')->nullable();
        });
    }

    public function test_finance_officer_sees_all_divisions(): void
    {
        DB::table('divisions')->insert([
            'id' => 10,
            'division_name' => 'Finance Div',
            'finance_officer' => 7001,
        ]);

        $scope = (new BudgetExecutionScope)->resolve([], 1, 7001, 10);

        $this->assertTrue($scope['can_view_all']);
        $this->assertSame('all', $scope['mode']);
    }

    public function test_regular_staff_keeps_only_attached_division_not_peers(): void
    {
        DB::table('divisions')->insert([
            ['id' => 1, 'division_name' => 'A', 'directorate_id' => 5, 'director_id' => null, 'finance_officer' => null],
            ['id' => 2, 'division_name' => 'B', 'directorate_id' => 5, 'director_id' => null, 'finance_officer' => null],
        ]);
        DB::table('staff')->insert([
            'staff_id' => 8001,
            'division_id' => 1,
            'associated_divisions' => json_encode([]),
        ]);

        $scope = (new BudgetExecutionScope)->resolve([], 1, 8001, 1);

        $this->assertFalse($scope['can_view_all']);
        $this->assertSame('division', $scope['mode']);
        $this->assertSame([1], $scope['division_ids']);
    }

    public function test_director_sees_all_divisions_in_directorate(): void
    {
        DB::table('divisions')->insert([
            ['id' => 1, 'division_name' => 'A', 'directorate_id' => 5, 'director_id' => 9001, 'finance_officer' => null],
            ['id' => 2, 'division_name' => 'B', 'directorate_id' => 5, 'director_id' => null, 'finance_officer' => null],
            ['id' => 3, 'division_name' => 'C', 'directorate_id' => 9, 'director_id' => null, 'finance_officer' => null],
        ]);
        DB::table('staff')->insert([
            'staff_id' => 9001,
            'division_id' => 1,
            'associated_divisions' => json_encode([]),
        ]);

        $scope = (new BudgetExecutionScope)->resolve([], 1, 9001, 1);

        $this->assertFalse($scope['can_view_all']);
        $this->assertSame('directorate', $scope['mode']);
        $this->assertSame([1, 2], $scope['division_ids']);
    }

    public function test_multi_division_staff_sees_attached_only(): void
    {
        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id('staff_contract_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedTinyInteger('status_id')->default(1);
            $table->json('other_associated_divisions')->nullable();
        });

        DB::table('divisions')->insert([
            ['id' => 1, 'division_name' => 'A', 'directorate_id' => 5, 'director_id' => null, 'finance_officer' => null],
            ['id' => 2, 'division_name' => 'B', 'directorate_id' => 5, 'director_id' => null, 'finance_officer' => null],
            ['id' => 3, 'division_name' => 'C', 'directorate_id' => 9, 'director_id' => null, 'finance_officer' => null],
        ]);
        DB::table('staff_contracts')->insert([
            'staff_id' => 8100,
            'division_id' => 1,
            'status_id' => 1,
            'other_associated_divisions' => json_encode([3]),
        ]);

        $scope = (new BudgetExecutionScope)->resolve([], 1, 8100, 1);

        $this->assertFalse($scope['can_view_all']);
        $this->assertContains(1, $scope['division_ids']);
        $this->assertContains(3, $scope['division_ids']);
        $this->assertNotContains(2, $scope['division_ids']);
    }
}
