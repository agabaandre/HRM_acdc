<?php

use App\Models\MemoTypeDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $nextOrder = ((int) MemoTypeDefinition::query()->max('sort_order')) + 1;

        MemoTypeDefinition::query()->updateOrCreate(
            ['slug' => 'upload'],
            [
                'name' => 'Upload',
                'description' => 'PDF upload memo with manual signer areas and staff-based approvers.',
                'ref_prefix' => 'UPL-',
                'is_division_specific' => false,
                'attachments_enabled' => true,
                'signature_style' => 'top_right',
                'fields_schema' => MemoTypeDefinition::normalizeFieldsSchemaRows([]),
                'is_system' => true,
                'sort_order' => $nextOrder,
                'is_active' => true,
            ]
        );
    }

    public function down(): void
    {
        MemoTypeDefinition::query()->where('slug', 'upload')->delete();
    }
};

