<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_business_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_anonymous')->default(false);
            $table->timestamps();
        });

        $now = now();
        $units = [
            ['name' => 'IT & MIS', 'slug' => 'it-mis', 'sort_order' => 10, 'allows_anonymous' => false],
            ['name' => 'Knowledge Management', 'slug' => 'knowledge-management', 'sort_order' => 20, 'allows_anonymous' => false],
            ['name' => 'Human Resource', 'slug' => 'human-resource', 'sort_order' => 30, 'allows_anonymous' => false],
            ['name' => 'Finance', 'slug' => 'finance', 'sort_order' => 40, 'allows_anonymous' => false],
            ['name' => 'Internal Oversight', 'slug' => 'internal-oversight', 'sort_order' => 50, 'allows_anonymous' => true],
        ];

        foreach ($units as $unit) {
            DB::table('helpdesk_business_units')->updateOrInsert(
                ['slug' => $unit['slug']],
                [
                    'name' => $unit['name'],
                    'sort_order' => $unit['sort_order'],
                    'is_active' => true,
                    'allows_anonymous' => $unit['allows_anonymous'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $itMisId = (int) DB::table('helpdesk_business_units')->where('slug', 'it-mis')->value('id');

        Schema::table('helpdesk_categories', function (Blueprint $table) use ($itMisId) {
            if (! Schema::hasColumn('helpdesk_categories', 'business_unit_id')) {
                $table->foreignId('business_unit_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('helpdesk_business_units')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('helpdesk_categories', 'ai_description')) {
                $table->text('ai_description')->nullable()->after('default_priority');
            }
        });

        if ($itMisId > 0) {
            DB::table('helpdesk_categories')
                ->whereNull('business_unit_id')
                ->update(['business_unit_id' => $itMisId, 'updated_at' => $now]);
        }

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_tickets', 'business_unit_id')) {
                $table->foreignId('business_unit_id')
                    ->nullable()
                    ->after('category_id')
                    ->constrained('helpdesk_business_units')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('helpdesk_tickets', 'is_anonymous')) {
                $table->boolean('is_anonymous')->default(false)->after('requester_email');
            }
        });

        // Allow tickets to be created without a category while AI categorizes.
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // SQLite rebuilds via doctrine-less recreate; FK already dropped for tests that use MySQL-like.
        } else {
            DB::statement('ALTER TABLE helpdesk_tickets MODIFY category_id BIGINT UNSIGNED NULL');
        }

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('helpdesk_categories')
                ->nullOnDelete();
        });

        // Backfill ticket business_unit_id from category.
        if ($itMisId > 0) {
            DB::statement(
                'UPDATE helpdesk_tickets t
                 INNER JOIN helpdesk_categories c ON c.id = t.category_id
                 SET t.business_unit_id = COALESCE(c.business_unit_id, ?)
                 WHERE t.business_unit_id IS NULL',
                [$itMisId]
            );
        }

        DB::table('helpdesk_settings')->updateOrInsert(
            ['key' => 'show_issue_category_on_request_form'],
            ['value' => '0', 'created_at' => $now, 'updated_at' => $now]
        );

        // Placeholder categories so non–IT&MIS units appear on the request form.
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
            DB::table('helpdesk_categories')->insert([
                'business_unit_id' => $unitId,
                'name' => $name,
                'slug' => $catSlug,
                'sort_order' => 100,
                'is_active' => true,
                'default_priority' => 'medium',
                'ai_description' => 'General enquiries and requests for this business unit. Use when no more specific category fits.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('helpdesk_settings')->where('key', 'show_issue_category_on_request_form')->delete();

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_tickets', 'is_anonymous')) {
                $table->dropColumn('is_anonymous');
            }
            if (Schema::hasColumn('helpdesk_tickets', 'business_unit_id')) {
                $table->dropConstrainedForeignId('business_unit_id');
            }
        });

        // Restore non-null category_id where possible (assign IT&MIS first category).
        $fallbackCategoryId = DB::table('helpdesk_categories')->orderBy('id')->value('id');
        if ($fallbackCategoryId) {
            DB::table('helpdesk_tickets')
                ->whereNull('category_id')
                ->update(['category_id' => $fallbackCategoryId]);
        }

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE helpdesk_tickets MODIFY category_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreign('category_id')
                ->references('id')
                ->on('helpdesk_categories');
        });

        Schema::table('helpdesk_categories', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_categories', 'ai_description')) {
                $table->dropColumn('ai_description');
            }
            if (Schema::hasColumn('helpdesk_categories', 'business_unit_id')) {
                $table->dropConstrainedForeignId('business_unit_id');
            }
        });

        Schema::dropIfExists('helpdesk_business_units');
    }
};
