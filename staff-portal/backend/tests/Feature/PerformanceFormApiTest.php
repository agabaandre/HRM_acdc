<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Services\CsvExportService;
use Modules\Core\Services\PdfService;
use Modules\Performance\Http\Controllers\Api\V1\PerformanceFormApiController;
use Modules\Performance\Http\Controllers\Api\V1\PerformanceHubApiController;
use Modules\Performance\Enums\PerformancePhase;
use Modules\Performance\Services\CompetencyService;
use Modules\Performance\Services\PerformanceAnalyticsService;
use Modules\Performance\Services\PerformanceApprovalService;
use Modules\Performance\Services\PerformanceService;
use Modules\Performance\Services\PerformanceWorkflowService;
use Modules\Performance\Services\PpaContractService;
use Modules\Performance\Services\PpaFormService;
use Modules\Performance\Services\PpaSettingsService;
use Modules\Performance\Services\SupervisorResolver;
use Modules\Performance\Support\PerformancePeriod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PerformanceFormApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);
        DB::purge();
        DB::reconnect();

        $this->withoutMiddleware();
        $this->createTables();
        $this->seedFixtures();
    }

    public function test_create_bootstrap_returns_a_new_ppa_payload_for_the_owner(): void
    {
        $period = 'January 2026 to December 2026';
        $entryId = app(PpaFormService::class)->entryIdFor(100, PerformancePeriod::toSlug($period) ?? '');

        session()->put($this->portalSession(100));

        $response = app(PerformanceFormApiController::class)->create(
            Request::create('/api/v1/performance/entries', 'POST', [
                'period' => $period,
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
            app(SupervisorResolver::class),
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ppa', $payload['data']['phase']);
        $this->assertSame($entryId, $payload['data']['entry']['entry_id']);
        $this->assertSame(100, $payload['data']['entry']['staff_id']);
        $this->assertSame('January-2026-to-December-2026', $payload['data']['entry']['performance_period']);
        $this->assertSame(1000, $payload['data']['form']['staff_contract_id']);
        $this->assertSame(50, $payload['data']['form']['supervisor_id']);
        $this->assertSame(51, $payload['data']['form']['supervisor2_id']);
        $this->assertSame('No', $payload['data']['form']['training_recommended']);
        $this->assertTrue($payload['data']['can_save']);
        $this->assertSame(1000, $payload['data']['contract']['staff_contract_id']);
        $this->assertSame('Alice Supervisor', $payload['data']['contract']['first_supervisor_name']);
        $this->assertSame('Bob Supervisor', $payload['data']['contract']['second_supervisor_name']);
        $this->assertCount(5, $payload['data']['form']['objectives']);

        $this->assertDatabaseMissing('ppa_entries', [
            'entry_id' => $entryId,
        ]);
    }

    public function test_show_returns_existing_entry_payload_with_workflow_flags(): void
    {
        $this->insertPpaEntry([
            'staff_id' => 100,
            'staff_contract_id' => 1000,
            'performance_period' => 'January-2026-to-December-2026',
            'entry_id' => 'ppa-entry-100',
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'objectives' => json_encode([
                1 => [
                    'objective' => 'Ship staffing dashboard',
                    'timeline' => 'Q1',
                    'indicator' => 'Go live',
                    'weight' => 100,
                    'self_appraisal' => '',
                    'appraiser_rating' => '',
                ],
            ]),
            'required_skills' => json_encode([1]),
            'training_recommended' => 'Yes',
            'draft_status' => 1,
        ]);

        session()->put($this->portalSession(100));

        $response = app(PerformanceFormApiController::class)->show(
            'ppa-entry-100',
            Request::create('/api/v1/performance/entries/ppa-entry-100', 'GET', [
                'phase' => 'ppa',
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ppa', $payload['data']['phase']);
        $this->assertSame('ppa-entry-100', $payload['data']['entry']['entry_id']);
        $this->assertSame(100, $payload['data']['form']['staff_id']);
        $this->assertSame(50, $payload['data']['form']['supervisor_id']);
        $this->assertSame('Alice Supervisor', $payload['data']['contract']['first_supervisor_name']);
        $this->assertSame('Bob Supervisor', $payload['data']['contract']['second_supervisor_name']);
        $this->assertSame(1, $payload['data']['form']['required_skills'][0]);
        $this->assertSame('Yes', $payload['data']['form']['training_recommended']);
        $this->assertSame('Ship staffing dashboard', $payload['data']['form']['objectives'][1]['objective']);
        $this->assertSame('', $payload['data']['readonly']);
        $this->assertTrue($payload['data']['can_save']);
        $this->assertFalse($payload['data']['can_approve']);
        $this->assertFalse($payload['data']['can_return']);
        $this->assertFalse($payload['data']['can_consent']);
        $this->assertSame('draft', $payload['data']['workflow']['state']['step']);
        $this->assertCount(1, $payload['data']['catalogs']['skills']);
    }

    public function test_show_midterm_resolves_supervisor_names_not_ids(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-midterm-names',
            'staff_id' => 100,
            'staff_contract_id' => 1000,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'midterm_supervisor_1' => 50,
            'midterm_supervisor_2' => 51,
            'draft_status' => 2,
            'staff_sign_off' => 1,
        ]);

        session()->put($this->portalSession(100));

        $response = app(PerformanceFormApiController::class)->show(
            'ppa-entry-midterm-names',
            Request::create('/api/v1/performance/entries/ppa-entry-midterm-names', 'GET', [
                'phase' => 'midterm',
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('midterm', $payload['data']['phase']);
        $this->assertSame(50, $payload['data']['form']['supervisor_id']);
        $this->assertSame(51, $payload['data']['form']['supervisor2_id']);
        $this->assertSame('Alice Supervisor', $payload['data']['contract']['first_supervisor_name']);
        $this->assertSame('Bob Supervisor', $payload['data']['contract']['second_supervisor_name']);
        $this->assertIsInt($payload['data']['contract']['first_supervisor']);
        $this->assertIsInt($payload['data']['contract']['second_supervisor']);
    }

    public function test_decode_skill_ids_casts_quoted_json_ids_to_integers(): void
    {
        $forms = app(PpaFormService::class);

        $this->assertSame([17, 44], $forms->decodeSkillIds('["17","44"]'));
        $this->assertSame([17, 44], $forms->decodeSkillIds([17, '44', '17']));
        $this->assertSame([], $forms->decodeSkillIds('[]'));
    }

    public function test_show_can_return_requires_permission_and_setting(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-show-return',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 0,
            'staff_sign_off' => 1,
        ]);

        session()->put($this->portalSession(50, permissions: [74]));

        $response = app(PerformanceFormApiController::class)->show(
            'ppa-entry-show-return',
            Request::create('/api/v1/performance/entries/ppa-entry-show-return', 'GET', [
                'phase' => 'ppa',
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $payload = $response->getData(true);

        $this->assertFalse($payload['data']['can_return']);

        DB::table('ppa_configs')->update(['allow_supervisor_return' => 1]);
        session()->put($this->portalSession(50, permissions: [74, 83]));

        $response = app(PerformanceFormApiController::class)->show(
            'ppa-entry-show-return',
            Request::create('/api/v1/performance/entries/ppa-entry-show-return', 'GET', [
                'phase' => 'ppa',
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $payload = $response->getData(true);

        $this->assertTrue($payload['data']['can_return']);

        DB::table('ppa_configs')->update(['allow_supervisor_return' => 0]);

        $response = app(PerformanceFormApiController::class)->show(
            'ppa-entry-show-return',
            Request::create('/api/v1/performance/entries/ppa-entry-show-return', 'GET', [
                'phase' => 'ppa',
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $payload = $response->getData(true);

        $this->assertFalse($payload['data']['can_return']);
    }

    public function test_hr_admin_can_return_submitted_ppa_without_being_supervisor(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-hr-return',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 0,
            'staff_sign_off' => 1,
        ]);

        session()->put($this->portalSession(999, 22, [74, 83]));

        $payload = $this->showEntry('ppa-entry-hr-return');

        $this->assertFalse($payload['can_approve']);
        $this->assertTrue($payload['can_return']);
        $this->assertSame('employee', $payload['return_target']);

        $response = app(PerformanceFormApiController::class)->returnEntry(
            'ppa-entry-hr-return',
            Request::create('/api/v1/performance/entries/ppa-entry-hr-return/return', 'POST', [
                'phase' => 'ppa',
                'comments' => 'HR sending this back to the employee.',
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, (int) DB::table('ppa_entries')->where('entry_id', 'ppa-entry-hr-return')->value('draft_status'));
        $this->assertSame(
            'Returned',
            DB::table('ppa_approval_trail')->where('entry_id', 'ppa-entry-hr-return')->where('staff_id', 999)->value('action')
        );
    }

    public function test_hr_admin_can_return_approved_ppa_to_draft(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-hr-draft',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 2,
            'staff_sign_off' => 1,
        ]);
        DB::table('ppa_approval_trail')->insert([
            'entry_id' => 'ppa-entry-hr-draft',
            'staff_id' => 50,
            'comments' => 'Looks good',
            'action' => 'Approved',
            'created_at' => now()->toDateTimeString(),
        ]);

        session()->put($this->portalSession(999, 22, [74, 83]));

        $payload = $this->showEntry('ppa-entry-hr-draft');

        $this->assertTrue($payload['can_return']);
        $this->assertSame('draft', $payload['return_target']);

        app(PerformanceFormApiController::class)->returnEntry(
            'ppa-entry-hr-draft',
            Request::create('/api/v1/performance/entries/ppa-entry-hr-draft/return', 'POST', [
                'phase' => 'ppa',
                'comments' => 'Reopening this approved PPA.',
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $this->assertSame(1, (int) DB::table('ppa_entries')->where('entry_id', 'ppa-entry-hr-draft')->value('draft_status'));

        DB::table('ppa_entries')->where('entry_id', 'ppa-entry-hr-draft')->update(['draft_status' => 0]);
        $entry = DB::table('ppa_entries')->where('entry_id', 'ppa-entry-hr-draft')->first();
        $state = app(PerformanceWorkflowService::class)->resolveState($entry, PerformancePhase::Ppa);

        $this->assertSame('supervisor_1', $state['step']);
        $this->assertSame(50, $state['actor_staff_id']);
    }

    public function test_show_does_not_offer_return_while_ppa_is_still_draft(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-hr-still-draft',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'draft_status' => 1,
        ]);

        session()->put($this->portalSession(999, 22, [74, 83]));

        $payload = $this->showEntry('ppa-entry-hr-still-draft');

        $this->assertFalse($payload['can_return']);
        $this->assertNull($payload['return_target']);
    }

    public function test_hub_response_exposes_only_spa_edit_urls(): void
    {
        session()->put($this->portalSession(100));

        $performance = \Mockery::mock(PerformanceService::class);
        $performance->shouldReceive('currentPeriodSlug')->once()->andReturn('January-2026-to-December-2026');
        $performance->shouldReceive('dashboardSummary')->once()->with(null, 'January-2026-to-December-2026', 100)->andReturn([
            'total' => 1,
            'approved' => 0,
            'submitted' => 1,
            'draft' => 0,
            'without_ppa' => 0,
        ]);
        $performance->shouldReceive('periodOptions')->once()->andReturn(['January-2026-to-December-2026']);

        $approval = \Mockery::mock(PerformanceApprovalService::class);
        $approval->shouldReceive('pendingActionsFor')->once()->with(100)->andReturn(collect([
            (object) [
                'entry_id' => 'entry-1',
                'staff_id' => 100,
                'approval_type' => 'ppa',
            ],
        ]));

        $ppaSettings = \Mockery::mock(PpaSettingsService::class);
        $ppaSettings->shouldReceive('workflowSummaryLine')->times(3)->andReturn('Configured');
        $ppaSettings->shouldReceive('allSubmissionWindowStatuses')->once()->andReturn([
            'ppa' => ['open' => true],
            'midterm' => ['open' => false],
            'endterm' => ['open' => false],
        ]);
        $ppaSettings->shouldReceive('isSubmissionOpen')->once()->with(PerformancePhase::Ppa)->andReturn(true);

        $response = app(PerformanceHubApiController::class)->hub(
            Request::create('/api/v1/performance/hub', 'GET'),
            $performance,
            $approval,
            $ppaSettings,
        );

        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/performance/form/ppa/entry-1/100', $payload['data']['pending'][0]['form_url']);
        $this->assertArrayNotHasKey('livewire_url', $payload['data']['pending'][0]);
        $this->assertSame('/performance/create', $payload['data']['create_ppa_url']);
        $this->assertArrayNotHasKey('create_ppa_livewire_url', $payload['data']);
    }

    public function test_put_draft_saves_a_ppa_and_returns_the_saved_payload(): void
    {
        $period = 'January-2026-to-December-2026';
        $entryId = app(PpaFormService::class)->entryIdFor(100, $period);

        $payload = [
            'phase' => 'ppa',
            'staff_id' => 100,
            'performance_period' => $period,
            'staff_contract_id' => 1000,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'objectives' => [
                1 => [
                    'objective' => 'Complete API rollout',
                    'timeline' => 'Q2',
                    'indicator' => 'Routes live',
                    'weight' => 100,
                    'self_appraisal' => '',
                    'appraiser_rating' => '',
                ],
            ],
            'training_recommended' => 'Yes',
            'required_skills' => [1],
            'training_contributions' => 'Coached backend team',
            'recommended_trainings' => 'Advanced API design',
            'recommended_trainings_details' => 'Internal workshop',
            'comments' => 'Draft note',
        ];

        session()->put($this->portalSession(100));

        $response = app(PerformanceFormApiController::class)->update(
            $entryId,
            Request::create("/api/v1/performance/entries/{$entryId}", 'PUT', $payload),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
            app(SupervisorResolver::class),
        );

        $responsePayload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($entryId, $responsePayload['data']['entry']['entry_id']);
        $this->assertSame('Complete API rollout', $responsePayload['data']['form']['objectives'][1]['objective']);
        $this->assertSame('Yes', $responsePayload['data']['form']['training_recommended']);
        $this->assertTrue($responsePayload['data']['can_save']);
        $this->assertSame('draft', $responsePayload['data']['workflow']['state']['step']);

        $this->assertDatabaseHas('ppa_entries', [
            'entry_id' => $entryId,
            'staff_id' => 100,
            'staff_contract_id' => 1000,
            'performance_period' => $period,
            'draft_status' => 1,
            'training_recommended' => 'Yes',
            'training_contributions' => 'Coached backend team',
            'recommended_trainings' => 'Advanced API design',
            'recommended_trainings_details' => 'Internal workshop',
        ]);
    }

    public function test_print_entry_requires_same_entry_access_as_show(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-print',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
        ]);

        session()->put($this->portalSession(999, permissions: [74]));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You are not allowed to access this entry.');

        try {
            app(PerformanceFormApiController::class)->printEntry(
                'ppa-entry-print',
                Request::create('/api/v1/performance/entries/ppa-entry-print/print.pdf', 'GET', [
                    'phase' => 'ppa',
                ]),
                app(PpaFormService::class),
                new class extends PdfService
                {
                    public function inline(string $htmlBody, string $filename, array $options = []): Response
                    {
                        return response('fake-pdf', 200, ['Content-Type' => 'application/pdf']);
                    }
                },
                app(PerformanceWorkflowService::class),
                app(PerformanceService::class),
            );
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            throw $e;
        }
    }

    public function test_export_csv_restricts_role_17_staff_to_their_own_analytics(): void
    {
        session()->put($this->portalSession(100, 17, [74]));

        $analytics = \Mockery::mock(PerformanceAnalyticsService::class);
        $analytics->shouldReceive('dashboard')
            ->once()
            ->withArgs(fn (PerformancePhase $phase, ?int $divisionId, string $period, ?int $restrictStaffId): bool => $phase === PerformancePhase::Ppa
                && $divisionId === null
                && $period === 'January-2026-to-December-2026'
                && $restrictStaffId === 100)
            ->andReturn([
                'phase' => 'ppa',
                'phase_label' => 'PPA',
                'period' => 'January-2026-to-December-2026',
                'summary' => [
                    'total' => 1,
                    'approved' => 1,
                    'submitted' => 0,
                    'draft' => 0,
                    'without' => 0,
                ],
                'by_division' => [
                    (object) ['label' => 'People', 'value' => 1],
                ],
                'trend' => [],
                'create_url' => null,
            ]);

        $response = app(PerformanceFormApiController::class)->exportCsv(
            Request::create('/api/v1/performance/analytics/export.csv', 'GET', [
                'phase' => 'ppa',
                'period' => 'January-2026-to-December-2026',
            ]),
            $analytics,
            app(PerformanceService::class),
            app(CsvExportService::class),
        );

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Total,1', $csv);
        $this->assertStringContainsString('People,1', $csv);
    }

    public function test_return_entry_requires_permission_83(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-return-permission',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 0,
            'staff_sign_off' => 1,
        ]);

        session()->put($this->portalSession(50, permissions: [74]));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You are not allowed to return this entry.');

        try {
            app(PerformanceFormApiController::class)->returnEntry(
                'ppa-entry-return-permission',
                Request::create('/api/v1/performance/entries/ppa-entry-return-permission/return', 'POST', [
                    'phase' => 'ppa',
                    'comments' => 'Needs more detail.',
                ]),
                app(PpaFormService::class),
                app(PpaContractService::class),
                app(PpaSettingsService::class),
                app(CompetencyService::class),
                app(PerformanceWorkflowService::class),
                app(PerformanceApprovalService::class),
                app(PerformanceService::class),
            );
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            throw $e;
        }
    }

    public function test_return_entry_requires_supervisor_return_setting(): void
    {
        $this->insertPpaEntry([
            'entry_id' => 'ppa-entry-return-setting',
            'staff_id' => 100,
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'draft_status' => 0,
            'staff_sign_off' => 1,
        ]);
        DB::table('ppa_configs')->update(['allow_supervisor_return' => 0]);

        session()->put($this->portalSession(50, permissions: [74, 83]));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('You are not allowed to return this entry.');

        try {
            app(PerformanceFormApiController::class)->returnEntry(
                'ppa-entry-return-setting',
                Request::create('/api/v1/performance/entries/ppa-entry-return-setting/return', 'POST', [
                    'phase' => 'ppa',
                    'comments' => 'Needs more detail.',
                ]),
                app(PpaFormService::class),
                app(PpaContractService::class),
                app(PpaSettingsService::class),
                app(CompetencyService::class),
                app(PerformanceWorkflowService::class),
                app(PerformanceApprovalService::class),
                app(PerformanceService::class),
            );
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function showEntry(string $entryId, string $phase = 'ppa'): array
    {
        $response = app(PerformanceFormApiController::class)->show(
            $entryId,
            Request::create('/api/v1/performance/entries/'.$entryId, 'GET', [
                'phase' => $phase,
            ]),
            app(PpaFormService::class),
            app(PpaContractService::class),
            app(PpaSettingsService::class),
            app(CompetencyService::class),
            app(PerformanceWorkflowService::class),
            app(PerformanceApprovalService::class),
            app(PerformanceService::class),
        );

        $this->assertSame(200, $response->getStatusCode());

        return $response->getData(true)['data'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function portalSession(int $staffId, int $roleId = 17, array $permissions = [74]): array
    {
        return [
            'user' => [
                'staff_id' => $staffId,
                'role_id' => $roleId,
                'permissions' => $permissions,
            ],
        ];
    }

    protected function createTables(): void
    {
        Schema::create('staff', function (Blueprint $table): void {
            $table->integer('staff_id')->primary();
            $table->string('SAPNO')->nullable();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('photo')->nullable();
            $table->date('initiation_date')->nullable();
            $table->string('work_email')->nullable();
        });

        Schema::create('staff_contracts', function (Blueprint $table): void {
            $table->increments('staff_contract_id');
            $table->integer('staff_id');
            $table->integer('job_id')->nullable();
            $table->integer('job_acting_id')->nullable();
            $table->integer('division_id')->nullable();
            $table->integer('funder_id')->nullable();
            $table->integer('contract_type_id')->nullable();
            $table->integer('first_supervisor')->nullable();
            $table->integer('second_supervisor')->nullable();
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->integer('job_id')->primary();
            $table->string('job_name')->nullable();
        });

        Schema::create('jobs_acting', function (Blueprint $table): void {
            $table->integer('job_acting_id')->primary();
            $table->string('job_acting')->nullable();
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->integer('division_id')->primary();
            $table->string('division_name')->nullable();
        });

        Schema::create('funders', function (Blueprint $table): void {
            $table->integer('funder_id')->primary();
            $table->string('funder')->nullable();
        });

        Schema::create('contract_types', function (Blueprint $table): void {
            $table->integer('contract_type_id')->primary();
            $table->string('contract_type')->nullable();
        });

        Schema::create('training_skills', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('skill');
            $table->integer('category_id')->default(0);
        });

        Schema::create('au_values', function (Blueprint $table): void {
            $table->increments('id');
            $table->text('description');
            $table->text('annotation');
            $table->text('score_5');
            $table->text('score_4');
            $table->text('score_3');
            $table->text('score_2');
            $table->text('score_1');
            $table->string('category');
            $table->integer('version')->default(1);
        });

        Schema::create('ppa_configs', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('allow_supervisor_return')->default(1);
            $table->integer('allow_supervisor_comments')->default(1);
            $table->integer('allow_supervisor_ppa_edit')->default(1);
            $table->integer('allow_employee_comments')->default(1);
            $table->boolean('ppa_requires_second_supervisor')->default(false);
            $table->boolean('midterm_requires_second_supervisor')->default(false);
            $table->boolean('endterm_requires_second_supervisor')->default(true);
            $table->boolean('endterm_requires_employee_consent')->default(true);
            $table->date('ppa_start')->nullable();
            $table->date('ppa_deadline')->nullable();
            $table->date('mid_term_start')->nullable();
            $table->date('mid_term_deadline')->nullable();
            $table->date('end_term_start')->nullable();
            $table->date('end_term_deadline')->nullable();
        });

        Schema::create('ppa_entries', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('staff_id');
            $table->integer('staff_contract_id')->nullable();
            $table->string('performance_period', 50);
            $table->string('entry_id', 100)->unique();
            $table->integer('supervisor_id')->nullable();
            $table->integer('supervisor2_id')->nullable();
            $table->longText('objectives')->nullable();
            $table->string('training_recommended', 3)->default('No');
            $table->longText('required_skills')->nullable();
            $table->text('training_contributions')->nullable();
            $table->text('recommended_trainings')->nullable();
            $table->text('recommended_trainings_details')->nullable();
            $table->boolean('staff_sign_off')->default(false);
            $table->tinyInteger('draft_status')->default(1);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->longText('midterm_objectives')->nullable();
            $table->longText('midterm_competency')->nullable();
            $table->text('midterm_achievements')->nullable();
            $table->text('midterm_non_achievements')->nullable();
            $table->text('midterm_comments')->nullable();
            $table->text('midterm_training_review')->nullable();
            $table->longText('midterm_recommended_skills')->nullable();
            $table->longText('midterm_training_contributions')->nullable();
            $table->longText('midterm_recommended_trainings')->nullable();
            $table->longText('midterm_recommended_trainings_details')->nullable();
            $table->integer('midterm_rating_by')->nullable();
            $table->boolean('midterm_sign_off')->default(false);
            $table->tinyInteger('midterm_draft_status')->default(1);
            $table->dateTime('midterm_created_at')->nullable();
            $table->dateTime('midterm_updated_at')->nullable();
            $table->integer('midterm_supervisor_1')->nullable();
            $table->integer('midterm_supervisor_2')->nullable();
            $table->longText('endterm_objectives')->nullable();
            $table->longText('endterm_competency')->nullable();
            $table->text('endterm_achievements')->nullable();
            $table->text('endterm_non_achievements')->nullable();
            $table->text('endterm_comments')->nullable();
            $table->text('endterm_training_review')->nullable();
            $table->longText('endterm_recommended_skills')->nullable();
            $table->longText('endterm_training_contributions')->nullable();
            $table->longText('endterm_recommended_trainings')->nullable();
            $table->longText('endterm_recommended_trainings_details')->nullable();
            $table->integer('endterm_rating_by')->nullable();
            $table->boolean('endterm_sign_off')->default(false);
            $table->tinyInteger('endterm_draft_status')->default(1);
            $table->integer('endterm_supervisor_1')->nullable();
            $table->integer('endterm_supervisor_2')->nullable();
            $table->dateTime('endterm_created_at')->nullable();
            $table->dateTime('endterm_updated_at')->nullable();
            $table->boolean('endterm_supervisor1_discussion_confirmed')->default(false);
            $table->boolean('endterm_staff_discussion_confirmed')->default(false);
            $table->boolean('endterm_staff_rating_acceptance')->nullable();
            $table->dateTime('endterm_staff_consent_at')->nullable();
            $table->boolean('endterm_supervisor2_agreement')->nullable();
        });

        Schema::create('ppa_approval_trail', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('entry_id', 100);
            $table->integer('staff_id');
            $table->text('comments')->nullable();
            $table->string('action');
            $table->dateTime('created_at')->nullable();
        });

        Schema::create('ppa_approval_trail_midterm', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('entry_id', 100);
            $table->integer('staff_id');
            $table->text('comments')->nullable();
            $table->string('action');
            $table->dateTime('created_at')->nullable();
            $table->string('type', 20)->default('PPA');
        });

        Schema::create('ppa_approval_trail_end_term', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('entry_id', 255);
            $table->integer('staff_id');
            $table->text('comments')->nullable();
            $table->string('action');
            $table->dateTime('created_at');
            $table->string('type')->nullable();
        });
    }

    protected function seedFixtures(): void
    {
        DB::table('staff')->insert([
            [
                'staff_id' => 50,
                'SAPNO' => null,
                'fname' => 'Alice',
                'lname' => 'Supervisor',
                'initiation_date' => null,
                'work_email' => 'alice.supervisor@example.test',
            ],
            [
                'staff_id' => 51,
                'SAPNO' => null,
                'fname' => 'Bob',
                'lname' => 'Supervisor',
                'initiation_date' => null,
                'work_email' => 'bob.supervisor@example.test',
            ],
            [
                'staff_id' => 100,
                'fname' => 'Current',
                'lname' => 'Staff',
                'SAPNO' => 'AU-100',
                'initiation_date' => '2024-01-01',
                'work_email' => 'current.staff@example.test',
            ],
        ]);

        DB::table('jobs')->insert([
            'job_id' => 1,
            'job_name' => 'Advisor',
        ]);
        DB::table('jobs_acting')->insert([
            'job_acting_id' => 1,
            'job_acting' => 'Acting Advisor',
        ]);
        DB::table('divisions')->insert([
            'division_id' => 1,
            'division_name' => 'People',
        ]);
        DB::table('funders')->insert([
            'funder_id' => 1,
            'funder' => 'AU',
        ]);
        DB::table('contract_types')->insert([
            'contract_type_id' => 1,
            'contract_type' => 'Permanent',
        ]);
        DB::table('staff_contracts')->insert([
            'staff_contract_id' => 1000,
            'staff_id' => 100,
            'job_id' => 1,
            'job_acting_id' => 1,
            'division_id' => 1,
            'funder_id' => 1,
            'contract_type_id' => 1,
            'first_supervisor' => 50,
            'second_supervisor' => 51,
        ]);
        DB::table('training_skills')->insert([
            'id' => 1,
            'skill' => 'API design',
            'category_id' => 1,
        ]);
        DB::table('au_values')->insert([
            'id' => 1,
            'description' => 'Integrity',
            'annotation' => 'Acts with integrity',
            'score_5' => 'Always',
            'score_4' => 'Often',
            'score_3' => 'Sometimes',
            'score_2' => 'Rarely',
            'score_1' => 'Never',
            'category' => 'values',
            'version' => 1,
        ]);
        DB::table('ppa_configs')->insert([
            'id' => 1,
            'allow_supervisor_return' => 1,
            'allow_supervisor_comments' => 1,
            'allow_supervisor_ppa_edit' => 1,
            'allow_employee_comments' => 1,
            'ppa_requires_second_supervisor' => 0,
            'midterm_requires_second_supervisor' => 0,
            'endterm_requires_second_supervisor' => 1,
            'endterm_requires_employee_consent' => 1,
            'ppa_start' => '2026-01-01',
            'ppa_deadline' => '2026-12-31',
            'mid_term_start' => '2026-01-01',
            'mid_term_deadline' => '2026-12-31',
            'end_term_start' => '2026-01-01',
            'end_term_deadline' => '2026-12-31',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function insertPpaEntry(array $attributes): void
    {
        DB::table('ppa_entries')->insert(array_merge([
            'staff_id' => 100,
            'staff_contract_id' => 1000,
            'performance_period' => 'January-2026-to-December-2026',
            'entry_id' => 'ppa-entry-default',
            'supervisor_id' => 50,
            'supervisor2_id' => 51,
            'objectives' => json_encode([]),
            'training_recommended' => 'No',
            'required_skills' => json_encode([]),
            'staff_sign_off' => 1,
            'draft_status' => 1,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ], $attributes));
    }
}
