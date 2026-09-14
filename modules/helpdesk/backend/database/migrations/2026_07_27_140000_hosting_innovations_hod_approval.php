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
            if (! Schema::hasColumn('helpdesk_profiles', 'can_process_hosting_requests')) {
                $table->boolean('can_process_hosting_requests')->default(false)->after('can_manage_software_requests');
            }
            if (! Schema::hasColumn('helpdesk_profiles', 'can_process_innovation_requests')) {
                $table->boolean('can_process_innovation_requests')->default(false)->after('can_process_hosting_requests');
            }
        });

        DB::table('helpdesk_profiles')
            ->where(function ($q) {
                $q->where('role', 'admin')
                    ->orWhere('grant_helpdesk_admin', true);
            })
            ->update([
                'can_process_hosting_requests' => true,
                'can_process_innovation_requests' => true,
                'updated_at' => now(),
            ]);

        if (! Schema::hasTable('helpdesk_hosting_requests')) {
            Schema::create('helpdesk_hosting_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 32)->unique();
                $table->string('status', 32)->default('draft')->index();
                $table->string('category', 32)->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('cloud_provider')->nullable();
                $table->text('environment_notes')->nullable();
                $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('requester_staff_id')->nullable()->index();
                $table->string('requester_name');
                $table->unsignedInteger('requester_division_id')->nullable()->index();
                $table->string('requester_division_name')->nullable();
                $table->unsignedInteger('on_behalf_of_staff_id')->nullable()->index();
                $table->unsignedInteger('hod_staff_id')->nullable()->index();
                $table->string('hod_name')->nullable();
                $table->timestamp('hod_decided_at')->nullable();
                $table->foreignId('hod_decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('hod_decision_notes')->nullable();
                $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->text('process_notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('helpdesk_innovation_requests')) {
            Schema::create('helpdesk_innovation_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 32)->unique();
                $table->string('status', 32)->default('draft')->index();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('innovation_type')->nullable();
                $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('requester_staff_id')->nullable()->index();
                $table->string('requester_name');
                $table->unsignedInteger('requester_division_id')->nullable()->index();
                $table->string('requester_division_name')->nullable();
                $table->unsignedInteger('on_behalf_of_staff_id')->nullable()->index();
                $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('processed_at')->nullable();
                $table->text('process_notes')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('helpdesk_software_requests')) {
            Schema::table('helpdesk_software_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('helpdesk_software_requests', 'hod_staff_id')) {
                    $table->unsignedInteger('hod_staff_id')->nullable()->after('division_name');
                }
                if (! Schema::hasColumn('helpdesk_software_requests', 'hod_name')) {
                    $table->string('hod_name')->nullable()->after('hod_staff_id');
                }
                if (! Schema::hasColumn('helpdesk_software_requests', 'hod_decided_at')) {
                    $table->timestamp('hod_decided_at')->nullable()->after('hod_name');
                }
                if (! Schema::hasColumn('helpdesk_software_requests', 'hod_decided_by_user_id')) {
                    $table->foreignId('hod_decided_by_user_id')->nullable()->after('hod_decided_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('helpdesk_software_requests', 'hod_decision_notes')) {
                    $table->text('hod_decision_notes')->nullable()->after('hod_decided_by_user_id');
                }
            });

            // Existing in-flight "submitted" rows stay processable; new submits use pending_hod.
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('helpdesk_software_requests')) {
            Schema::table('helpdesk_software_requests', function (Blueprint $table) {
                foreach (['hod_decision_notes', 'hod_decided_by_user_id', 'hod_decided_at', 'hod_name', 'hod_staff_id'] as $col) {
                    if (Schema::hasColumn('helpdesk_software_requests', $col)) {
                        if ($col === 'hod_decided_by_user_id') {
                            $table->dropConstrainedForeignId('hod_decided_by_user_id');
                        } else {
                            $table->dropColumn($col);
                        }
                    }
                }
            });
        }

        Schema::dropIfExists('helpdesk_innovation_requests');
        Schema::dropIfExists('helpdesk_hosting_requests');

        Schema::table('helpdesk_profiles', function (Blueprint $table) {
            foreach (['can_process_innovation_requests', 'can_process_hosting_requests'] as $col) {
                if (Schema::hasColumn('helpdesk_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
