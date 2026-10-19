<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_modules', function (Blueprint $table) {
            $table->string('requesting_division')->nullable()->after('success_measures');
            $table->foreignId('business_owner_staff_id')->nullable()->after('requesting_division')->constrained('staff')->nullOnDelete();
            $table->foreignId('responsible_developer_staff_id')->nullable()->after('business_owner_staff_id')->constrained('staff')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_modules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_developer_staff_id');
            $table->dropConstrainedForeignId('business_owner_staff_id');
            $table->dropColumn('requesting_division');
        });
    }
};
