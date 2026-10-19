<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_source', 20)->default('system')->after('organisation');
        });

        DB::table('users')
            ->whereIn('approval_status', ['pending', 'rejected'])
            ->update(['registration_source' => 'public']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registration_source');
        });
    }
};
