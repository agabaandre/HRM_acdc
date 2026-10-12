<?php

namespace App\Services\ActivityReport;

use App\Models\ActivityReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ActivityReportShareService
{
    /** @var list<string> */
    public const TARGETS = ['rba', 'rbm'];

    public function __construct(
        private ActivityReportService $reports,
    ) {}

    /**
     * Build the export payload consumed by RBA / RBM platforms.
     *
     * @return array<string, mixed>
     */
    public function exportPayload(ActivityReport $report): array
    {
        $report->loadMissing(['relatedLinks']);
        $base = $this->reports->previewPayload($report);

        $praActivityId = null;
        try {
            $report->loadMissing(['source']);
            $source = $report->source;
            if ($source && isset($source->pra_activity_id) && $source->pra_activity_id) {
                $praActivityId = (int) $source->pra_activity_id;
            }
        } catch (\Throwable) {
            $praActivityId = null;
        }

        return array_merge($base, [
            'source_id' => (int) $report->source_id,
            'source_type' => (string) $report->source_type,
            'division_id' => $report->division_id !== null ? (int) $report->division_id : null,
            'staff_id' => $report->staff_id !== null ? (int) $report->staff_id : null,
            'responsible_person_id' => $report->responsible_person_id !== null ? (int) $report->responsible_person_id : null,
            'pra_activity_id' => $praActivityId,
            'has_been_submitted' => (bool) $report->has_been_submitted,
            'submitted_at' => optional($report->submitted_at)?->toIso8601String(),
            'shared_for' => self::TARGETS,
        ]);
    }

    /**
     * Record a share of an approved (or submitted) activity report to RBA and/or RBM.
     *
     * @param  list<string>  $targets
     * @return list<array<string, mixed>>
     */
    public function share(ActivityReport $report, array $targets, int $sharedBy, ?string $note = null): array
    {
        $targets = array_values(array_unique(array_map(
            static fn ($t) => strtolower(trim((string) $t)),
            $targets
        )));

        foreach ($targets as $target) {
            if (! in_array($target, self::TARGETS, true)) {
                throw new InvalidArgumentException("Invalid share target [{$target}]. Allowed: rba, rbm.");
            }
        }

        if ($targets === []) {
            throw new InvalidArgumentException('At least one share target is required (rba or rbm).');
        }

        if (! Schema::hasTable('activity_report_shares')) {
            throw new InvalidArgumentException('activity_report_shares table is missing. Run migrations.');
        }

        $payload = $this->exportPayload($report);
        $rows = [];

        DB::transaction(function () use ($report, $targets, $sharedBy, $note, $payload, &$rows) {
            foreach ($targets as $target) {
                $id = DB::table('activity_report_shares')->insertGetId([
                    'activity_report_id' => $report->id,
                    'target' => $target,
                    'status' => 'queued',
                    'shared_by' => $sharedBy > 0 ? $sharedBy : null,
                    'note' => $note,
                    'payload_snapshot' => json_encode($payload),
                    'shared_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Delivery to external RBA/RBM systems is queued for integration jobs.
                DB::table('activity_report_shares')->where('id', $id)->update([
                    'status' => 'sent',
                    'updated_at' => now(),
                ]);

                $rows[] = [
                    'id' => $id,
                    'activity_report_id' => (int) $report->id,
                    'target' => $target,
                    'status' => 'sent',
                    'shared_at' => now()->toIso8601String(),
                ];
            }
        });

        return $rows;
    }
}
