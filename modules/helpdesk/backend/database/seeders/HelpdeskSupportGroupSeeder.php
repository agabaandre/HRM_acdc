<?php

namespace Database\Seeders;

use App\Models\HelpdeskSupportGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HelpdeskSupportGroupSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'name' => 'Software Development',
                'slug' => 'software-development',
                'description' => 'Business applications, portals, and custom software support.',
                'sort_order' => 10,
                'categories' => [
                    'staff-portal',
                    'apm-support',
                    'knowledge-hub-support',
                    'pra-support',
                    'other-systems-support',
                ],
            ],
            [
                'name' => 'Infrastructure Management',
                'slug' => 'infrastructure-management',
                'description' => 'Servers, endpoints, and platform infrastructure.',
                'sort_order' => 20,
                'categories' => [
                    'computer-troubleshooting',
                    'other-systems-support',
                ],
            ],
            [
                'name' => 'Network and Infrastructure',
                'slug' => 'network-and-infrastructure',
                'description' => 'Connectivity, email access, and network services.',
                'sort_order' => 30,
                'categories' => [
                    'email-access-support',
                ],
            ],
            [
                'name' => 'Systems Administration',
                'slug' => 'systems-administration',
                'description' => 'Identity, email, and workstation administration.',
                'sort_order' => 40,
                'categories' => [
                    'email-access-support',
                    'computer-troubleshooting',
                ],
            ],
        ];

        $categoryIdsBySlug = DB::table('helpdesk_categories')
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($definitions as $def) {
            $group = HelpdeskSupportGroup::query()->updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'sort_order' => $def['sort_order'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );

            $catIds = [];
            foreach ($def['categories'] as $slug) {
                if (isset($categoryIdsBySlug[$slug])) {
                    $catIds[] = $categoryIdsBySlug[$slug];
                }
            }
            $group->categories()->sync(array_values(array_unique($catIds)));
        }
    }
}
