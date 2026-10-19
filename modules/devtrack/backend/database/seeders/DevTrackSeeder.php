<?php

namespace Database\Seeders;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\NamedProjectRole;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\RagStatus;
use App\Enums\RaidStatus;
use App\Enums\RaidType;
use App\Enums\ReportPeriod;
use App\Enums\SprintStatus;
use App\Enums\UrgencyLevel;
use App\Enums\WorkClass;
use App\Enums\WorkItemPriority;
use App\Enums\WorkItemStatus;
use App\Enums\WorkItemType;
use App\Enums\WorkRequestStatus;
use App\Models\Project;
use App\Models\RaidItem;
use App\Models\Sprint;
use App\Models\StatusReport;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkplanIndicator;
use App\Models\WorkRequest;
use App\Services\WorkRequestScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevTrackSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('is_admin', true)->orderBy('id')->first();
        $password = (string) env('ADMIN_PASSWORD', 'ChangeMe123!');

        $team = $this->seedTeam($admin, $password);
        $indicators = $this->seedIndicators();
        $projects = $this->seedProjects($indicators, $team, $admin);
        $this->seedRequests($indicators, $projects, $team, $admin);
        $this->seedSprintsAndWork($projects, $team, $admin);
        $this->seedRaidAndReports($projects, $team, $admin);
    }

    /**
     * @return array<string, User>
     */
    private function seedTeam(?User $admin, string $password): array
    {
        $people = [
            'lead' => ['name' => 'Amina Okonkwo', 'email' => 'amina.okonkwo@africacdc.org', 'admin' => false],
            'po' => ['name' => 'Kwame Mensah', 'email' => 'kwame.mensah@africacdc.org', 'admin' => false],
            'pm' => ['name' => 'Naledi Moyo', 'email' => 'naledi.moyo@africacdc.org', 'admin' => false],
            'tech' => ['name' => 'Joseph Kamau', 'email' => 'joseph.kamau@africacdc.org', 'admin' => false],
            'security' => ['name' => 'Fatima El-Hassan', 'email' => 'fatima.elhassan@africacdc.org', 'admin' => false],
            'owner' => ['name' => 'Chidi Nwosu', 'email' => 'chidi.nwosu@africacdc.org', 'admin' => false],
            'dev' => ['name' => 'Aisha Bello', 'email' => 'aisha.bello@africacdc.org', 'admin' => false],
            'qa' => ['name' => 'Daniel Tesfaye', 'email' => 'daniel.tesfaye@africacdc.org', 'admin' => false],
        ];

        $users = [];
        foreach ($people as $key => $person) {
            $users[$key] = User::query()->updateOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'password' => Hash::make($password),
                    'is_admin' => $person['admin'],
                    'is_active' => true,
                    'approval_status' => \App\Enums\UserApprovalStatus::Approved,
                    'approved_at' => now(),
                    'totp_required' => false,
                ],
            );
        }

        if ($admin) {
            $users['admin'] = $admin;
        }

        return $users;
    }

    /**
     * @return array<string, WorkplanIndicator>
     */
    private function seedIndicators(): array
    {
        $rows = [
            'platforms' => [
                'code' => 'WP-2026-SD-01',
                'title' => 'Strengthen digital public-health platforms',
                'description' => 'Deliver and maintain enterprise and programme platforms used by Africa CDC directorates.',
                'programme' => 'Digital Transformation',
                'target' => '4 platforms in production',
                'unit' => 'platforms',
            ],
            'reporting' => [
                'code' => 'WP-2026-SD-02',
                'title' => 'Improve outbreak reporting timeliness',
                'description' => 'Reduce time from event detection to authorised situation report through digital workflows.',
                'programme' => 'Surveillance and Response',
                'target' => '< 24 hours',
                'unit' => 'hours',
            ],
            'lab' => [
                'code' => 'WP-2026-SD-03',
                'title' => 'Expand laboratory information systems',
                'description' => 'Connect laboratory networks and improve result exchange with continental platforms.',
                'programme' => 'Laboratory Systems',
                'target' => '12 laboratories onboarded',
                'unit' => 'laboratories',
            ],
            'mis' => [
                'code' => 'WP-2026-SD-04',
                'title' => 'Deliver enterprise MIS applications',
                'description' => 'Provide workflow, notifications and management information systems for headquarters operations.',
                'programme' => 'Management Information Systems',
                'target' => '6 MIS modules live',
                'unit' => 'modules',
            ],
            'exchange' => [
                'code' => 'WP-2026-SD-05',
                'title' => 'Integrate surveillance data exchange',
                'description' => 'Establish secure APIs and data exchange with Member States and partner systems.',
                'programme' => 'Data and Analytics',
                'target' => '8 integrations operational',
                'unit' => 'integrations',
            ],
            'devsecops' => [
                'code' => 'WP-2026-SD-06',
                'title' => 'Institutionalize DevSecOps and secure delivery',
                'description' => 'One intake channel, named ownership, two-week sprints, and evidence at every release gate.',
                'programme' => 'Software Development',
                'target' => '100% of new work in the register',
                'unit' => 'percent',
            ],
            'support' => [
                'code' => 'WP-2026-SD-07',
                'title' => 'Improve service availability and support handover',
                'description' => 'Controlled release, hypercare, runbooks and named application owners after go-live.',
                'programme' => 'ICT Operations',
                'target' => '99.5% availability',
                'unit' => 'percent',
            ],
            'adoption' => [
                'code' => 'WP-2026-SD-08',
                'title' => 'Increase user adoption of digital tools',
                'description' => 'Training, UAT participation and measurable use of delivered applications.',
                'programme' => 'Digital Transformation',
                'target' => '80% active users',
                'unit' => 'percent',
            ],
        ];

        $indicators = [];
        foreach ($rows as $key => $row) {
            $indicators[$key] = WorkplanIndicator::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'directorate' => 'MIS / Software Development',
                    'programme' => $row['programme'],
                    'year' => 2026,
                    'target' => $row['target'],
                    'unit' => $row['unit'],
                    'is_active' => true,
                ],
            );
        }

        return $indicators;
    }

    /**
     * @param  array<string, WorkplanIndicator>  $indicators
     * @param  array<string, User>  $team
     * @return array<string, Project>
     */
    private function seedProjects(array $indicators, array $team, ?User $admin): array
    {
        $definitions = [
            'devtrack' => [
                'code' => 'PRJ-2026-0001',
                'name' => 'Africa CDC DevTrack',
                'description' => 'Portfolio register, intake, assignment and reporting for the Software Development Team.',
                'work_class' => WorkClass::MediumProject,
                'indicator' => 'devsecops',
                'stage' => ProjectStage::Development,
                'rag' => RagStatus::Green,
                'reason' => 'Pilot is on track. Dashboard and intake are in active development.',
                'priority' => PriorityCategory::MustDo,
                'sprint_goal' => 'Make every request, project and task visible in one workspace.',
                'readiness' => DeploymentReadiness::InProgress,
                'classification' => DataClassification::Internal,
                'start' => now()->subWeeks(6)->toDateString(),
                'end' => now()->addMonths(2)->toDateString(),
                'scope' => 'Intake, portfolio, work assignment, RAID, weekly/monthly/quarterly reports, workplan indicator linkage.',
                'success' => 'All new software work is registered; management sees one RAG dashboard; named roles exist on every project.',
                'resources' => 'Software team assigned; Product Owner nominated.',
                'budget' => 'Internal delivery — no additional licence cost in the pilot.',
            ],
            'surveillance' => [
                'code' => 'PRJ-2026-0002',
                'name' => 'Event-based surveillance dashboard',
                'description' => 'Continental dashboard for signals, verification and authorised situation reporting.',
                'work_class' => WorkClass::MediumProject,
                'indicator' => 'reporting',
                'stage' => ProjectStage::UserAcceptance,
                'rag' => RagStatus::Amber,
                'reason' => 'UAT delayed by Member State data-sharing decisions.',
                'priority' => PriorityCategory::HighPriority,
                'sprint_goal' => 'Close remaining UAT defects on the verification workflow.',
                'readiness' => DeploymentReadiness::InProgress,
                'classification' => DataClassification::Health,
                'start' => now()->subMonths(4)->toDateString(),
                'end' => now()->addWeeks(6)->toDateString(),
                'scope' => 'Signal capture, verification, mapping, weekly epidemiological outputs.',
                'success' => 'Situation reports generated within 24 hours of verified events.',
                'resources' => 'One developer and QA shared with LIS; Product Owner in Surveillance.',
                'budget' => 'Funded under surveillance programme.',
            ],
            'lis' => [
                'code' => 'PRJ-2026-0003',
                'name' => 'Laboratory information exchange',
                'description' => 'Secure result exchange between national laboratories and Africa CDC platforms.',
                'work_class' => WorkClass::MajorProject,
                'indicator' => 'lab',
                'stage' => ProjectStage::RequirementsDesign,
                'rag' => RagStatus::Red,
                'reason' => 'Architecture and data-classification decisions are outstanding; Security Champion has paused build.',
                'priority' => PriorityCategory::MustDo,
                'sprint_goal' => 'Complete threat model and integration design before any build.',
                'readiness' => DeploymentReadiness::NotReady,
                'classification' => DataClassification::Restricted,
                'start' => now()->subWeeks(3)->toDateString(),
                'end' => now()->addMonths(8)->toDateString(),
                'scope' => 'HL7/FHIR mapping, identity, environments, onboarding of 12 laboratories.',
                'success' => '12 laboratories exchanging results; no production access for developers.',
                'resources' => 'Technical Lead plus data engineer; vendor interface pending.',
                'budget' => 'Steering committee to confirm hosting cost.',
            ],
            'notifications' => [
                'code' => 'PRJ-2026-0004',
                'name' => 'Staff portal notifications gateway',
                'description' => 'Exchange/SMTP notification service used by enterprise applications.',
                'work_class' => WorkClass::MinorEnhancement,
                'indicator' => 'mis',
                'stage' => ProjectStage::ContinuousImprovement,
                'rag' => RagStatus::Green,
                'reason' => 'In production with named application owner and hypercare complete.',
                'priority' => PriorityCategory::Planned,
                'sprint_goal' => 'Clear remaining operational defects and access reviews.',
                'readiness' => DeploymentReadiness::Ready,
                'classification' => DataClassification::Confidential,
                'start' => now()->subMonths(8)->toDateString(),
                'end' => now()->subWeeks(2)->toDateString(),
                'scope' => 'Provider management, JWT integrations, audit, 2FA.',
                'success' => 'Applications send mail through the gateway with an audit trail.',
                'resources' => 'Application Owner in MIS operations; light developer support.',
                'budget' => 'Operating cost only.',
            ],
            'mobile' => [
                'code' => 'PRJ-2026-0005',
                'name' => 'Field outbreak reporting app',
                'description' => 'Mobile application for field teams to submit structured outbreak reports.',
                'work_class' => WorkClass::MajorProject,
                'indicator' => 'platforms',
                'stage' => ProjectStage::Initiation,
                'rag' => RagStatus::Amber,
                'reason' => 'Charter approved; security requirements and device-management approach still open.',
                'priority' => PriorityCategory::HighPriority,
                'sprint_goal' => 'Finish kick-off package and named roles before sprint 1.',
                'readiness' => DeploymentReadiness::NotReady,
                'classification' => DataClassification::Health,
                'start' => now()->toDateString(),
                'end' => now()->addMonths(9)->toDateString(),
                'scope' => 'Android/iOS reporting, offline capture, photo evidence, sync to surveillance.',
                'success' => 'Field teams in two RCCs using the app for weekly reports.',
                'resources' => 'Need a mobile developer; currently combined with Technical Lead.',
                'budget' => 'Device and store publishing costs not yet approved.',
            ],
            'dhis' => [
                'code' => 'PRJ-2026-0006',
                'name' => 'DHIS2 data exchange',
                'description' => 'API integration for selected DHIS2 aggregate indicators into Africa CDC analytics.',
                'work_class' => WorkClass::MediumProject,
                'indicator' => 'exchange',
                'stage' => ProjectStage::Development,
                'rag' => RagStatus::Green,
                'reason' => 'First two country mappings in review; no critical defects.',
                'priority' => PriorityCategory::Planned,
                'sprint_goal' => 'Complete mapping for the third country dataset.',
                'readiness' => DeploymentReadiness::NotReady,
                'classification' => DataClassification::Confidential,
                'start' => now()->subMonths(2)->toDateString(),
                'end' => now()->addMonths(3)->toDateString(),
                'scope' => 'Secure API, mapping, scheduled pull, data-quality checks.',
                'success' => 'Eight operational exchanges with documented data dictionaries.',
                'resources' => 'Data engineer with developer support.',
                'budget' => 'Covered by data programme.',
            ],
        ];

        $projects = [];
        foreach ($definitions as $key => $row) {
            $project = Project::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'work_class' => $row['work_class'],
                    'workplan_indicator_id' => $indicators[$row['indicator']]->id,
                    'stage' => $row['stage'],
                    'rag_status' => $row['rag'],
                    'rag_reason' => $row['reason'],
                    'priority_category' => $row['priority'],
                    'status' => ProjectStatus::InProgress,
                    'start_date' => $row['start'],
                    'target_end_date' => $row['end'],
                    'sprint_goal' => $row['sprint_goal'],
                    'budget_status' => $row['budget'],
                    'resource_status' => $row['resources'],
                    'uat_progress_percent' => $key === 'surveillance' ? 62 : ($key === 'notifications' ? 100 : 0),
                    'deployment_readiness' => $row['readiness'],
                    'data_classification' => $row['classification'],
                    'scope' => $row['scope'],
                    'modules' => [$row['name']],
                    'success_measures' => $row['success'],
                    'created_by' => $admin?->id ?? $team['lead']->id,
                ],
            );

            $roleMap = [
                NamedProjectRole::Sponsor->value => $team['lead']->id,
                NamedProjectRole::ProductOwner->value => $team['po']->id,
                NamedProjectRole::ProjectManager->value => $team['pm']->id,
                NamedProjectRole::TechnicalLead->value => $team['tech']->id,
                NamedProjectRole::SecurityChampion->value => $team['security']->id,
                NamedProjectRole::ApplicationOwner->value => $team['owner']->id,
            ];

            $project->roleAssignments()->delete();
            foreach ($roleMap as $role => $userId) {
                $project->roleAssignments()->create([
                    'role' => $role,
                    'user_id' => $userId,
                ]);
            }

            if ($project->stageEvents()->doesntExist()) {
                $project->stageEvents()->create([
                    'from_stage' => ProjectStage::Intake,
                    'to_stage' => $row['stage'],
                    'approved_by' => $admin?->id ?? $team['lead']->id,
                    'notes' => 'Migrated into the DevTrack portfolio register.',
                ]);
            }

            $projects[$key] = $project;
        }

        return $projects;
    }

    /**
     * @param  array<string, WorkplanIndicator>  $indicators
     * @param  array<string, Project>  $projects
     * @param  array<string, User>  $team
     */
    private function seedRequests(array $indicators, array $projects, array $team, ?User $admin): void
    {
        $scoring = app(WorkRequestScoringService::class);

        $rows = [
            [
                'reference' => 'REQ-2026-0001',
                'title' => 'Single intake channel for software work',
                'unit' => 'MIS / Software Development',
                'problem' => 'Requests arrive by email, chat and hallway conversations, so work is invisible.',
                'class' => WorkClass::MediumProject,
                'indicator' => 'devsecops',
                'project' => 'devtrack',
                'status' => WorkRequestStatus::Converted,
                'urgency' => UrgencyLevel::High,
                'scores' => [90, 85, 80, 70, 75, 60, 40],
            ],
            [
                'reference' => 'REQ-2026-0002',
                'title' => 'Access for new RCC epidemiologist',
                'unit' => 'Surveillance',
                'problem' => 'New staff member needs role-based access to the surveillance dashboard.',
                'class' => WorkClass::ServiceRequest,
                'indicator' => 'adoption',
                'project' => null,
                'status' => WorkRequestStatus::Acknowledged,
                'urgency' => UrgencyLevel::Medium,
                'scores' => [40, 50, 70, 20, 90, 85, 30],
            ],
            [
                'reference' => 'REQ-2026-0003',
                'title' => 'Production login failure on staff portal',
                'unit' => 'ICT Operations',
                'problem' => 'Intermittent 500 errors on login after the last configuration change.',
                'class' => WorkClass::Incident,
                'indicator' => 'support',
                'project' => 'notifications',
                'status' => WorkRequestStatus::Classified,
                'urgency' => UrgencyLevel::Critical,
                'scores' => [60, 90, 95, 80, 70, 50, 55],
            ],
            [
                'reference' => 'REQ-2026-0004',
                'title' => 'AI prototype for signal clustering',
                'unit' => 'Data and Analytics',
                'problem' => 'Analysts spend too long grouping similar rumours; a time-boxed experiment is requested.',
                'class' => WorkClass::Research,
                'indicator' => 'reporting',
                'project' => null,
                'status' => WorkRequestStatus::Submitted,
                'urgency' => UrgencyLevel::Low,
                'scores' => [55, 60, 20, 40, 45, 40, 70],
            ],
        ];

        foreach ($rows as $row) {
            $request = WorkRequest::query()->updateOrCreate(
                ['reference' => $row['reference']],
                [
                    'title' => $row['title'],
                    'requesting_unit' => $row['unit'],
                    'requester_name' => $team['po']->name,
                    'requester_email' => $team['po']->email,
                    'requester_id' => $team['po']->id,
                    'business_owner' => $team['po']->name,
                    'problem_statement' => $row['problem'],
                    'expected_outcomes' => 'Registered, owned and visible work with acceptance criteria.',
                    'success_measures' => 'Request acknowledged and classified within two working days.',
                    'target_users' => 'Africa CDC software team and requesting units',
                    'urgency' => $row['urgency'],
                    'data_classification' => DataClassification::Internal,
                    'work_class' => $row['class'],
                    'status' => $row['status'],
                    'workplan_indicator_id' => $indicators[$row['indicator']]->id,
                    'project_id' => $row['project'] ? $projects[$row['project']]->id : null,
                    'created_by' => $admin?->id ?? $team['lead']->id,
                    'score_strategic_alignment' => $row['scores'][0],
                    'score_impact' => $row['scores'][1],
                    'score_urgency_risk' => $row['scores'][2],
                    'score_user_reach' => $row['scores'][3],
                    'score_feasibility' => $row['scores'][4],
                    'score_cost_effort' => $row['scores'][5],
                    'score_security' => $row['scores'][6],
                    'acknowledged_by' => $team['lead']->id,
                    'acknowledged_at' => now()->subDays(2),
                ],
            );
            $scoring->apply($request);
            $request->save();
        }
    }

    /**
     * @param  array<string, Project>  $projects
     * @param  array<string, User>  $team
     */
    private function seedSprintsAndWork(array $projects, array $team, ?User $admin): void
    {
        $sprint = Sprint::query()->updateOrCreate(
            ['name' => 'Sprint 12 — visibility'],
            [
                'project_id' => $projects['devtrack']->id,
                'goal' => 'Portfolio dashboard, intake form and task assignment usable by the team.',
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->startOfWeek()->addDays(13)->toDateString(),
                'status' => SprintStatus::Active,
                'committed_points' => 21,
                'completed_points' => 8,
            ],
        );

        Sprint::query()->updateOrCreate(
            ['name' => 'Sprint 8 — UAT defects'],
            [
                'project_id' => $projects['surveillance']->id,
                'goal' => 'Clear P1 UAT defects on verification.',
                'start_date' => now()->subWeeks(2)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(2)->startOfWeek()->addDays(13)->toDateString(),
                'status' => SprintStatus::Active,
                'committed_points' => 13,
                'completed_points' => 5,
            ],
        );

        $items = [
            ['WI-2026-0001', 'devtrack', 'Register every software request in DevTrack', WorkItemType::Story, WorkItemStatus::Completed, WorkItemPriority::MustDo, 'dev', 8, null, 'Request has a reference, owner and indicator.'],
            ['WI-2026-0002', 'devtrack', 'Build portfolio RAG dashboard', WorkItemType::Story, WorkItemStatus::InProgress, WorkItemPriority::MustDo, 'dev', 13, $sprint->id, 'Management can see RAG, stage and next gate.'],
            ['WI-2026-0003', 'devtrack', 'Assign work down to the smallest task', WorkItemType::Story, WorkItemStatus::InProgress, WorkItemPriority::High, 'pm', 8, $sprint->id, 'Every task has an assignee, due date and status.'],
            ['WI-2026-0004', 'devtrack', 'Weekly, monthly and quarterly status reports', WorkItemType::Story, WorkItemStatus::NotStarted, WorkItemPriority::High, 'pm', 5, $sprint->id, 'Reports can be filed per project or for the portfolio.'],
            ['WI-2026-0005', 'devtrack', 'Link each project to a workplan indicator', WorkItemType::Task, WorkItemStatus::Completed, WorkItemPriority::MustDo, 'tech', 3, $sprint->id, 'No project can be saved without an indicator.'],
            ['WI-2026-0006', 'devtrack', 'Name the six project roles', WorkItemType::Task, WorkItemStatus::InProgress, WorkItemPriority::High, 'lead', 2, $sprint->id, 'Sponsor, PO, PM, Tech Lead, Security, App Owner recorded.'],
            ['WI-2026-0007', 'surveillance', 'Fix verification workflow empty-state', WorkItemType::Bug, WorkItemStatus::InProgress, WorkItemPriority::MustDo, 'dev', 5, null, 'Empty queues no longer throw a client error.'],
            ['WI-2026-0008', 'surveillance', 'UAT sign-off from Surveillance Product Owner', WorkItemType::Task, WorkItemStatus::InProgress, WorkItemPriority::High, 'po', 2, null, 'Written UAT acceptance on file.'],
            ['WI-2026-0009', 'lis', 'Complete threat model for laboratory exchange', WorkItemType::Spike, WorkItemStatus::InProgress, WorkItemPriority::MustDo, 'security', 8, null, 'Threat model reviewed by Security Focal Point.'],
            ['WI-2026-0010', 'lis', 'Document FHIR mapping for result messages', WorkItemType::Task, WorkItemStatus::NotStarted, WorkItemPriority::High, 'tech', 13, null, 'Mapping table approved by Technical Lead.'],
            ['WI-2026-0011', 'notifications', 'Quarterly repository and production access review', WorkItemType::Chore, WorkItemStatus::NotStarted, WorkItemPriority::Medium, 'security', 3, null, 'Access review completed and filed.'],
            ['WI-2026-0012', 'mobile', 'Draft project charter and RACI', WorkItemType::Task, WorkItemStatus::InProgress, WorkItemPriority::MustDo, 'pm', 5, null, 'Charter signed by Sponsor and Product Owner.'],
            ['WI-2026-0013', 'dhis', 'Country 3 indicator mapping', WorkItemType::Task, WorkItemStatus::InProgress, WorkItemPriority::Medium, 'dev', 8, null, 'Mapping reviewed; data-quality checks pass on sample.'],
            ['WI-2026-0014', 'devtrack', 'Write Definition of Ready and Done into project workspace', WorkItemType::Task, WorkItemStatus::NotStarted, WorkItemPriority::Medium, 'qa', 2, $sprint->id, 'Published DoR/DoD used in sprint planning.'],
            ['WI-2026-0015', 'surveillance', 'Prepare release rollback notes', WorkItemType::Task, WorkItemStatus::NotStarted, WorkItemPriority::High, 'tech', 4, null, 'Rollback tested in UAT.'],
        ];

        foreach ($items as [$key, $projectKey, $title, $type, $status, $priority, $assigneeKey, $hours, $sprintId, $ac]) {
            WorkItem::query()->updateOrCreate(
                ['key' => $key],
                [
                    'project_id' => $projects[$projectKey]->id,
                    'sprint_id' => $sprintId,
                    'title' => $title,
                    'description' => $title.'.',
                    'acceptance_criteria' => $ac,
                    'type' => $type,
                    'status' => $status,
                    'priority' => $priority,
                    'assignee_id' => $team[$assigneeKey]->id,
                    'reporter_id' => $admin?->id ?? $team['pm']->id,
                    'estimate_hours' => $hours,
                    'story_points' => max(1, (int) round($hours / 2)),
                    'due_date' => $key === 'WI-2026-0008'
                        ? now()->subDays(2)->toDateString()
                        : now()->addDays(rand(2, 18))->toDateString(),
                    'blocked_reason' => $key === 'WI-2026-0008'
                        ? 'Waiting for Product Owner UAT sign-off and data-sharing confirmation.'
                        : null,
                    'completed_at' => $status === WorkItemStatus::Completed ? now()->subDays(3) : null,
                ],
            );
        }

        WorkItem::query()->updateOrCreate(
            ['key' => 'WI-2026-0016'],
            [
                'project_id' => $projects['devtrack']->id,
                'parent_id' => WorkItem::query()->where('key', 'WI-2026-0003')->value('id'),
                'title' => 'Add subtask statuses for blocked work',
                'description' => 'Smallest unit of work under the assignment story.',
                'acceptance_criteria' => 'A blocked task shows a reason and an owner.',
                'type' => WorkItemType::Subtask,
                'status' => WorkItemStatus::NotStarted,
                'priority' => WorkItemPriority::Medium,
                'assignee_id' => $team['dev']->id,
                'reporter_id' => $team['pm']->id,
                'estimate_hours' => 2,
                'due_date' => now()->addDays(4)->toDateString(),
            ],
        );
    }

    /**
     * @param  array<string, Project>  $projects
     * @param  array<string, User>  $team
     */
    private function seedRaidAndReports(array $projects, array $team, ?User $admin): void
    {
        $raid = [
            [$projects['lis']->id, RaidType::Risk, 'Unresolved data classification for laboratory results', UrgencyLevel::Critical, RaidStatus::Open, 'security', 'Complete classification and threat model before development.'],
            [$projects['surveillance']->id, RaidType::Issue, 'UAT delayed by Member State data-sharing decisions', UrgencyLevel::High, RaidStatus::Mitigating, 'po', 'Escalate to Sponsor; offer synthetic data for remaining tests.'],
            [$projects['mobile']->id, RaidType::Assumption, 'Field teams have managed devices with store access', UrgencyLevel::Medium, RaidStatus::Open, 'owner', 'Confirm device-management policy with ICT operations.'],
            [$projects['dhis']->id, RaidType::Dependency, 'Country 3 DHIS2 credentials from the data programme', UrgencyLevel::Medium, RaidStatus::Open, 'tech', 'Request credentials through the approved intake channel.'],
            [$projects['devtrack']->id, RaidType::Risk, 'Team still receiving hallway requests', UrgencyLevel::Medium, RaidStatus::Mitigating, 'lead', 'Team Lead will not assign unregistered work.'],
        ];

        foreach ($raid as [$projectId, $type, $title, $severity, $status, $ownerKey, $mitigation]) {
            RaidItem::query()->updateOrCreate(
                ['project_id' => $projectId, 'title' => $title],
                [
                    'type' => $type,
                    'description' => $title,
                    'severity' => $severity,
                    'status' => $status,
                    'owner_id' => $team[$ownerKey]->id,
                    'due_date' => now()->addDays(10)->toDateString(),
                    'mitigation' => $mitigation,
                ],
            );
        }

        StatusReport::query()->updateOrCreate(
            ['title' => 'DevTrack weekly status — week of '.now()->startOfWeek()->format('d M')],
            [
                'project_id' => $projects['devtrack']->id,
                'period' => ReportPeriod::Weekly,
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->startOfWeek()->addDays(6)->toDateString(),
                'rag_status' => RagStatus::Green,
                'summary' => 'Intake, indicator linkage and assignment are in place. Remaining work is the management dashboard and report cadence.',
                'accomplishments' => "Portfolio register created.\nSix named roles assigned on active projects.\nDummy workplan indicators loaded pending official workplan codes.",
                'next_period_plan' => 'Complete weekly/monthly/quarterly reporting screens and migrate remaining hallway work into intake.',
                'blockers' => 'None this week.',
                'decisions_required' => 'Confirm official 2026 workplan indicator codes when the workplan is issued.',
                'submitted_by' => $team['pm']->id,
                'submitted_at' => now()->subDay(),
            ],
        );

        StatusReport::query()->updateOrCreate(
            ['title' => 'Software portfolio — September 2026'],
            [
                'project_id' => null,
                'period' => ReportPeriod::Monthly,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'rag_status' => RagStatus::Amber,
                'summary' => 'Six active pieces of work. Laboratory exchange is red pending architecture and security decisions. Surveillance is in UAT.',
                'accomplishments' => "DevTrack pilot started.\nNotifications gateway in continuous improvement.\nDHIS2 mapping progressing.",
                'next_period_plan' => 'Unblock laboratory threat model. Complete surveillance UAT. Close DevTrack 90-day foundation items.',
                'blockers' => 'Laboratory data classification; mobile device-management policy.',
                'decisions_required' => 'Steering committee to confirm laboratory hosting and residual-risk authority.',
                'submitted_by' => $team['lead']->id,
                'submitted_at' => now()->subDays(3),
            ],
        );

        StatusReport::query()->updateOrCreate(
            ['title' => 'Q3 2026 software posture'],
            [
                'project_id' => null,
                'period' => ReportPeriod::Quarterly,
                'period_start' => now()->firstOfQuarter()->toDateString(),
                'period_end' => now()->lastOfQuarter()->toDateString(),
                'rag_status' => RagStatus::Amber,
                'summary' => 'The operating model is being piloted. Delivery is becoming visible; security gates are not yet consistent on major work.',
                'accomplishments' => 'Named roles, intake form and two-week sprints introduced on live projects.',
                'next_period_plan' => 'Apply the model to all newly approved projects; first access review; release checklists in use.',
                'blockers' => 'Capacity for a dedicated mobile developer.',
                'decisions_required' => 'Approve the 90-day DevSecOps implementation plan and two pilot projects.',
                'submitted_by' => $team['lead']->id,
                'submitted_at' => now()->subWeeks(2),
            ],
        );
    }
}
