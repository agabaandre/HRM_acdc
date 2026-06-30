<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateMonthlyAgentReportsJob;
use App\Models\HelpdeskAgentMonthlyReport;
use App\Models\HelpdeskProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentMonthlyReportController extends Controller
{
    private function isStaff(HelpdeskProfile $p): bool
    {
        return in_array($p->role, [
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $this->isStaff($p), 403);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', 0);

        $query = HelpdeskAgentMonthlyReport::query()
            ->with('user:id,name,email')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month');

        if ($year > 0) {
            $query->where('period_year', $year);
        }
        if ($month >= 1 && $month <= 12) {
            $query->where('period_month', $month);
        }

        if ($p->isHelpdeskAdmin() && $request->filled('user_id')) {
            $query->where('user_id', (int) $request->query('user_id'));
        } elseif (! $p->isHelpdeskAdmin()) {
            $query->where('user_id', $user->id);
        }

        $reports = $query->limit(24)->get();

        return response()->json([
            'data' => $reports->map(fn (HelpdeskAgentMonthlyReport $r) => $this->serializeList($r)),
        ]);
    }

    public function show(Request $request, HelpdeskAgentMonthlyReport $report): JsonResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $this->isStaff($p), 403);
        abort_unless($p->isHelpdeskAdmin() || $report->user_id === $user->id, 403);

        $report->load('user:id,name,email');

        return response()->json(['data' => $this->serializeDetail($report)]);
    }

    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $this->isStaff($p), 403);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'force' => ['nullable', 'boolean'],
        ]);

        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $force = (bool) ($validated['force'] ?? false);

        if (! $p->isHelpdeskAdmin()) {
            $report = app(\App\Services\AgentMonthlyReportService::class)
                ->generateForAgent($user, $year, $month, $force);

            return response()->json(['data' => $this->serializeDetail($report)]);
        }

        if (! empty($validated['user_id'])) {
            $target = User::query()->findOrFail((int) $validated['user_id']);
            $report = app(\App\Services\AgentMonthlyReportService::class)
                ->generateForAgent($target, $year, $month, $force);

            return response()->json(['data' => $this->serializeDetail($report)]);
        }

        GenerateMonthlyAgentReportsJob::dispatch($year, $month, $force);

        return response()->json([
            'data' => ['queued' => true, 'year' => $year, 'month' => $month],
            'message' => 'Monthly agent report generation queued for all agents.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeList(HelpdeskAgentMonthlyReport $report): array
    {
        return [
            'id' => $report->id,
            'user_id' => $report->user_id,
            'user_name' => $report->user?->name,
            'period_year' => $report->period_year,
            'period_month' => $report->period_month,
            'period_label' => $report->periodLabel(),
            'tickets_worked' => $report->metrics_json['tickets_worked'] ?? null,
            'tickets_resolved' => $report->metrics_json['tickets_resolved'] ?? null,
            'avg_first_response_minutes' => $report->metrics_json['avg_first_response_minutes'] ?? null,
            'emailed_at' => $report->emailed_at?->toIso8601String(),
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(HelpdeskAgentMonthlyReport $report): array
    {
        return [
            ...$this->serializeList($report),
            'ai_summary' => $report->ai_summary,
            'ai_model' => $report->ai_model,
            'metrics' => $report->metrics_json,
            'has_file' => $report->storage_path !== null,
        ];
    }
}
