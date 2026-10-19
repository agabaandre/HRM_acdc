<?php

namespace App\Services;

use App\Enums\PriorityCategory;
use App\Models\WorkRequest;

class WorkRequestScoringService
{
    public const WEIGHTS = [
        'score_strategic_alignment' => 25,
        'score_impact' => 25,
        'score_urgency_risk' => 15,
        'score_user_reach' => 10,
        'score_feasibility' => 10,
        'score_cost_effort' => 10,
        'score_security' => 5,
    ];

    /**
     * @param  array<string, mixed>  $scores
     */
    public function weightedScore(array $scores): int
    {
        $total = 0;

        foreach (self::WEIGHTS as $field => $weight) {
            $value = max(0, min(100, (int) ($scores[$field] ?? 0)));
            $total += $value * $weight;
        }

        return (int) round($total / 100);
    }

    public function suggestCategory(int $weightedScore, ?string $urgency = null): PriorityCategory
    {
        if ($urgency === 'critical' || $weightedScore >= 80) {
            return PriorityCategory::MustDo;
        }

        if ($weightedScore >= 65) {
            return PriorityCategory::HighPriority;
        }

        if ($weightedScore >= 50) {
            return PriorityCategory::Planned;
        }

        return PriorityCategory::Parked;
    }

    public function apply(WorkRequest $request): WorkRequest
    {
        $request->weighted_score = $this->weightedScore($request->only(array_keys(self::WEIGHTS)));

        if ($request->priority_category === null) {
            $request->priority_category = $this->suggestCategory(
                $request->weighted_score,
                $request->urgency?->value ?? (string) $request->urgency,
            );
        }

        return $request;
    }
}
