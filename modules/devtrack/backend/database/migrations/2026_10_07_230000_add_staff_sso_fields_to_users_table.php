<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->unique()->after('id');
            $table->string('division')->nullable()->after('organisation');
            $table->string('directorate')->nullable()->after('division');
            $table->timestamp('staff_synced_at')->nullable()->after('directorate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['staff_id', 'division', 'directorate', 'staff_synced_at']);
        });
    }
};
