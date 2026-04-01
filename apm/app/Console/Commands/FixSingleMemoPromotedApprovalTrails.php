<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\ApprovalTrail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSingleMemoPromotedApprovalTrails extends Command
{
    protected $signature = 'apm:fix-single-memo-promoted-approval-trails
                            {--activity-id= : Limit to one activity ID}
                            {--dry-run : Show changes without saving}';

    protected $description = 'Fix approval_trails for converted single memos: map passed→approved, convert_to_single_memo→returned, restore timestamps from activity_approval_trails, ensure returned sorts as latest';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $singleId = $this->option('activity-id');

        $query = Activity::query()->where('is_single_memo', true)->orderBy('id');
        if ($singleId !== null && $singleId !== '') {
            $query->where('id', (int) $singleId);
        }

        $activities = $query->get();
        if ($activities->isEmpty()) {
            $this->warn('No matching single-memo activities found.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $skipped = 0;
        $warnings = 0;

        foreach ($activities as $activity) {
            $result = $this->fixActivity($activity, $dryRun);
            if ($result === 'fixed') {
                $fixed++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $warnings++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Done. Fixed: %d, skipped (nothing to do): %d, warnings: %d%s',
            $fixed,
            $skipped,
            $warnings,
            $dryRun ? ' (dry run — no DB writes)' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * @return 'fixed'|'skipped'|'warn'
     */
    private function fixActivity(Activity $activity, bool $dryRun): string
    {
        $activityTrails = ActivityApprovalTrail::query()
            ->where('activity_id', $activity->id)
            ->orderBy('id')
            ->get();

        if ($activityTrails->isEmpty()) {
            $this->line("Activity {$activity->id}: no activity_approval_trails — skipped.");

            return 'skipped';
        }

        $promoted = ApprovalTrail::query()
            ->where('model_id', $activity->id)
            ->where('model_type', Activity::class)
            ->orderBy('id')
            ->get();

        if ($promoted->isEmpty()) {
            $this->warn("Activity {$activity->id}: no approval_trails — skipped.");

            return 'warn';
        }

        $pairCount = min($activityTrails->count(), $promoted->count());
        if ($pairCount < $activityTrails->count()) {
            $this->warn("Activity {$activity->id}: fewer approval_trails ({$promoted->count()}) than activity_approval_trails ({$activityTrails->count()}); pairing first {$pairCount} rows by id order.");
        }

        /** @var array<int, Carbon|null> $effectiveCreated */
        $effectiveCreated = [];
        /** @var array<int, Carbon|null> $effectiveUpdated */
        $effectiveUpdated = [];
        $changed = false;

        for ($i = 0; $i < $pairCount; $i++) {
            $at = $activityTrails[$i];
            $pt = $promoted[$i];

            $mapped = ActivityApprovalTrail::mapActionForPromotionToApprovalTrail($at->action);
            $srcCreated = $at->created_at ? Carbon::parse($at->created_at) : null;
            $srcUpdated = $at->updated_at ? Carbon::parse($at->updated_at) : $srcCreated;

            $effectiveCreated[$i] = $srcCreated ?? ($pt->created_at ? Carbon::parse($pt->created_at) : null);
            $effectiveUpdated[$i] = $srcUpdated ?? ($pt->updated_at ? Carbon::parse($pt->updated_at) : $effectiveCreated[$i]);

            $needsAction = (string) $pt->action !== (string) $mapped;
            $ec = $effectiveCreated[$i];
            $needsTime = $ec && (
                ! $pt->created_at
                || $pt->created_at->ne($ec)
                || ($effectiveUpdated[$i] && $pt->updated_at && $pt->updated_at->ne($effectiveUpdated[$i]))
            );

            if ($needsAction || $needsTime) {
                $changed = true;
                $this->line(sprintf(
                    '  Activity %d: approval_trail %d (was "%s") ← activity_trail %d (source "%s") → "%s", sync timestamps',
                    $activity->id,
                    $pt->id,
                    $pt->action,
                    $at->id,
                    $at->action,
                    $mapped
                ));
            }

            if (! $dryRun && ($needsAction || $needsTime)) {
                $pt->action = $mapped;
                if ($ec) {
                    $pt->created_at = $ec;
                }
                if ($effectiveUpdated[$i]) {
                    $pt->updated_at = $effectiveUpdated[$i];
                }
                $pt->save(['timestamps' => false]);
            }
        }

        $returnedIndex = null;
        for ($i = 0; $i < $pairCount; $i++) {
            if ($this->activityTrailWasConvertToSingleMemo($activityTrails[$i]->action)) {
                $returnedIndex = $i;
            }
        }

        if ($returnedIndex !== null && isset($effectiveCreated[$returnedIndex])) {
            $othersMax = null;
            for ($i = 0; $i < $pairCount; $i++) {
                if ($i === $returnedIndex) {
                    continue;
                }
                $c = $effectiveCreated[$i] ?? null;
                if ($c && ($othersMax === null || $c->gt($othersMax))) {
                    $othersMax = $c->copy();
                }
            }

            $retTs = $effectiveCreated[$returnedIndex];
            if ($othersMax !== null && $retTs !== null && ! $retTs->gt($othersMax)) {
                $bumped = $othersMax->copy()->addSecond();
                $effectiveCreated[$returnedIndex] = $bumped;
                $effectiveUpdated[$returnedIndex] = $bumped;
                $changed = true;
                $returnedPt = $promoted[$returnedIndex];
                $this->line(sprintf(
                    '  Activity %d: bump returned approval_trail %d created_at/updated_at to %s (newest in promoted batch)',
                    $activity->id,
                    $returnedPt->id,
                    $bumped->toDateTimeString()
                ));
                if (! $dryRun) {
                    DB::table('approval_trails')->where('id', $returnedPt->id)->update([
                        'created_at' => $bumped,
                        'updated_at' => $bumped,
                    ]);
                }
            }
        }

        if (! $changed) {
            $this->line("Activity {$activity->id}: already aligned — skipped.");

            return 'skipped';
        }

        return 'fixed';
    }

    private function activityTrailWasConvertToSingleMemo(?string $action): bool
    {
        $n = strtolower((string) $action);

        return in_array($n, ['convert_to_single_memo', 'converted_to_single_memo'], true);
    }
}
