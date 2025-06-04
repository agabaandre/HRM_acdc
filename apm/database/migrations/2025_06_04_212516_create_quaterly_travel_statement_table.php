<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuaterlyTravelStatementTable extends Migration
{
    public function up(): void
    {
        Schema::create('quaterly_travel_statement', function (Blueprint $table) {
            $table->id();
            $table->string('staff_id'); // assuming it's a string (change to integer/foreignId if needed)
            $table->year('year');
            $table->enum('quater', ['Q1', 'Q2', 'Q3', 'Q4']);
            $table->integer('total_travel_days')->default(0);
            $table->integer('travel_balance')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quaterly_travel_statement');
    }
}

