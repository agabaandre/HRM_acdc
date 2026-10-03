<?php

use App\Http\Controllers\DivisionContextController;
use App\Support\StaffDivisionContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('staff');
    Schema::dropIfExists('divisions');

    Schema::create('divisions', function (Blueprint $table) {
        $table->unsignedBigInteger('id')->primary();
        $table->string('division_name')->nullable();
        $table->timestamps();
    });

    Schema::create('staff', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('staff_id')->unique();
        $table->unsignedBigInteger('division_id')->nullable();
        $table->json('associated_divisions')->nullable();
        $table->string('status')->nullable();
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    DB::table('divisions')->insert([
        ['id' => 920010, 'division_name' => 'Primary', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 920020, 'division_name' => 'Associated', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('staff')->insert([
        'staff_id' => 920701,
        'division_id' => 920010,
        'associated_divisions' => json_encode([920020]),
        'status' => 'Active',
        'active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    StaffDivisionContext::clearActive();
    session(['user' => ['staff_id' => 920701, 'division_id' => 920010, 'division_name' => 'Primary']]);
});

afterEach(function () {
    StaffDivisionContext::clearActive();
    Schema::dropIfExists('staff');
    Schema::dropIfExists('divisions');
});

test('division context controller switches active division', function () {
    $request = Request::create('/division-context', 'POST', ['division_id' => 920020]);
    $request->setLaravelSession(app('session.store'));

    $response = (new DivisionContextController)->update($request);

    expect($response->isRedirect())->toBeTrue();
    expect((int) session(StaffDivisionContext::SESSION_ACTIVE_ID))->toBe(920020);
});

test('division context controller rejects non-switchable division', function () {
    $request = Request::create('/division-context', 'POST', ['division_id' => 999999]);
    $request->headers->set('referer', 'http://127.0.0.1/home');
    $request->setLaravelSession(app('session.store'));

    $response = (new DivisionContextController)->update($request);

    expect($response->isRedirect())->toBeTrue();
    expect(session(StaffDivisionContext::SESSION_ACTIVE_ID))->toBeNull();
});
