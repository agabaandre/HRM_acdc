<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_profiles', 'can_manage_information_systems')) {
                $table->boolean('can_manage_information_systems')->default(false)->after('can_manage_licenses');
            }
        });

        $adminRoleIds = collect(explode(',', (string) env('HELPDESK_SSO_STAFF_ROLE_IDS_ADMIN', '10')))
            ->map(fn (string $v) => (int) trim($v))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        DB::table('helpdesk_profiles')
            ->where(function ($sub) use ($adminRoleIds) {
                $sub->where('role', 'admin')
                    ->orWhere('grant_helpdesk_admin', true);
                if ($adminRoleIds !== []) {
                    $sub->orWhereIn('staff_portal_role', $adminRoleIds);
                }
            })
            ->update(['can_manage_information_systems' => true, 'updated_at' => now()]);

        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (! Schema::hasColumn('helpdesk_business_units', 'allows_information_system_link_on_resolve')) {
                $table->boolean('allows_information_system_link_on_resolve')->default(false)
                    ->after('allows_asset_link_on_resolve');
            }
        });

        DB::table('helpdesk_business_units')
            ->where('slug', 'it-mis')
            ->update(['allows_information_system_link_on_resolve' => true, 'updated_at' => now()]);

        if (! Schema::hasTable('helpdesk_information_system_languages')) {
            Schema::create('helpdesk_information_system_languages', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('helpdesk_information_systems')) {
            Schema::create('helpdesk_information_systems', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status', 32);
                $table->string('host')->nullable();
                $table->string('host_name')->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('domain')->nullable();
                $table->string('os')->nullable();
                $table->string('version', 64)->default('1.0');
                $table->date('last_update_on')->nullable();
                $table->unsignedInteger('division_id')->nullable()->index();
                $table->unsignedInteger('focal_staff_id')->nullable()->index();
                $table->string('focal_name_raw')->nullable();
                $table->unsignedInteger('mis_focal_staff_id')->nullable()->index();
                $table->string('mis_focal_name_raw')->nullable();
                $table->string('system_profile_url', 2048)->nullable();
                $table->string('user_manual_users_url', 2048)->nullable();
                $table->string('user_manual_managers_url', 2048)->nullable();
                $table->string('user_manual_technical_url', 2048)->nullable();
                $table->string('faqs_url', 2048)->nullable();
                $table->string('sops_url', 2048)->nullable();
                $table->unsignedInteger('total_users')->nullable();
                $table->decimal('estimated_annual_hosting_cost', 12, 2)->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique('name');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('helpdesk_information_system_language')) {
            Schema::create('helpdesk_information_system_language', function (Blueprint $table) {
                $table->id();
                $table->foreignId('information_system_id');
                $table->foreignId('language_id');
                $table->foreign('information_system_id', 'hd_is_lang_sys_fk')
                    ->references('id')
                    ->on('helpdesk_information_systems')
                    ->cascadeOnDelete();
                $table->foreign('language_id', 'hd_is_lang_lang_fk')
                    ->references('id')
                    ->on('helpdesk_information_system_languages')
                    ->cascadeOnDelete();
                $table->unique(['information_system_id', 'language_id'], 'hd_is_lang_unique');
            });
        }

        if (! Schema::hasTable('helpdesk_information_system_modules')) {
            Schema::create('helpdesk_information_system_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('information_system_id');
                $table->foreign('information_system_id', 'hd_is_mod_sys_fk')
                    ->references('id')
                    ->on('helpdesk_information_systems')
                    ->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status', 32);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['information_system_id', 'name'], 'hd_is_module_name_unique');
                $table->index(['information_system_id', 'sort_order'], 'hd_is_mod_sort_idx');
            });
        }

        if (! Schema::hasTable('helpdesk_information_system_status_events')) {
            Schema::create('helpdesk_information_system_status_events', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 16);
                $table->unsignedBigInteger('entity_id');
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32);
                $table->unsignedBigInteger('changed_by_user_id')->nullable();
                $table->timestamp('changed_at');
                $table->string('note')->nullable();
                $table->timestamps();
                $table->index(['entity_type', 'entity_id'], 'hd_is_evt_entity_idx');
                $table->index('changed_at', 'hd_is_evt_changed_idx');
                $table->foreign('changed_by_user_id', 'hd_is_evt_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('helpdesk_tickets', 'linked_information_system_id')) {
            Schema::table('helpdesk_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('linked_information_system_id')->nullable()->after('linked_it_asset_id');
                $table->foreign('linked_information_system_id', 'hd_ticket_is_fk')
                    ->references('id')
                    ->on('helpdesk_information_systems')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_tickets', 'linked_information_system_id')) {
                $table->dropForeign('hd_ticket_is_fk');
                $table->dropColumn('linked_information_system_id');
            }
        });

        Schema::dropIfExists('helpdesk_information_system_status_events');
        Schema::dropIfExists('helpdesk_information_system_modules');
        Schema::dropIfExists('helpdesk_information_system_language');
        Schema::dropIfExists('helpdesk_information_systems');
        Schema::dropIfExists('helpdesk_information_system_languages');

        Schema::table('helpdesk_business_units', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_business_units', 'allows_information_system_link_on_resolve')) {
                $table->dropColumn('allows_information_system_link_on_resolve');
            }
        });

        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('helpdesk_profiles', 'can_manage_information_systems')) {
                $table->dropColumn('can_manage_information_systems');
            }
        });
    }
};
