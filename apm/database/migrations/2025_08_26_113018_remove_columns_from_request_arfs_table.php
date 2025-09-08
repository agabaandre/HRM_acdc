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
        Schema::table('request_arfs', function (Blueprint $table) {
            // Remove the specified columns
            $table->dropColumn([
                'location_id',
                'activity_title',
                'purpose',
                'start_date',
                'end_date',
                'requested_amount',
                'accounting_code',
                'budget_breakdown',
                'attachment',
                'status',
                'arf_number'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('request_arfs', function (Blueprint $table) {
            // Re-add the removed columns
            $table->json('location_id')->nullable();
            $table->string('activity_title')->nullable();
            $table->text('purpose')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('requested_amount', 15, 2)->nullable();
            $table->string('accounting_code')->nullable();
            $table->json('budget_breakdown')->nullable();
            $table->json('attachment')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->string('arf_number')->unique()->nullable();
        });
    }
};
