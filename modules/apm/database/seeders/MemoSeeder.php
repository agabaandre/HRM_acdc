<?php

namespace Database\Seeders;

use App\Models\Memo;
use Illuminate\Database\Seeder;

class MemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample memo data based on SQL dump
        $memos = [
            [
                'id' => 1,
                'user_id' => 1,
                'workflow_id' => 1,
                'document_id' => 1,
                'title' => 'mr',
                'country' => 'uganda',
                'description' => 'Builds new software architecture documents by understanding user requirements and design constraint',
                'created_at' => '2025-05-03 11:34:30',
                'update_at' => '2025-05-03 11:36:03'
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'workflow_id' => 1,
                'document_id' => 1,
                'title' => 'PHEOC Inspection , Verification and Installation',
                'country' => 'Kenya',
                'description' => 'A mission for PHEOC Inspection Verification and Installation',
                'created_at' => '2025-05-03 11:34:30',
                'update_at' => '2025-05-03 11:36:03'
            ],
            [
                'id' => 3,
                'user_id' => 1,
                'workflow_id' => 1,
                'document_id' => 1,
                'title' => 'Travel Memo',
                'country' => 'Tunisia',
                'description' => 'Description goes here',
                'created_at' => '2025-05-03 11:34:30',
                'update_at' => '2025-05-03 11:36:03'
            ],
            [
                'id' => 4,
                'user_id' => 1,
                'workflow_id' => 1,
                'document_id' => 1,
                'title' => 'Dr Taj trip',
                'country' => 'Morocco',
                'description' => 'But Shanelle with go you',
                'created_at' => '2025-05-03 11:34:30',
                'update_at' => '2025-05-03 11:36:03'
            ]
        ];

        // Insert the memos into the database
        foreach ($memos as $memo) {
            Memo::create($memo);
        }
    }
}
