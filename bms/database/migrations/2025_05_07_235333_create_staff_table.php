<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->integer('staff_id')->unique();
            $table->string('work_email')->unique();
            $table->string('sap_no');
            $table->string('title')->nullable();
            $table->string('fname');
            $table->string('lname');
            $table->string('oname')->nullable();
            $table->string('grade')->nullable();
            $table->string('gender');
            $table->date('date_of_birth');
            $table->string('job_name')->nullable();
            $table->string('contracting_institution')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('nationality')->nullable();
            $table->string('division_name')->nullable();
            $table->foreignId('division_id')->nullable();
            $table->foreignId('duty_station_id')->nullable();
            $table->string('status')->nullable();
            $table->string('tel_1')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('private_email')->nullable();
            $table->string('photo')->nullable();
            $table->string('physical_location')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
