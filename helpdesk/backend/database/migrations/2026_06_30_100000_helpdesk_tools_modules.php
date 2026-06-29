<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('helpdesk_profiles', 'can_manage_it_assets')) {
            Schema::table('helpdesk_profiles', function (Blueprint $table) {
                $table->boolean('can_manage_it_assets')->default(false)->after('can_change_ticket_category');
                $table->boolean('can_manage_licenses')->default(false)->after('can_manage_it_assets');
                $table->boolean('can_submit_software_requests')->default(true)->after('can_manage_licenses');
                $table->boolean('can_approve_software_requests')->default(false)->after('can_submit_software_requests');
                $table->boolean('can_manage_software_requests')->default(false)->after('can_approve_software_requests');
            });
        }

        if (! Schema::hasTable('helpdesk_it_asset_categories')) {
            Schema::create('helpdesk_it_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 64)->nullable();
            $table->unsignedSmallInteger('default_useful_life_years')->default(3);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        if (! Schema::hasTable('helpdesk_it_assets')) {
        Schema::create('helpdesk_it_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->foreignId('category_id')->constrained('helpdesk_it_asset_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 14, 2)->default(0);
            $table->decimal('salvage_value', 14, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_years')->nullable();
            $table->unsignedBigInteger('assigned_staff_id')->nullable()->index();
            $table->string('assigned_name')->nullable();
            $table->string('status', 32)->default('deployed')->index();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('helpdesk_licenses')) {
        Schema::create('helpdesk_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor')->nullable();
            $table->text('license_key')->nullable();
            $table->unsignedInteger('seats_total')->default(1);
            $table->unsignedInteger('seats_used')->default(0);
            $table->date('purchase_date')->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->unsignedSmallInteger('warning_days_before')->default(30);
            $table->decimal('cost', 14, 2)->nullable();
            $table->decimal('renewal_cost', 14, 2)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('helpdesk_software_requests')) {
        Schema::create('helpdesk_software_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('requester_name');
            $table->string('department')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('request_title');
            $table->text('problem_statement')->nullable();
            $table->text('proposed_solution')->nullable();
            $table->text('business_justification')->nullable();
            $table->text('affected_stakeholders')->nullable();
            $table->string('mandate_alignment')->nullable();
            $table->string('priority', 16)->default('medium');
            $table->string('desired_timeline')->nullable();
            $table->decimal('budget_estimate', 14, 2)->nullable();
            $table->text('existing_alternatives')->nullable();
            $table->text('additional_comments')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('decision', 32)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('team_lead_review_at')->nullable();
            $table->unsignedBigInteger('assigned_ba_staff_id')->nullable();
            $table->string('assigned_ba_name')->nullable();
            $table->string('project_id')->nullable();
            $table->timestamp('project_team_formed_at')->nullable();
            $table->foreignId('team_lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('helpdesk_software_request_team_members')) {
        Schema::create('helpdesk_software_request_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('helpdesk_software_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('member_name');
            $table->string('member_email')->nullable();
            $table->string('role', 64)->default('member');
            $table->timestamps();
            $table->unique(['software_request_id', 'member_email', 'role'], 'hd_sw_team_unique');
        });
        }

        if (! Schema::hasTable('helpdesk_software_request_approvals')) {
        Schema::create('helpdesk_software_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_request_id')->constrained('helpdesk_software_requests')->cascadeOnDelete();
            $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name')->nullable();
            $table->string('approval_role', 64);
            $table->string('decision', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
        }

        if (Schema::hasTable('helpdesk_it_asset_categories') && DB::table('helpdesk_it_asset_categories')->count() === 0) {
        $categories = [
            ['name' => 'Laptops', 'slug' => 'laptops', 'icon' => 'bx-laptop', 'default_useful_life_years' => 4, 'sort_order' => 10],
            ['name' => 'Phones', 'slug' => 'phones', 'icon' => 'bx-mobile', 'default_useful_life_years' => 3, 'sort_order' => 20],
            ['name' => 'Desktops', 'slug' => 'desktops', 'icon' => 'bx-desktop', 'default_useful_life_years' => 5, 'sort_order' => 30],
            ['name' => 'Tablets', 'slug' => 'tablets', 'icon' => 'bx-tablet', 'default_useful_life_years' => 3, 'sort_order' => 40],
            ['name' => 'Monitors', 'slug' => 'monitors', 'icon' => 'bx-tv', 'default_useful_life_years' => 5, 'sort_order' => 50],
            ['name' => 'Servers', 'slug' => 'servers', 'icon' => 'bx-server', 'default_useful_life_years' => 5, 'sort_order' => 60],
            ['name' => 'Network equipment', 'slug' => 'network-equipment', 'icon' => 'bx-network-chart', 'default_useful_life_years' => 5, 'sort_order' => 70],
            ['name' => 'Peripherals', 'slug' => 'peripherals', 'icon' => 'bx-plug', 'default_useful_life_years' => 3, 'sort_order' => 80],
            ['name' => 'Other', 'slug' => 'other', 'icon' => 'bx-package', 'default_useful_life_years' => 3, 'sort_order' => 99],
        ];

        foreach ($categories as $row) {
            DB::table('helpdesk_it_asset_categories')->insert(array_merge($row, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_software_request_approvals');
        Schema::dropIfExists('helpdesk_software_request_team_members');
        Schema::dropIfExists('helpdesk_software_requests');
        Schema::dropIfExists('helpdesk_licenses');
        Schema::dropIfExists('helpdesk_it_assets');
        Schema::dropIfExists('helpdesk_it_asset_categories');

        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'can_manage_it_assets',
                'can_manage_licenses',
                'can_submit_software_requests',
                'can_approve_software_requests',
                'can_manage_software_requests',
            ]);
        });
    }
};
