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
        Schema::table('service_requests', function (Blueprint $table) {
            // Add missing columns that don't exist yet
            if (!Schema::hasColumn('service_requests', 'request_date')) {
                $table->date('request_date')->after('request_number');
            }
            if (!Schema::hasColumn('service_requests', 'division_id')) {
                $table->foreignId('division_id')->constrained()->after('staff_id');
            }
            if (!Schema::hasColumn('service_requests', 'service_title')) {
                $table->string('service_title')->after('division_id');
            }
            if (!Schema::hasColumn('service_requests', 'description')) {
                $table->text('description')->after('service_title');
            }
            if (!Schema::hasColumn('service_requests', 'justification')) {
                $table->text('justification')->after('description');
            }
            if (!Schema::hasColumn('service_requests', 'required_by_date')) {
                $table->date('required_by_date')->after('justification');
            }
            if (!Schema::hasColumn('service_requests', 'location')) {
                $table->string('location')->nullable()->after('required_by_date');
            }
            if (!Schema::hasColumn('service_requests', 'estimated_cost')) {
                $table->decimal('estimated_cost', 15, 2)->default(0)->after('location');
            }
            if (!Schema::hasColumn('service_requests', 'priority')) {
                $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->after('estimated_cost');
            }
            if (!Schema::hasColumn('service_requests', 'service_type')) {
                $table->enum('service_type', ['it', 'maintenance', 'procurement', 'travel', 'other'])->default('other')->after('priority');
            }
            if (!Schema::hasColumn('service_requests', 'specifications')) {
                $table->json('specifications')->nullable()->after('service_type');
            }
            if (!Schema::hasColumn('service_requests', 'attachments')) {
                $table->json('attachments')->nullable()->after('specifications');
            }
            if (!Schema::hasColumn('service_requests', 'status')) {
                $table->enum('status', ['draft', 'submitted', 'in_progress', 'approved', 'rejected', 'completed'])->default('draft')->after('attachments');
            }
            if (!Schema::hasColumn('service_requests', 'remarks')) {
                $table->text('remarks')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn([
                'request_number',
                'request_date',
                'staff_id',
                'division_id',
                'service_title',
                'description',
                'justification',
                'required_by_date',
                'location',
                'estimated_cost',
                'priority',
                'service_type',
                'specifications',
                'attachments',
                'status',
                'remarks'
            ]);
            
            // Drop foreign key constraints
            $table->dropForeign(['staff_id']);
            $table->dropForeign(['division_id']);
        });
    }
};