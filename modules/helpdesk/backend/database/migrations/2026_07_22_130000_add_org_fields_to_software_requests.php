<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_software_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_software_requests', 'division_id')) {
                $table->unsignedInteger('division_id')->nullable()->after('department')->index();
            }
            if (! Schema::hasColumn('helpdesk_software_requests', 'directorate_id')) {
                $table->unsignedInteger('directorate_id')->nullable()->after('division_id')->index();
            }
            if (! Schema::hasColumn('helpdesk_software_requests', 'division_name')) {
                $table->string('division_name')->nullable()->after('directorate_id');
            }
            if (! Schema::hasColumn('helpdesk_software_requests', 'directorate_name')) {
                $table->string('directorate_name')->nullable()->after('division_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_software_requests', function (Blueprint $table) {
            foreach (['directorate_name', 'division_name', 'directorate_id', 'division_id'] as $col) {
                if (Schema::hasColumn('helpdesk_software_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
