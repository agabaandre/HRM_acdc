<?php

namespace App\Services;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\RagStatus;
use App\Enums\UrgencyLevel;
use App\Enums\WorkClass;
use App\Enums\WorkRequestStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkplanIndicator;
use App\Models\WorkRequest;
use App\Support\DevTrackReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SystemsInventoryImportService
{
    /**
     * @var array<string, list<string>>
     */
    private const NAME_ALIASES = [
        'starlink fleet dashboard' => ['starlink dashboard'],
        'risk management tracker' => ['risk register'],
        'cphia admin portal' => ['cphia platform'],
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created_projects: int, updated_projects: int, created_requests: int, updated_requests: int}
     */
    public function import(array $rows, bool $dryRun = false): array
    {
        $admin = User::query()->where('is_admin', true)->orderBy('id')->first();
        $indicator = WorkplanIndicator::query()->orderBy('id')->first();
        if ($indicator === null) {
            throw new \RuntimeException('No workplan indicator found. Seed indicators before importing.');
        }

        $stats = [
            'created_projects' => 0,
            'updated_projects' => 0,
            'created_requests' => 0,
            'updated_requests' => 0,
        ];

        $claimedProjectIds = [];

        DB::transaction(function () use ($rows, $admin, $indicator, $dryRun, &$stats, &$claimedProjectIds): void {
            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $project = $this->findMatchingProject($name, $claimedProjectIds);
                if ($project !== null) {
                    $claimedProjectIds[] = $project->id;
                }

                $stage = $this->mapInventoryStatusToStage((string) ($row['inventory_status'] ?? ''));
                $inventoryBlock = $this->inventoryMetadataBlock($row);

                if ($project === null) {
                    if ($dryRun) {
                        $stats['created_projects']++;
                        $stats['created_requests']++;

                        continue;
                    }

                    $request = $this->createWorkRequest($name, $row, $indicator, $admin, $inventoryBlock);
                    $stats['created_requests']++;

                    $project = Project::query()->create([
                        'code' => DevTrackReference::next('PRJ', 'projects', 'code'),
                        'name' => $name,
                        'description' => $this->defaultDescription($name, $row),
                        'work_class' => $this->defaultWorkClass($stage),
                        'workplan_indicator_id' => $indicator->id,
                        'work_request_id' => $request->id,
                        'stage' => $stage,
                        'rag_status' => $this->defaultRag($stage),
                        'priority_category' => PriorityCategory::Planned,
                        'status' => ProjectStatus::InProgress,
                        'deployment_readiness' => $this->deploymentReadiness($stage),
                        'data_classification' => DataClassification::Internal,
                        'scope' => $inventoryBlock,
                        'resource_status' => $this->resourceStatusLine($row),
                        'created_by' => $admin?->id,
                        'start_date' => now()->toDateString(),
                    ]);

                    $request->project_id = $project->id;
                    $request->status = WorkRequestStatus::Converted;
                    $request->save();

                    $stats['created_projects']++;

                    continue;
                }

                if ($dryRun) {
                    $stats['updated_projects']++;
                    if ($project->work_request_id === null) {
                        $stats['created_requests']++;
                    } else {
                        $stats['updated_requests']++;
                    }

                    continue;
                }

                $updates = [
                    'stage' => $stage,
                    'deployment_readiness' => $this->deploymentReadiness($stage),
                ];

                if ($this->isBlank($project->description)) {
                    $updates['description'] = $this->defaultDescription($project->name, $row);
                }
                if ($this->isBlank($project->scope)) {
                    $updates['scope'] = $inventoryBlock;
                } else {
                    $updates['scope'] = $this->appendInventoryBlock($project->scope, $inventoryBlock);
                }
                if ($this->isBlank($project->resource_status)) {
                    $updates['resource_status'] = $this->resourceStatusLine($row);
                }

                $project->fill($updates);
                $project->save();
                $stats['updated_projects']++;

                $request = $project->workRequest;
                if ($request === null) {
                    $request = $this->createWorkRequest($project->name, $row, $indicator, $admin, $inventoryBlock);
                    $stats['created_requests']++;
                    $project->work_request_id = $request->id;
                    $project->save();
                } else {
                    $this->mergeWorkRequest($request, $row, $inventoryBlock);
                    $stats['updated_requests']++;
                }

                if ($request->project_id === null) {
                    $request->project_id = $project->id;
                }
                if ($request->status !== WorkRequestStatus::Converted) {
                    $request->status = WorkRequestStatus::Converted;
                }
                $request->save();
            }
        });

        return $stats;
    }

    public function mapInventoryStatusToStage(string $status): ProjectStage
    {
        $normalized = Str::lower(trim($status));

        return match (true) {
            str_contains($normalized, 'to be developed') => ProjectStage::Intake,
            str_contains($normalized, 'under testing') => ProjectStage::UserAcceptance,
            str_contains($normalized, 'in use') => ProjectStage::ContinuousImprovement,
            default => ProjectStage::Discovery,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function defaultDescription(string $name, array $row): string
    {
        $status = (string) ($row['inventory_status'] ?? 'Unknown');

        return "Africa CDC systems inventory: {$name} ({$status}).";
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function inventoryMetadataBlock(array $row): string
    {
        $lines = [
            'Source: Africa CDC Systems Inventory',
            'Inventory #: '.($row['inventory_number'] ?? '—'),
            'Inventory status: '.($row['inventory_status'] ?? '—'),
            'Version: '.($row['version'] ?? '—'),
            'Division: '.($row['division'] ?? '—'),
            'Languages / stack: '.($row['languages'] ?? '—'),
            'Business focal: '.($row['focal'] ?? '—'),
            'MIS focal: '.($row['mis_focal'] ?? '—'),
            'Modules: '.($row['modules'] ?? '—'),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resourceStatusLine(array $row): string
    {
        $focal = trim((string) ($row['focal'] ?? ''));
        $mis = trim((string) ($row['mis_focal'] ?? ''));
        $parts = [];
        if ($focal !== '' && $focal !== '—') {
            $parts[] = 'Business focal: '.$focal;
        }
        if ($mis !== '' && $mis !== '—') {
            $parts[] = 'MIS focal: '.$mis;
        }

        return $parts === [] ? 'See systems inventory for ownership.' : implode('; ', $parts);
    }

    private function defaultWorkClass(ProjectStage $stage): WorkClass
    {
        return $stage === ProjectStage::Intake
            ? WorkClass::MediumProject
            : WorkClass::MinorEnhancement;
    }

    private function defaultRag(ProjectStage $stage): RagStatus
    {
        return match ($stage) {
            ProjectStage::UserAcceptance => RagStatus::Amber,
            default => RagStatus::Green,
        };
    }

    private function deploymentReadiness(ProjectStage $stage): DeploymentReadiness
    {
        return match ($stage) {
            ProjectStage::ContinuousImprovement => DeploymentReadiness::Ready,
            ProjectStage::UserAcceptance => DeploymentReadiness::InProgress,
            ProjectStage::Intake => DeploymentReadiness::NotReady,
            default => DeploymentReadiness::NotReady,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createWorkRequest(
        string $title,
        array $row,
        WorkplanIndicator $indicator,
        ?User $admin,
        string $inventoryBlock,
    ): WorkRequest {
        $scoring = app(WorkRequestScoringService::class);
        $stage = $this->mapInventoryStatusToStage((string) ($row['inventory_status'] ?? ''));

        $request = WorkRequest::query()->create([
            'reference' => DevTrackReference::next('REQ', 'work_requests', 'reference'),
            'title' => $title,
            'requesting_unit' => (string) ($row['division'] ?? 'Africa CDC'),
            'requester_name' => $admin?->name ?? 'MIS / Software Development',
            'requester_email' => $admin?->email,
            'requester_id' => $admin?->id,
            'business_owner' => $this->cleanFocal($row['focal'] ?? null),
            'problem_statement' => 'Register and track '.$title.' in the Africa CDC systems inventory.',
            'expected_outcomes' => 'System visible in DevTrack with correct lifecycle stage and ownership.',
            'success_measures' => 'Inventory metadata recorded; stage reflects operational status.',
            'target_users' => 'Africa CDC staff and MIS',
            'urgency' => $stage === ProjectStage::Intake ? UrgencyLevel::Medium : UrgencyLevel::Low,
            'data_classification' => DataClassification::Internal,
            'existing_systems' => $title,
            'work_class' => $this->defaultWorkClass($stage),
            'status' => WorkRequestStatus::Converted,
            'workplan_indicator_id' => $indicator->id,
            'created_by' => $admin?->id,
            'acknowledged_by' => $admin?->id,
            'acknowledged_at' => now(),
            'score_strategic_alignment' => 60,
            'score_impact' => 55,
            'score_urgency_risk' => 40,
            'score_user_reach' => 50,
            'score_feasibility' => 70,
            'score_cost_effort' => 50,
            'score_security' => 45,
        ]);

        if ($this->isBlank($request->integrations_needed)) {
            $request->integrations_needed = $inventoryBlock;
        }

        $scoring->apply($request);
        $request->save();

        return $request;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mergeWorkRequest(WorkRequest $request, array $row, string $inventoryBlock): void
    {
        if ($this->isBlank($request->requesting_unit) && ! $this->isBlank($row['division'] ?? null)) {
            $request->requesting_unit = (string) $row['division'];
        }
        if ($this->isBlank($request->business_owner)) {
            $request->business_owner = $this->cleanFocal($row['focal'] ?? null);
        }
        if ($this->isBlank($request->existing_systems)) {
            $request->existing_systems = $request->title;
        }
        if ($this->isBlank($request->integrations_needed)) {
            $request->integrations_needed = $inventoryBlock;
        } elseif (! str_contains($request->integrations_needed, 'Africa CDC Systems Inventory')) {
            $request->integrations_needed = $this->appendInventoryBlock($request->integrations_needed, $inventoryBlock);
        }
        $request->save();
    }

    /**
     * @param  list<int>  $claimedProjectIds
     */
    private function findMatchingProject(string $inventoryName, array $claimedProjectIds): ?Project
    {
        $needle = $this->normalizeName($inventoryName);

        $projects = Project::query()
            ->when($claimedProjectIds !== [], fn ($q) => $q->whereNotIn('id', $claimedProjectIds))
            ->get();

        foreach ($projects as $project) {
            if ($this->normalizeName($project->name) === $needle) {
                return $project;
            }
        }

        foreach (self::NAME_ALIASES as $inventoryKey => $aliases) {
            if ($needle !== $this->normalizeName($inventoryKey)) {
                continue;
            }
            foreach ($aliases as $alias) {
                foreach ($projects as $project) {
                    if ($this->normalizeName($project->name) === $this->normalizeName($alias)) {
                        return $project;
                    }
                }
            }
        }

        foreach ($projects as $project) {
            $projectKey = $this->normalizeName($project->name);
            if ($projectKey !== '' && (str_contains($needle, $projectKey) || str_contains($projectKey, $needle))) {
                if (strlen($projectKey) >= 8 || strlen($needle) >= 8) {
                    return $project;
                }
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(trim($name))) ?? '';
    }

    private function isBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '—';
    }

    private function cleanFocal(mixed $value): ?string
    {
        if ($this->isBlank($value)) {
            return null;
        }

        return trim((string) $value);
    }

    private function appendInventoryBlock(string $existing, string $block): string
    {
        if (str_contains($existing, 'Source: Africa CDC Systems Inventory')) {
            return $existing;
        }

        return trim($existing."\n\n".$block);
    }
}
