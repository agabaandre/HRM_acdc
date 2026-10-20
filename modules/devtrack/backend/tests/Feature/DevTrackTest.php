<?php

namespace Tests\Feature;

use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\WorkClass;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkplanIndicator;
use App\Models\WorkRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevTrackTest extends TestCase
{
    use RefreshDatabase;

    private function token(): string
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        return $admin->createToken('admin-panel')->plainTextToken;
    }

    public function test_dashboard_returns_portfolio_stats(): void
    {
        $indicator = WorkplanIndicator::factory()->create();
        Project::factory()->create([
            'workplan_indicator_id' => $indicator->id,
            'rag_status' => 'red',
        ]);

        $this->withToken($this->token())
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.active_projects', 1)
            ->assertJsonPath('stats.red_projects', 1)
            ->assertJsonStructure(['portfolio', 'indicators', 'task_activity', 'my_work']);
    }

    public function test_project_requires_workplan_indicator(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/admin/projects', [
                'name' => 'Orphan project',
                'work_class' => WorkClass::MediumProject->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['workplan_indicator_id']);
    }

    public function test_can_register_project_linked_to_indicator_and_assign_task(): void
    {
        $token = $this->token();
        $assignee = User::factory()->create(['is_active' => true]);
        $indicator = WorkplanIndicator::factory()->create([
            'code' => 'WP-2026-SD-99',
            'title' => 'Dummy institutionalize DevSecOps',
        ]);
        $request = WorkRequest::factory()->create([
            'workplan_indicator_id' => $indicator->id,
            'title' => 'Register DevTrack as a controlled request',
        ]);

        $created = $this->withToken($token)
            ->postJson('/api/v1/admin/projects', [
                'name' => 'Africa CDC DevTrack',
                'work_class' => WorkClass::MediumProject->value,
                'workplan_indicator_id' => $indicator->id,
                'work_request_id' => $request->id,
                'stage' => ProjectStage::Development->value,
                'roles' => [
                    ['role' => 'project_manager', 'user_id' => $assignee->id],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.indicator.code', 'WP-2026-SD-99')
            ->assertJsonPath('data.work_request.reference', $request->reference)
            ->assertJsonPath('data.roles.project_manager.user.id', $assignee->id)
            ->assertJsonPath('data.project_modules.0.is_primary', true);

        $projectId = $created->json('data.id');

        $this->assertDatabaseHas('work_requests', [
            'id' => $request->id,
            'project_id' => $projectId,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/admin/work-items', [
                'project_id' => $projectId,
                'title' => 'Write weekly status report',
                'type' => 'task',
                'assignee_id' => $assignee->id,
                'priority' => 'must_do',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Write weekly status report')
            ->assertJsonPath('data.assignee.id', $assignee->id);

        $moduleId = $created->json('data.project_modules.0.id');
        $this->assertNotNull($moduleId);
        $this->assertDatabaseHas('work_items', [
            'project_id' => $projectId,
            'project_module_id' => $moduleId,
            'assignee_id' => $assignee->id,
        ]);
    }

    public function test_intake_scores_request_and_converts_to_project(): void
    {
        $token = $this->token();
        $indicator = WorkplanIndicator::factory()->create();

        $created = $this->withToken($token)
            ->postJson('/api/v1/admin/work-requests', [
                'title' => 'Single intake channel',
                'requesting_unit' => 'MIS',
                'requester_name' => 'Product Owner',
                'problem_statement' => 'Requests arrive through too many channels.',
                'work_class' => WorkClass::MediumProject->value,
                'workplan_indicator_id' => $indicator->id,
                'urgency' => 'critical',
                'score_strategic_alignment' => 90,
                'score_impact' => 80,
                'score_urgency_risk' => 70,
                'score_user_reach' => 60,
                'score_feasibility' => 70,
                'score_cost_effort' => 50,
                'score_security' => 40,
            ])
            ->assertCreated();

        $this->assertGreaterThan(70, $created->json('data.weighted_score'));
        $this->assertSame(PriorityCategory::MustDo->value, $created->json('data.priority_category'));

        $id = $created->json('data.id');

        $this->withToken($token)
            ->postJson("/api/v1/admin/work-requests/{$id}/convert")
            ->assertOk()
            ->assertJsonPath('data.status', 'converted')
            ->assertJsonStructure(['project' => ['id', 'code', 'indicator']]);

        $this->assertDatabaseHas('projects', [
            'work_request_id' => $id,
            'workplan_indicator_id' => $indicator->id,
        ]);
    }

    public function test_weekly_monthly_and_quarterly_reports_can_be_filed(): void
    {
        $token = $this->token();
        $project = Project::factory()->create();

        foreach (['weekly', 'monthly', 'quarterly'] as $period) {
            $this->withToken($token)
                ->postJson('/api/v1/admin/status-reports', [
                    'project_id' => $period === 'monthly' ? null : $project->id,
                    'period' => $period,
                    'period_start' => now()->startOfMonth()->toDateString(),
                    'period_end' => now()->toDateString(),
                    'title' => ucfirst($period).' status',
                    'rag_status' => 'amber',
                    'summary' => 'Pilot progress against the operating model.',
                ])
                ->assertCreated()
                ->assertJsonPath('data.period', $period);
        }

        $this->assertSame(3, \App\Models\StatusReport::query()->count());
    }

    public function test_only_admin_can_delete_an_unlinked_indicator(): void
    {
        $indicator = WorkplanIndicator::factory()->create();
        $user = User::factory()->create(['is_admin' => false, 'is_active' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/admin/workplan-indicators/'.$indicator->id)
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/admin/workplan-indicators/'.$indicator->id)
            ->assertOk();

        $this->assertDatabaseMissing('workplan_indicators', ['id' => $indicator->id]);
    }
}
