<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('operational_status')->default('planned')->after('status');
        });

        Schema::create('project_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('work_class')->nullable();
            $table->string('stage')->default('intake');
            $table->string('rag_status')->default('green');
            $table->text('rag_reason')->nullable();
            $table->string('priority_category')->nullable();
            $table->string('status')->default('not_started');
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('sprint_goal')->nullable();
            $table->string('budget_status')->nullable();
            $table->string('resource_status')->nullable();
            $table->unsignedTinyInteger('uat_progress_percent')->default(0);
            $table->string('deployment_readiness')->nullable();
            $table->string('data_classification')->nullable();
            $table->text('scope')->nullable();
            $table->text('success_measures')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('enhancements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_module_id')->nullable()->constrained('project_modules')->nullOnDelete();
            $table->string('name');
            $table->string('work_type')->default('feature_improvement');
            $table->text('description')->nullable();
            $table->text('scope')->nullable();
            $table->string('requesting_division')->nullable();
            $table->foreignId('business_owner_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('responsible_developer_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('priority')->default('medium');
            $table->date('planned_start')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('not_started');
            $table->text('acceptance_criteria')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('proposed');
            $table->date('target_date')->nullable();
            $table->date('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'version']);
        });

        Schema::create('enhancement_release', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enhancement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['enhancement_id', 'release_id']);
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->foreignId('enhancement_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('project_module_id')->nullable()->after('enhancement_id')->constrained('project_modules')->nullOnDelete();
        });

        // Promote legacy JSON module names into project_modules rows.
        $projects = DB::table('projects')->select('id', 'modules', 'created_by')->get();
        foreach ($projects as $project) {
            $modules = json_decode($project->modules ?? '[]', true);
            if (! is_array($modules)) {
                continue;
            }
            $seq = 1;
            foreach ($modules as $moduleName) {
                if (! is_string($moduleName) || trim($moduleName) === '') {
                    continue;
                }
                $code = sprintf('MOD-%d-%04d', (int) $project->id, $seq);
                DB::table('project_modules')->insert([
                    'project_id' => $project->id,
                    'code' => $code,
                    'name' => trim($moduleName),
                    'status' => 'not_started',
                    'stage' => 'intake',
                    'rag_status' => 'green',
                    'created_by' => $project->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $seq++;
            }
        }
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enhancement_id');
            $table->dropConstrainedForeignId('project_module_id');
        });

        Schema::dropIfExists('enhancement_release');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('enhancements');
        Schema::dropIfExists('project_modules');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('operational_status');
        });
    }
};
