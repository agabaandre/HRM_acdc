<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_business_units', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
        });

        $defaults = [
            'it-mis' => 'IT systems, email, devices, networks, portals, and MIS applications.',
            'knowledge-management' => 'Knowledge Hub, document libraries, records, and information management services.',
            'human-resource' => 'HR processes, staff records, leave, recruitment, and people-related services.',
            'finance' => 'Finance systems, payments, budgets, procurement support, and accounting queries.',
            'internal-oversight' => 'Integrity, compliance, and confidential internal oversight matters. Anonymous reporting is available.',
        ];

        foreach ($defaults as $slug => $description) {
            DB::table('helpdesk_business_units')
                ->where('slug', $slug)
                ->where(function ($q) {
                    $q->whereNull('description')->orWhere('description', '');
                })
                ->update(['description' => $description, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_business_units', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
