<?php

namespace App\Services;

use App\Ai\OpenAiCompatibleClient;
use App\Models\HelpdeskAgentMonthlyReport;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AgentMonthlyReportService
{
    public function __construct(
        private readonly OpenAiCompatibleClient $ai,
        private readonly AgentCategoryRoutingService $routing,
    ) {}

    /**
     * @return list<User>
     */
    public function eligibleAgents(): array
    {
        return User::query()
            ->whereHas('helpdeskProfile', fn ($q) => $q->whereIn('role', [
                HelpdeskProfile::ROLE_AGENT,
                HelpdeskProfile::ROLE_SUPERVISOR,
                HelpdeskProfile::ROLE_ADMIN,
            ]))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'photo'])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function collectMetrics(User $agent, int $year, int $month): array
    {
        $start = now()->setDate($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $assignedBase = HelpdeskTicket::query()
            ->where('assigned_user_id', $agent->id)
            ->whereBetween('created_at', [$start, $end]);

        $workedInPeriod = HelpdeskTicket::query()
            ->where(function ($q) use ($agent, $start, $end) {
                $q->where(function ($w) use ($agent, $start, $end) {
                    $w->where('assigned_user_id', $agent->id)
                        ->whereNotNull('first_response_at')
                        ->whereBetween('first_response_at', [$start, $end]);
                })->orWhere(function ($w) use ($agent, $start, $end) {
                    $w->where('resolved_by_user_id', $agent->id)
                        ->whereBetween('resolved_at', [$start, $end]);
                });
            })
            ->distinct('id')
            ->count('id');

        $resolved = HelpdeskTicket::query()
            ->where('resolved_by_user_id', $agent->id)
            ->whereBetween('resolved_at', [$start, $end])
            ->count();

        $avgResponse = HelpdeskTicket::query()
            ->where('assigned_user_id', $agent->id)
            ->whereNotNull('first_response_at')
            ->whereBetween('first_response_at', [$start, $end])
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) AS avg_min')
            ->value('avg_min');

        $byStatus = (clone $assignedBase)
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $byPriority = (clone $assignedBase)
            ->selectRaw('priority, COUNT(*) AS c')
            ->groupBy('priority')
            ->pluck('c', 'priority')
            ->all();

        $overdueResolved = HelpdeskTicket::query()
            ->where('resolved_by_user_id', $agent->id)
            ->whereBetween('resolved_at', [$start, $end])
            ->whereNotNull('sla_resolution_due_at')
            ->whereColumn('resolved_at', '>', 'sla_resolution_due_at')
            ->count();

        return [
            'period' => ['year' => $year, 'month' => $month],
            'agent' => ['id' => $agent->id, 'name' => $agent->name, 'email' => $agent->email],
            'tickets_assigned' => (clone $assignedBase)->count(),
            'tickets_worked' => $workedInPeriod,
            'tickets_resolved' => $resolved,
            'avg_first_response_minutes' => $avgResponse !== null ? (int) round((float) $avgResponse) : null,
            'sla_resolution_breaches' => $overdueResolved,
            'by_status' => $byStatus,
            'by_priority' => $byPriority,
            'support_groups' => $this->routing->supportGroupsForUser($agent->id),
        ];
    }

    public function generateForAgent(User $agent, int $year, int $month, bool $force = false): ?HelpdeskAgentMonthlyReport
    {
        $existing = HelpdeskAgentMonthlyReport::query()
            ->where('user_id', $agent->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($existing && ! $force) {
            return $existing;
        }

        $metrics = $this->collectMetrics($agent, $year, $month);
        [$summary, $model] = $this->buildSummary($metrics, $agent, $year, $month);

        $report = $existing ?? new HelpdeskAgentMonthlyReport([
            'user_id' => $agent->id,
            'period_year' => $year,
            'period_month' => $month,
        ]);

        $report->metrics_json = $metrics;
        $report->ai_summary = $summary;
        $report->ai_model = $model;
        $report->save();

        $report->storage_path = $this->writeHtmlFile($report);
        $report->save();

        return $report->fresh(['user']);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{0: string, 1: string|null}
     */
    private function buildSummary(array $metrics, User $agent, int $year, int $month): array
    {
        $monthName = now()->setDate($year, $month, 1)->format('F Y');
        $model = null;

        if ($this->ai->isConfigured()) {
            $model = (string) (\App\Models\HelpdeskSetting::getValue(
                \App\Models\HelpdeskSetting::KEY_AI_MODEL_NAME,
                'gpt-4o-mini'
            ));

            $prompt = json_encode($metrics, JSON_PRETTY_PRINT);
            $aiText = $this->ai->chat([
                [
                    'role' => 'system',
                    'content' => 'You write concise monthly IT helpdesk agent performance reports for Africa CDC staff. '
                        .'Return JSON with keys: headline (string), summary (string, 2-4 sentences), highlights (array of 3-5 bullet strings), '
                        .'improvements (array of 1-3 constructive suggestions). Be professional and encouraging.',
                ],
                [
                    'role' => 'user',
                    'content' => "Generate the monthly agent report for {$agent->name} for {$monthName}. Metrics:\n{$prompt}",
                ],
            ], 1200, 0.4);

            if ($aiText !== null && $aiText !== '') {
                $parsed = json_decode($aiText, true);
                if (is_array($parsed)) {
                    return [$this->formatAiJsonSummary($parsed), $model];
                }

                return [$aiText, $model];
            }
        }

        return [$this->fallbackSummary($metrics, $monthName), null];
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function formatAiJsonSummary(array $parsed): string
    {
        $parts = [];
        if (! empty($parsed['headline'])) {
            $parts[] = '## '.$parsed['headline'];
        }
        if (! empty($parsed['summary'])) {
            $parts[] = (string) $parsed['summary'];
        }
        if (! empty($parsed['highlights']) && is_array($parsed['highlights'])) {
            $parts[] = "### Highlights\n".implode("\n", array_map(
                fn ($h) => '- '.(string) $h,
                $parsed['highlights']
            ));
        }
        if (! empty($parsed['improvements']) && is_array($parsed['improvements'])) {
            $parts[] = "### Opportunities\n".implode("\n", array_map(
                fn ($h) => '- '.(string) $h,
                $parsed['improvements']
            ));
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function fallbackSummary(array $metrics, string $monthName): string
    {
        $worked = (int) ($metrics['tickets_worked'] ?? 0);
        $resolved = (int) ($metrics['tickets_resolved'] ?? 0);
        $avg = $metrics['avg_first_response_minutes'] ?? null;
        $avgLabel = $avg !== null ? "{$avg} minutes" : 'n/a';

        return "## Monthly summary — {$monthName}\n\n"
            ."During {$monthName}, you worked **{$worked}** ticket(s) and resolved **{$resolved}**. "
            ."Your average first-response time was **{$avgLabel}**.";
    }

    private function writeHtmlFile(HelpdeskAgentMonthlyReport $report): string
    {
        $path = sprintf(
            'helpdesk/agent-reports/%04d/%02d/agent-%d.html',
            $report->period_year,
            $report->period_month,
            $report->user_id,
        );

        $agentName = e($report->metrics_json['agent']['name'] ?? 'Agent');
        $period = e($report->periodLabel());
        $body = nl2br(e($report->ai_summary));
        $metricsJson = e(json_encode($report->metrics_json, JSON_PRETTY_PRINT));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Agent report {$period}</title></head>
<body style="font-family:system-ui,sans-serif;max-width:720px;margin:2rem auto;color:#0f172a;">
<h1>Monthly agent report</h1>
<p><strong>Agent:</strong> {$agentName}<br><strong>Period:</strong> {$period}</p>
<div>{$body}</div>
<hr>
<h2>Metrics</h2>
<pre style="background:#f8fafc;padding:1rem;overflow:auto;font-size:12px;">{$metricsJson}</pre>
</body>
</html>
HTML;

        Storage::disk('local')->put($path, $html);

        return $path;
    }

    public function purgeExpiredReports(): int
    {
        $months = \App\Models\HelpdeskSetting::agentMonthlyReportRetentionMonths();
        $cutoff = now()->subMonths($months)->startOfMonth();

        $deleted = 0;
        $rows = HelpdeskAgentMonthlyReport::query()
            ->where(function ($q) use ($cutoff) {
                $q->where('period_year', '<', $cutoff->year)
                    ->orWhere(function ($w) use ($cutoff) {
                        $w->where('period_year', $cutoff->year)
                            ->where('period_month', '<', $cutoff->month);
                    });
            })
            ->get();

        foreach ($rows as $row) {
            if ($row->storage_path && Storage::disk('local')->exists($row->storage_path)) {
                Storage::disk('local')->delete($row->storage_path);
            }
            $row->delete();
            $deleted++;
        }

        Log::info('Purged expired agent monthly reports', ['deleted' => $deleted, 'retention_months' => $months]);

        return $deleted;
    }
}
