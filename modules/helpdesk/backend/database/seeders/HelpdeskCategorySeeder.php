<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HelpdeskCategorySeeder extends Seeder
{
    public function run(): void
    {
        $itMisId = 0;
        if (Schema::hasTable('helpdesk_business_units')) {
            $itMisId = (int) DB::table('helpdesk_business_units')->where('slug', 'it-mis')->value('id');
        }

        $rows = [
            'Email Access Support',
            'Computer Troubleshooting',
            'Staff Portal',
            'APM Support',
            'Knowledge Hub Support',
            'PRA Support',
            'Other Systems Support',
        ];
        $order = 0;
        foreach ($rows as $name) {
            $slug = Str::slug($name);
            $payload = [
                'name' => $name,
                'sort_order' => $order++,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($itMisId > 0 && Schema::hasColumn('helpdesk_categories', 'business_unit_id')) {
                $payload['business_unit_id'] = $itMisId;
            }
            DB::table('helpdesk_categories')->updateOrInsert(
                ['slug' => $slug],
                $payload
            );
        }

        if ($itMisId < 1 || ! Schema::hasTable('helpdesk_business_units')) {
            return;
        }
        if (! Schema::hasColumn('helpdesk_categories', 'business_unit_id')) {
            return;
        }

        // Ensure non–IT&MIS units appear on the request form (needs ≥1 active category).
        $placeholders = [
            'knowledge-management' => 'Knowledge Management — General enquiry',
            'human-resource' => 'Human Resource — General enquiry',
            'finance' => 'Finance — General enquiry',
            'internal-oversight' => 'Internal Oversight — General enquiry',
        ];
        foreach ($placeholders as $unitSlug => $name) {
            $unitId = (int) DB::table('helpdesk_business_units')->where('slug', $unitSlug)->value('id');
            if ($unitId < 1) {
                continue;
            }
            $catSlug = $unitSlug.'-general';
            if (DB::table('helpdesk_categories')->where('slug', $catSlug)->exists()) {
                continue;
            }
            $row = [
                'business_unit_id' => $unitId,
                'name' => $name,
                'slug' => $catSlug,
                'sort_order' => 100,
                'is_active' => true,
                'default_priority' => 'medium',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('helpdesk_categories', 'ai_description')) {
                $row['ai_description'] = 'General enquiries and requests for this business unit. Use when no more specific category fits.';
            }
            DB::table('helpdesk_categories')->insert($row);
        }

        $this->seedProtocolUnitAndCategories();
    }

    private function seedProtocolUnitAndCategories(): void
    {
        if (! Schema::hasTable('helpdesk_business_units')) {
            return;
        }

        $now = now();
        $exists = DB::table('helpdesk_business_units')->where('slug', 'protocol')->exists();
        if (! $exists) {
            DB::table('helpdesk_business_units')->insert([
                'name' => 'Protocol',
                'slug' => 'protocol',
                'description' => 'Protocol office services: note verbals, visas, resident IDs, accreditation, VIP arrivals, and related diplomatic support.',
                'sort_order' => 40,
                'is_active' => true,
                'allows_anonymous' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $unitId = (int) DB::table('helpdesk_business_units')->where('slug', 'protocol')->value('id');
        if ($unitId < 1 || ! Schema::hasTable('helpdesk_categories')) {
            return;
        }

        $categories = [
            'Note Verbals' => 'Requests to draft, clear, transmit, or follow up on note verbales and formal diplomatic correspondence.',
            'Resident ID processing' => 'Applications, renewals, corrections, and status checks for resident identity documents handled by Protocol.',
            'Visa Processing' => 'Visa applications, invitations, extensions, and related immigration paperwork facilitated by Protocol.',
            'Diplomatic accreditation' => 'Accreditation of diplomats and officials, diplomatic ID cards, and related host-country formalities.',
            'Airport and VIP protocol' => 'VIP and official arrival/departure arrangements, airport facilitation, and meeting/escort protocol.',
            'Ceremonial and official events' => 'Protocol for ceremonies, high-level visits, seating, flags, and official event arrangements.',
            'Privileges and immunities' => 'Questions and requests about privileges, immunities, and related host-agreement entitlements.',
            'Protocol - general' => 'General Protocol office enquiries when no more specific category fits.',
        ];

        $order = 0;
        foreach ($categories as $name => $ai) {
            $slug = Str::slug($name);
            if (DB::table('helpdesk_categories')->where('slug', $slug)->exists()) {
                continue;
            }
            $row = [
                'business_unit_id' => $unitId,
                'name' => $name,
                'slug' => $slug,
                'sort_order' => $order++,
                'is_active' => true,
                'default_priority' => 'medium',
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('helpdesk_categories', 'ai_description')) {
                $row['ai_description'] = $ai;
            }
            DB::table('helpdesk_categories')->insert($row);
        }
    }
}
