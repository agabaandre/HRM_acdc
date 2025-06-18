<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('fund_codes');

        Schema::create('fund_codes', function (Blueprint $table) {
            $table->id();
            $table->year('year');
            $table->string('code')->unique();
            $table->text('activity')->nullable();
            $table->unsignedBigInteger('fund_type_id')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->string('cost_centre')->nullable();
            $table->string('amert_code')->nullable();
            $table->string('fund')->nullable();
            $table->string('budget_balance')->nullable();
            $table->string('uploaded_budget')->nullable();
            $table->string('approved_budget')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('fund_type_id')->references('id')->on('fund_types')->nullOnDelete();
            $table->foreign('division_id')->references('id')->on('divisions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_codes');
    }
};
