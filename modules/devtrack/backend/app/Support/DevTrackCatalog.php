<?php

namespace App\Support;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\EnhancementStatus;
use App\Enums\EnhancementWorkType;
use App\Enums\ModuleStatus;
use App\Enums\NamedProjectRole;
use App\Enums\OperationalStatus;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\RagStatus;
use App\Enums\RaidStatus;
use App\Enums\RaidType;
use App\Enums\ReleaseStatus;
use App\Enums\ReportPeriod;
use App\Enums\SprintStatus;
use App\Enums\UrgencyLevel;
use App\Enums\WorkClass;
use App\Enums\WorkItemPriority;
use App\Enums\WorkItemStatus;
use App\Enums\WorkItemType;
use App\Enums\WorkRequestStatus;
use App\Services\WorkRequestScoringService;
use BackedEnum;

class DevTrackCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        return [
            'work_classes' => self::map(WorkClass::cases(), fn (WorkClass $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'code' => $c->code(),
            ]),
            'priority_categories' => self::enumOptions(PriorityCategory::cases()),
            'project_stages' => self::map(ProjectStage::cases(), fn (ProjectStage $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'short_label' => $c->shortLabel(),
                'approver' => $c->approver(),
            ]),
            'rag_statuses' => self::enumOptions(RagStatus::cases()),
            'project_statuses' => self::map(ProjectStatus::cases(), fn (ProjectStatus $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'short_label' => $c->shortLabel(),
            ]),
            'operational_statuses' => self::enumOptions(OperationalStatus::cases()),
            'module_statuses' => self::enumOptions(ModuleStatus::cases()),
            'enhancement_statuses' => self::enumOptions(EnhancementStatus::cases()),
            'enhancement_work_types' => self::enumOptions(EnhancementWorkType::cases()),
            'release_statuses' => self::enumOptions(ReleaseStatus::cases()),
            'work_request_statuses' => self::enumOptions(WorkRequestStatus::cases()),
            'data_classifications' => self::enumOptions(DataClassification::cases()),
            'named_roles' => self::enumOptions(NamedProjectRole::cases()),
            'work_item_types' => self::enumOptions(WorkItemType::cases()),
            'work_item_statuses' => self::enumOptions(WorkItemStatus::cases()),
            'work_item_priorities' => self::enumOptions(WorkItemPriority::cases()),
            'raid_types' => self::enumOptions(RaidType::cases()),
            'raid_statuses' => self::enumOptions(RaidStatus::cases()),
            'report_periods' => self::enumOptions(ReportPeriod::cases()),
            'sprint_statuses' => self::enumOptions(SprintStatus::cases()),
            'urgency_levels' => self::enumOptions(UrgencyLevel::cases()),
            'deployment_readiness' => self::enumOptions(DeploymentReadiness::cases()),
            'scoring_weights' => WorkRequestScoringService::WEIGHTS,
        ];
    }

    /**
     * @param  list<BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private static function enumOptions(array $cases): array
    {
        return self::map($cases, function (BackedEnum $case): array {
            $label = method_exists($case, 'label') ? $case->label() : $case->value;

            return ['value' => (string) $case->value, 'label' => $label];
        });
    }

    /**
     * @template T
     * @param  list<T>  $items
     * @param  callable(T): array<string, mixed>  $callback
     * @return list<array<string, mixed>>
     */
    private static function map(array $items, callable $callback): array
    {
        return array_values(array_map($callback, $items));
    }
}
