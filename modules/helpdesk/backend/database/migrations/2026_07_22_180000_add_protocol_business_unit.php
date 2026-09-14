<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('helpdesk_business_units')) {
            return;
        }

        $now = now();
        if (! DB::table('helpdesk_business_units')->where('slug', 'protocol')->exists()) {
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
        } else {
            DB::table('helpdesk_business_units')
                ->where('slug', 'protocol')
                ->where(function ($q) {
                    $q->whereNull('description')->orWhere('description', '');
                })
                ->update([
                    'description' => 'Protocol office services: note verbals, visas, resident IDs, accreditation, VIP arrivals, and related diplomatic support.',
                    'updated_at' => $now,
                ]);
        }

        $unitId = (int) DB::table('helpdesk_business_units')->where('slug', 'protocol')->value('id');
        if ($unitId < 1 || ! Schema::hasTable('helpdesk_categories')) {
            return;
        }

        $categories = [
            [
                'name' => 'Note Verbals',
                'ai' => 'Requests to draft, clear, transmit, or follow up on note verbales and formal diplomatic correspondence.',
            ],
            [
                'name' => 'Resident ID processing',
                'ai' => 'Applications, renewals, corrections, and status checks for resident identity documents handled by Protocol.',
            ],
            [
                'name' => 'Visa Processing',
                'ai' => 'Visa applications, invitations, extensions, and related immigration paperwork facilitated by Protocol.',
            ],
            [
                'name' => 'Diplomatic accreditation',
                'ai' => 'Accreditation of diplomats and officials, diplomatic ID cards, and related host-country formalities.',
            ],
            [
                'name' => 'Airport and VIP protocol',
                'ai' => 'VIP and official arrival/departure arrangements, airport facilitation, and meeting/escort protocol.',
            ],
            [
                'name' => 'Ceremonial and official events',
                'ai' => 'Protocol for ceremonies, high-level visits, seating, flags, and official event arrangements.',
            ],
            [
                'name' => 'Privileges and immunities',
                'ai' => 'Questions and requests about privileges, immunities, and related host-agreement entitlements.',
            ],
            [
                'name' => 'Protocol - general',
                'ai' => 'General Protocol office enquiries when no more specific category fits.',
            ],
        ];

        $order = 0;
        foreach ($categories as $cat) {
            $slug = Str::slug($cat['name']);
            $payload = [
                'business_unit_id' => $unitId,
                'name' => $cat['name'],
                'sort_order' => $order++,
                'is_active' => true,
                'default_priority' => 'medium',
                'updated_at' => $now,
                'created_at' => $now,
            ];
            if (Schema::hasColumn('helpdesk_categories', 'ai_description')) {
                $payload['ai_description'] = $cat['ai'];
            }
            DB::table('helpdesk_categories')->updateOrInsert(
                ['slug' => $slug],
                $payload
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('helpdesk_categories')) {
            $slugs = [
                'note-verbals',
                'resident-id-processing',
                'visa-processing',
                'diplomatic-accreditation',
                'airport-and-vip-protocol',
                'ceremonial-and-official-events',
                'privileges-and-immunities',
                'protocol-general',
            ];
            DB::table('helpdesk_categories')->whereIn('slug', $slugs)->delete();
        }

        if (Schema::hasTable('helpdesk_business_units')) {
            DB::table('helpdesk_business_units')->where('slug', 'protocol')->delete();
        }
    }
};
