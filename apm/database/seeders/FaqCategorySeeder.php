<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class FaqCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Approvals Management System',
                'slug' => 'approvals-management-system',
                'description' => 'FAQs about quarterly travel matrices, single and special memos, change requests, approvals, and related workflows.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Staff Portal',
                'slug' => 'staff-portal',
                'description' => 'FAQs about the staff portal: profile, signature, performance (PPA, Midterm, Endterm), and related tasks.',
                'sort_order' => 2,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $item) {
            FaqCategory::updateOrCreate(
                ['slug' => $item['slug']],
                array_merge($item, ['is_active' => true])
            );
        }
    }
}
