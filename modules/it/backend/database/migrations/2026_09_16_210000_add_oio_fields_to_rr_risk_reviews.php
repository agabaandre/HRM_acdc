<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rr_risk_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('rr_risk_reviews', 'action_update')) {
                $table->text('action_update')->nullable()->after('timeline');
            }
            if (! Schema::hasColumn('rr_risk_reviews', 'oio_verification_notes')) {
                $table->text('oio_verification_notes')->nullable()->after('action_update');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rr_risk_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('rr_risk_reviews', 'oio_verification_notes')) {
                $table->dropColumn('oio_verification_notes');
            }
            if (Schema::hasColumn('rr_risk_reviews', 'action_update')) {
                $table->dropColumn('action_update');
            }
        });
    }
};
