<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('requester_email');
            $table->string('directorate')->nullable()->after('requesting_unit');
            $table->longText('proposed_solution')->nullable()->after('problem_statement');
            $table->longText('rationale')->nullable()->after('proposed_solution');
            $table->text('stakeholders')->nullable()->after('rationale');
            $table->string('mandate_alignment')->nullable()->after('stakeholders');
            $table->string('desired_timeline')->nullable()->after('mandate_alignment');
            $table->string('budget_estimate')->nullable()->after('desired_timeline');
            $table->text('existing_alternatives')->nullable()->after('budget_estimate');
            $table->text('additional_comments')->nullable()->after('existing_alternatives');
        });
    }

    public function down(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'directorate',
                'proposed_solution',
                'rationale',
                'stakeholders',
                'mandate_alignment',
                'desired_timeline',
                'budget_estimate',
                'existing_alternatives',
                'additional_comments',
            ]);
        });
    }
};
