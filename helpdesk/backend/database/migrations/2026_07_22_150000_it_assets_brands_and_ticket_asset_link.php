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
        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_business_units', 'allows_asset_link_on_resolve')) {
                $table->boolean('allows_asset_link_on_resolve')->default(false)->after('allows_anonymous');
            }
        });

        // Enable asset linking by default for IT & MIS.
        DB::table('helpdesk_business_units')
            ->where('slug', 'it-mis')
            ->update(['allows_asset_link_on_resolve' => true, 'updated_at' => now()]);

        Schema::create('helpdesk_it_asset_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $brands = [
            'Dell', 'Apple', 'HP', 'Lenovo', 'Samsung', 'Safaricom', 'Ethio Telecom',
            'Cisco', 'Microsoft', 'Asus', 'Acer', 'Logitech', 'Canon', 'Epson',
            'Huawei', 'Xiaomi', 'Toshiba', 'Brother', 'Other',
        ];
        $order = 10;
        foreach ($brands as $name) {
            $slug = Str::slug($name);
            DB::table('helpdesk_it_asset_brands')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'sort_order' => $order,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $order += 10;
        }

        Schema::table('helpdesk_it_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_it_assets', 'brand_id')) {
                $table->foreignId('brand_id')
                    ->nullable()
                    ->after('brand')
                    ->constrained('helpdesk_it_asset_brands')
                    ->nullOnDelete();
            }
        });

        // Backfill brand_id from free-text brand where possible.
        $brandRows = DB::table('helpdesk_it_asset_brands')->get(['id', 'name', 'slug']);
        foreach ($brandRows as $brand) {
            DB::table('helpdesk_it_assets')
                ->whereNull('brand_id')
                ->where(function ($q) use ($brand) {
                    $q->whereRaw('LOWER(brand) = ?', [strtolower($brand->name)])
                        ->orWhereRaw('LOWER(brand) = ?', [strtolower(str_replace('-', ' ', $brand->slug))]);
                })
                ->update(['brand_id' => $brand->id, 'updated_at' => $now]);
        }

        if (Schema::hasTable('helpdesk_it_asset_categories')) {
            $maxOrder = (int) DB::table('helpdesk_it_asset_categories')->max('sort_order');
            $extra = [
                ['name' => 'SIM cards', 'slug' => 'sim-cards', 'icon' => 'bx-chip', 'years' => 2],
                ['name' => 'Printers', 'slug' => 'printers', 'icon' => 'bx-printer', 'years' => 4],
                ['name' => 'Accessories', 'slug' => 'accessories', 'icon' => 'bx-plug', 'years' => 3],
                ['name' => 'Mobile hotspots', 'slug' => 'mobile-hotspots', 'icon' => 'bx-wifi', 'years' => 3],
            ];
            foreach ($extra as $i => $cat) {
                if (DB::table('helpdesk_it_asset_categories')->where('slug', $cat['slug'])->exists()) {
                    continue;
                }
                DB::table('helpdesk_it_asset_categories')->insert([
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'icon' => $cat['icon'],
                    'default_useful_life_years' => $cat['years'],
                    'sort_order' => $maxOrder + (($i + 1) * 10),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_tickets', 'linked_it_asset_id')) {
                $table->foreignId('linked_it_asset_id')
                    ->nullable()
                    ->after('business_unit_id')
                    ->constrained('helpdesk_it_assets')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_tickets', 'linked_it_asset_id')) {
                $table->dropConstrainedForeignId('linked_it_asset_id');
            }
        });

        Schema::table('helpdesk_it_assets', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_it_assets', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });

        Schema::dropIfExists('helpdesk_it_asset_brands');

        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_business_units', 'allows_asset_link_on_resolve')) {
                $table->dropColumn('allows_asset_link_on_resolve');
            }
        });
    }
};
