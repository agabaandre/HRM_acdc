<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track explicit "Mark as agent" choices made by admins from
 * Settings → General → "View staff in default agent divisions".
 *
 * Nullable so:
 *  - existing rows stay untouched (NULL = never reviewed),
 *  - TRUE locks the agent role across SSO logins (overrides division-based fallback),
 *  - FALSE means an admin explicitly demoted them (role is then recomputed from
 *    payload/division on next SSO, same as before).
 *
 * No new "staff" table — the helpdesk-side designation lives next to the
 * existing role/staff_id columns on `helpdesk_profiles` (the "staff" table for
 * the helpdesk app). Staff directory data is still pulled live from the Staff
 * Share API through ReferenceDataController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->boolean('is_designated_agent')->nullable()->default(null)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn('is_designated_agent');
        });
    }
};
