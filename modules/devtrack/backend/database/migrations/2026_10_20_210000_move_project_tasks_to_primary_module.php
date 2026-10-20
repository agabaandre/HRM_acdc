<?php

use App\Models\Project;
use App\Services\ProjectModuleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_modules', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('project_id');
        });

        $service = app(ProjectModuleService::class);
        Project::query()
            ->withoutGlobalScopes()
            ->orderBy('id')
            ->each(function (Project $project) use ($service): void {
                $service->moveProjectTasksToPrimary($project);
            });
    }

    public function down(): void
    {
        Schema::table('project_modules', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
