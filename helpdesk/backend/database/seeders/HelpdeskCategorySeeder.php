<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HelpdeskCategorySeeder extends Seeder
{
    public function run(): void
    {
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
            DB::table('helpdesk_categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'sort_order' => $order++,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
