<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const DEFAULT_TITLE = 'APM Approval Notification';

    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        if (!Schema::hasColumn('notifications', 'title')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->string('title', 255)->default(self::DEFAULT_TITLE)->after('type');
            });
        }

        if (Schema::hasColumn('notifications', 'title')) {
            DB::table('notifications')
                ->where(function ($q) {
                    $q->whereNull('title')->orWhere('title', '');
                })
                ->update(['title' => self::DEFAULT_TITLE]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications') || !Schema::hasColumn('notifications', 'title')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
