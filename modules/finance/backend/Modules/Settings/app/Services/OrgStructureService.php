<?php

namespace Modules\Settings\Services;

use App\Support\StaffPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrgStructureService
{
    public function tablesReady(): bool
    {
        return Schema::hasTable('org_structure_nodes')
            && Schema::hasTable('org_structure_assignments');
    }

    /**
     * @return array{tree: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function tree(): array
    {
        if (! $this->tablesReady()) {
            return [
                'tree' => [],
                'meta' => [
                    'ready' => false,
                    'message' => 'Run migrations to create org_structure tables.',
                    'totals' => ['nodes' => 0, 'approved' => 0, 'filled' => 0, 'vacant' => 0],
                ],
            ];
        }

        $nodes = DB::table('org_structure_nodes')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $assignments = DB::table('org_structure_assignments as a')
            ->leftJoin('staff as s', 's.staff_id', '=', 'a.staff_id')
            ->leftJoin('staff_contracts as sc', 'sc.staff_contract_id', '=', 'a.staff_contract_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->select([
                'a.*',
                's.title as staff_title',
                's.fname',
                's.lname',
                's.oname',
                's.photo',
                's.work_email',
                'g.grade as grade_label',
            ])
            ->get()
            ->groupBy('node_id');

        $byParent = [];
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $parent = $node['parent_id'] !== null ? (int) $node['parent_id'] : 0;
            $filledPeople = [];
            foreach ($assignments->get($id, collect()) as $a) {
                $photo = trim((string) ($a->photo ?? ''));
                $filledPeople[] = [
                    'staff_id' => (int) $a->staff_id,
                    'staff_contract_id' => $a->staff_contract_id ? (int) $a->staff_contract_id : null,
                    'name' => trim(implode(' ', array_filter([
                        (string) ($a->staff_title ?? ''),
                        (string) ($a->fname ?? ''),
                        (string) ($a->oname ?? ''),
                        (string) ($a->lname ?? ''),
                    ]))) ?: ('Staff #'.$a->staff_id),
                    'work_email' => $a->work_email,
                    'photo_url' => StaffPhoto::url($photo !== '' ? $photo : null),
                    'grade' => $a->grade_label,
                    'match_status' => $a->match_status,
                ];
            }
            $approved = max(1, (int) ($node['approved_slots'] ?? 1));
            $filled = count($filledPeople);
            $byParent[$parent][] = [
                'id' => $id,
                'parent_id' => $node['parent_id'] !== null ? (int) $node['parent_id'] : null,
                'node_type' => $node['node_type'],
                'title' => $node['title'],
                'job_id' => $node['job_id'] !== null ? (int) $node['job_id'] : null,
                'grade_id' => $node['grade_id'] !== null ? (int) $node['grade_id'] : null,
                'grade_code' => $node['grade_code'],
                'grade_band' => $node['grade_band'],
                'directorate_id' => $node['directorate_id'] !== null ? (int) $node['directorate_id'] : null,
                'division_id' => $node['division_id'] !== null ? (int) $node['division_id'] : null,
                'unit_id' => $node['unit_id'] !== null ? (int) $node['unit_id'] : null,
                'approved_slots' => $approved,
                'filled_slots' => $filled,
                'vacant_slots' => max(0, $approved - $filled),
                'sort_order' => (int) ($node['sort_order'] ?? 0),
                'source' => $node['source'],
                'tier' => $node['tier'],
                'notes' => $node['notes'],
                'filled_by' => $filledPeople,
                'children' => [],
            ];
        }

        $build = function (int $parentId) use (&$build, &$byParent): array {
            $children = $byParent[$parentId] ?? [];
            foreach ($children as &$child) {
                $child['children'] = $build((int) $child['id']);
            }

            return $children;
        };

        $tree = $build(0);
        $totals = [
            'nodes' => count($nodes),
            'approved' => array_sum(array_map(fn ($n) => (int) $n['approved_slots'], $nodes)),
            'filled' => $assignments->flatten(1)->count(),
        ];
        $totals['vacant'] = max(0, $totals['approved'] - $totals['filled']);

        return [
            'tree' => $tree,
            'meta' => [
                'ready' => true,
                'totals' => $totals,
            ],
        ];
    }

    /**
     * @return array{created_nodes: int, created_assignments: int, linked_by_supervisor: int, linked_by_role: int, message: string}
     */
    public function generateFromSystem(bool $replace = true): array
    {
        if (! $this->tablesReady()) {
            throw new \RuntimeException('Org structure tables are missing. Run migrations first.');
        }

        return DB::transaction(function () use ($replace): array {
            if ($replace) {
                DB::table('org_structure_assignments')->delete();
                DB::table('org_structure_nodes')->delete();
            } elseif (DB::table('org_structure_nodes')->exists()) {
                throw new \InvalidArgumentException('Structure already exists. Pass replace=true to regenerate.');
            }

            $now = now();
            $rootId = (int) DB::table('org_structure_nodes')->insertGetId([
                'parent_id' => null,
                'node_type' => 'organization',
                'title' => 'Africa CDC',
                'approved_slots' => 0,
                'sort_order' => 0,
                'source' => 'generated',
                'tier' => 'root',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $staffRows = $this->activeStaffRows();
            $nodeByStaff = [];
            $createdNodes = 1;
            $createdAssignments = 0;

            // 1) Create one filled position per active staff (parent = root temporarily).
            foreach ($staffRows as $idx => $row) {
                $tier = $this->detectTier($row);
                $title = (string) ($row['job_name'] ?: 'Staff');
                if ($tier === 'hod' && ! empty($row['division_name'])) {
                    $title .= ' — '.$row['division_name'];
                }

                $nodeId = $this->insertPositionNode([
                    'parent_id' => $rootId,
                    'title' => $title,
                    'job_id' => $row['job_id'],
                    'grade_id' => $row['grade_id'],
                    'grade_code' => $row['grade'],
                    'grade_band' => $this->gradeBand((string) ($row['grade'] ?? '')),
                    'directorate_id' => $row['directorate_id'],
                    'division_id' => $row['division_id'],
                    'unit_id' => $row['unit_id'],
                    'tier' => $tier,
                    'sort_order' => $this->tierSortBase($tier) + $idx,
                    'approved_slots' => 1,
                    'notes' => ! empty($row['first_supervisor'])
                        ? 'first_supervisor='.(int) $row['first_supervisor']
                        : null,
                ]);
                $createdNodes++;
                $createdAssignments += $this->assignStaff($nodeId, $row);
                $nodeByStaff[(int) $row['staff_id']] = $nodeId;
            }

            // 2) Ensure vacant executive shell posts exist when no incumbent.
            $vacantDefs = [
                'dg' => 'Director General',
                'ddg' => 'Deputy Director General',
                'cos' => 'Chief of Staff and Head of Executive Office',
                'dcos' => 'Deputy Chief of Staff',
            ];
            $tierNodeIds = [];
            foreach ($staffRows as $row) {
                $tier = $this->detectTier($row);
                if (isset($vacantDefs[$tier]) && ! isset($tierNodeIds[$tier])) {
                    $tierNodeIds[$tier] = $nodeByStaff[(int) $row['staff_id']];
                }
            }
            $order = 10;
            foreach ($vacantDefs as $tier => $title) {
                if (! isset($tierNodeIds[$tier])) {
                    $nodeId = $this->insertPositionNode([
                        'parent_id' => $rootId,
                        'title' => $title,
                        'tier' => $tier,
                        'sort_order' => $order,
                        'approved_slots' => 1,
                        'notes' => 'Vacant — created during generation',
                    ]);
                    $createdNodes++;
                    $tierNodeIds[$tier] = $nodeId;
                }
                $order += 10;
            }

            $dgNodeId = $tierNodeIds['dg'] ?? $rootId;

            // Pin DG under root; pin other executive shells under DG when not linked by supervisor.
            DB::table('org_structure_nodes')->where('id', $dgNodeId)->update(['parent_id' => $rootId]);
            foreach (['ddg', 'cos', 'dcos'] as $tier) {
                if (! isset($tierNodeIds[$tier])) {
                    continue;
                }
                $execId = (int) $tierNodeIds[$tier];
                // Only force if this node is still under root (may be overwritten by supervisor link next).
                DB::table('org_structure_nodes')
                    ->where('id', $execId)
                    ->where('parent_id', $rootId)
                    ->update(['parent_id' => $dgNodeId]);
            }

            // 3) Primary linking: first_supervisor → supervisor's position node.
            $linkedBySupervisor = 0;
            $parentByNode = DB::table('org_structure_nodes')->pluck('parent_id', 'id')->map(
                fn ($p) => $p !== null ? (int) $p : null
            )->all();

            foreach ($staffRows as $row) {
                $staffId = (int) $row['staff_id'];
                $nodeId = $nodeByStaff[$staffId] ?? null;
                if (! $nodeId) {
                    continue;
                }
                if ($this->detectTier($row) === 'dg') {
                    continue;
                }

                $supervisorId = (int) ($row['first_supervisor'] ?? 0);
                if ($supervisorId < 1 || $supervisorId === $staffId) {
                    continue;
                }
                $supervisorNodeId = $nodeByStaff[$supervisorId] ?? null;
                if (! $supervisorNodeId || $supervisorNodeId === $nodeId) {
                    continue;
                }
                if ($this->wouldCreateCycle($parentByNode, $nodeId, $supervisorNodeId)) {
                    continue;
                }

                DB::table('org_structure_nodes')->where('id', $nodeId)->update([
                    'parent_id' => $supervisorNodeId,
                    'notes' => 'Linked via first_supervisor #'.$supervisorId,
                    'updated_at' => now(),
                ]);
                $parentByNode[$nodeId] = $supervisorNodeId;
                $linkedBySupervisor++;
            }

            // 4) Role / division fallback for anyone still hanging off the root (except DG + vacant shells).
            $linkedByRole = 0;
            $hodByDivision = [];
            $directorByDirectorate = [];
            $directorByDivision = [];
            foreach ($staffRows as $row) {
                $sid = (int) $row['staff_id'];
                $nid = $nodeByStaff[$sid] ?? null;
                if (! $nid) {
                    continue;
                }
                $tier = $this->detectTier($row);
                $divId = (int) ($row['division_id'] ?? 0);
                $dirId = (int) ($row['directorate_id'] ?? 0);
                if ($tier === 'hod' && $divId > 0) {
                    $hodByDivision[$divId] = $nid;
                }
                if ($tier === 'director') {
                    if ($dirId > 0) {
                        $directorByDirectorate[$dirId] = $nid;
                    }
                    if ($divId > 0) {
                        $directorByDivision[$divId] = $nid;
                    }
                }
            }

            foreach ($staffRows as $idx => $row) {
                $staffId = (int) $row['staff_id'];
                $nodeId = $nodeByStaff[$staffId] ?? null;
                if (! $nodeId) {
                    continue;
                }
                $currentParent = $parentByNode[$nodeId] ?? null;
                if ($currentParent !== $rootId) {
                    continue;
                }
                $tier = $this->detectTier($row);
                if (in_array($tier, ['dg', 'ddg', 'cos', 'dcos'], true)) {
                    continue;
                }

                $divId = (int) ($row['division_id'] ?? 0);
                $dirId = (int) ($row['directorate_id'] ?? 0);
                $fallback = null;
                if ($tier === 'director') {
                    $fallback = $dgNodeId;
                } elseif ($tier === 'hod') {
                    $fallback = $directorByDirectorate[$dirId]
                        ?? $directorByDivision[$divId]
                        ?? $dgNodeId;
                } else {
                    $fallback = $hodByDivision[$divId]
                        ?? $directorByDirectorate[$dirId]
                        ?? $directorByDivision[$divId]
                        ?? $dgNodeId;
                }

                if (! $fallback || $fallback === $nodeId || $this->wouldCreateCycle($parentByNode, $nodeId, $fallback)) {
                    $fallback = $dgNodeId;
                }
                if ($fallback === $nodeId || $this->wouldCreateCycle($parentByNode, $nodeId, $fallback)) {
                    continue;
                }

                DB::table('org_structure_nodes')->where('id', $nodeId)->update([
                    'parent_id' => $fallback,
                    'notes' => 'Fallback link (no usable first_supervisor)',
                    'sort_order' => $this->tierSortBase($tier) + $idx,
                    'updated_at' => now(),
                ]);
                $parentByNode[$nodeId] = $fallback;
                $linkedByRole++;
            }

            // 5) Sort siblings by grade (highest first; GAS/GSA lowest).
            $this->resortChildrenByGrade($rootId);

            return [
                'created_nodes' => $createdNodes,
                'created_assignments' => $createdAssignments,
                'linked_by_supervisor' => $linkedBySupervisor,
                'linked_by_role' => $linkedByRole,
                'message' => "Generated {$createdNodes} nodes ({$createdAssignments} filled). "
                    ."Linked {$linkedBySupervisor} via first supervisor, {$linkedByRole} via role/division fallback.",
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateNode(int $nodeId, array $payload): array
    {
        $node = DB::table('org_structure_nodes')->where('id', $nodeId)->first();
        if (! $node) {
            throw new \InvalidArgumentException('Node not found.');
        }

        $data = [];
        foreach (['title', 'notes', 'tier', 'source'] as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key];
            }
        }
        foreach (['parent_id', 'job_id', 'grade_id', 'directorate_id', 'division_id', 'unit_id', 'approved_slots', 'sort_order', 'is_active'] as $key) {
            if (array_key_exists($key, $payload)) {
                $data[$key] = $payload[$key] === '' || $payload[$key] === null
                    ? null
                    : (int) $payload[$key];
            }
        }
        if (isset($data['approved_slots']) && $data['approved_slots'] !== null) {
            $data['approved_slots'] = max(0, (int) $data['approved_slots']);
        }
        if (isset($data['parent_id']) && (int) $data['parent_id'] === $nodeId) {
            throw new \InvalidArgumentException('A node cannot be its own parent.');
        }
        $data['updated_at'] = now();
        if ($data !== []) {
            $data['source'] = $data['source'] ?? 'manual';
            DB::table('org_structure_nodes')->where('id', $nodeId)->update($data);
        }

        return (array) DB::table('org_structure_nodes')->where('id', $nodeId)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function activeStaffRows(): array
    {
        $latest = DB::table('staff_contracts')
            ->select('staff_id', DB::raw('MAX(staff_contract_id) as staff_contract_id'))
            ->whereIn('status_id', [1, 2, 7])
            ->groupBy('staff_id');

        return DB::table('staff_contracts as sc')
            ->joinSub($latest, 'latest', function ($join): void {
                $join->on('latest.staff_contract_id', '=', 'sc.staff_contract_id');
            })
            ->join('staff as s', 's.staff_id', '=', 'sc.staff_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('directorates as dir', 'dir.id', '=', 'd.directorate_id')
            ->select([
                's.staff_id',
                's.title',
                's.fname',
                's.lname',
                's.oname',
                'sc.staff_contract_id',
                'sc.job_id',
                'sc.grade_id',
                'sc.division_id',
                'sc.unit_id',
                'sc.status_id',
                'sc.first_supervisor',
                'j.job_name',
                'g.grade',
                'd.division_name',
                'd.division_head',
                'd.director_id as division_director_id',
                'd.directorate_id',
                'dir.name as directorate_name',
                'dir.director_id as directorate_director_id',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** @param  array<string, mixed>  $row */
    private function detectTier(array $row): string
    {
        if ($this->isDirectorGeneral($row)) {
            return 'dg';
        }
        if ($this->isDeputyDirectorGeneral($row)) {
            return 'ddg';
        }
        if ($this->isChiefOfStaff($row)) {
            return 'cos';
        }
        if ($this->isDeputyChiefOfStaff($row)) {
            return 'dcos';
        }
        if ($this->isDirector($row)) {
            return 'director';
        }
        if ($this->isHod($row)) {
            return 'hod';
        }

        return 'staff';
    }

    private function tierSortBase(string $tier): int
    {
        return match ($tier) {
            'dg' => 10,
            'ddg' => 20,
            'cos' => 30,
            'dcos' => 40,
            'director' => 100,
            'hod' => 200,
            default => 300,
        };
    }

    /**
     * @param  array<int, int|null>  $parentByNode
     */
    private function wouldCreateCycle(array $parentByNode, int $childId, int $proposedParentId): bool
    {
        if ($childId === $proposedParentId) {
            return true;
        }
        $seen = [];
        $cursor = $proposedParentId;
        while ($cursor !== null) {
            if ($cursor === $childId) {
                return true;
            }
            if (isset($seen[$cursor])) {
                return true;
            }
            $seen[$cursor] = true;
            $cursor = $parentByNode[$cursor] ?? null;
        }

        return false;
    }

    private function resortChildrenByGrade(int $rootId): void
    {
        $nodes = DB::table('org_structure_nodes')
            ->where('is_active', 1)
            ->get(['id', 'parent_id', 'grade_code', 'tier', 'title']);

        $byParent = [];
        foreach ($nodes as $node) {
            $pid = $node->parent_id !== null ? (int) $node->parent_id : 0;
            $byParent[$pid][] = $node;
        }

        foreach ($byParent as $siblings) {
            usort($siblings, function ($a, $b): int {
                $tierDiff = $this->tierSortBase((string) ($a->tier ?? 'staff'))
                    <=> $this->tierSortBase((string) ($b->tier ?? 'staff'));
                if ($tierDiff !== 0) {
                    return $tierDiff;
                }
                $gradeDiff = $this->gradeRank((string) ($b->grade_code ?? ''))
                    <=> $this->gradeRank((string) ($a->grade_code ?? ''));
                if ($gradeDiff !== 0) {
                    return $gradeDiff;
                }

                return strcmp((string) $a->title, (string) $b->title);
            });

            foreach (array_values($siblings) as $i => $sibling) {
                DB::table('org_structure_nodes')->where('id', $sibling->id)->update([
                    'sort_order' => ($i + 1) * 10,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertPositionNode(array $data): int
    {
        $now = now();

        return (int) DB::table('org_structure_nodes')->insertGetId([
            'parent_id' => $data['parent_id'] ?? null,
            'node_type' => $data['node_type'] ?? 'position',
            'title' => $data['title'],
            'job_id' => $data['job_id'] ?? null,
            'grade_id' => $data['grade_id'] ?? null,
            'grade_code' => $data['grade_code'] ?? null,
            'grade_band' => $data['grade_band'] ?? null,
            'directorate_id' => $data['directorate_id'] ?? null,
            'division_id' => $data['division_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'approved_slots' => $data['approved_slots'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => 1,
            'source' => 'generated',
            'tier' => $data['tier'] ?? 'staff',
            'notes' => $data['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function assignStaff(int $nodeId, array $row): int
    {
        DB::table('org_structure_assignments')->insert([
            'node_id' => $nodeId,
            'staff_id' => (int) $row['staff_id'],
            'staff_contract_id' => (int) ($row['staff_contract_id'] ?? 0) ?: null,
            'is_primary' => 1,
            'match_status' => 'auto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 1;
    }

    /** @param  array<string, mixed>  $row */
    private function isDirectorGeneral(array $row): bool
    {
        $job = strtolower(trim((string) ($row['job_name'] ?? '')));

        return $job === 'director general' || preg_match('/^director general\b/', $job) === 1;
    }

    /** @param  array<string, mixed>  $row */
    private function isDeputyDirectorGeneral(array $row): bool
    {
        $job = strtolower(trim((string) ($row['job_name'] ?? '')));

        return str_contains($job, 'deputy director general');
    }

    /** @param  array<string, mixed>  $row */
    private function isChiefOfStaff(array $row): bool
    {
        $job = strtolower(trim((string) ($row['job_name'] ?? '')));

        return str_contains($job, 'chief of staff') && ! str_contains($job, 'deputy');
    }

    /** @param  array<string, mixed>  $row */
    private function isDeputyChiefOfStaff(array $row): bool
    {
        $job = strtolower(trim((string) ($row['job_name'] ?? '')));

        return str_contains($job, 'deputy chief of staff');
    }

    /** @param  array<string, mixed>  $row */
    private function isDirector(array $row): bool
    {
        if ($this->isDirectorGeneral($row) || $this->isDeputyDirectorGeneral($row)) {
            return false;
        }
        $job = strtolower(trim((string) ($row['job_name'] ?? '')));
        $grade = strtoupper(trim((string) ($row['grade'] ?? '')));
        if (str_starts_with($grade, 'D')) {
            return true;
        }
        if (preg_match('/^director\b/', $job) === 1) {
            return true;
        }
        $staffId = (int) ($row['staff_id'] ?? 0);

        return $staffId > 0 && (
            (int) ($row['directorate_director_id'] ?? 0) === $staffId
            || (int) ($row['division_director_id'] ?? 0) === $staffId
        );
    }

    /** @param  array<string, mixed>  $row */
    private function isHod(array $row): bool
    {
        $job = strtolower(trim((string) ($row['job_name'] ?? '')));
        if (str_starts_with($job, 'hod') || str_contains($job, 'head of division') || str_contains($job, 'head of unit')) {
            return true;
        }
        $staffId = (int) ($row['staff_id'] ?? 0);

        return $staffId > 0 && (int) ($row['division_head'] ?? 0) === $staffId;
    }

    private function gradeBand(string $grade): string
    {
        $g = strtoupper(trim($grade));
        if ($g === '') {
            return 'Unspecified';
        }
        if (str_starts_with($g, 'D')) {
            return 'Director';
        }
        if (str_starts_with($g, 'P')) {
            return 'Professional';
        }
        if (str_starts_with($g, 'NO')) {
            return 'National Officer';
        }
        if (str_starts_with($g, 'FS')) {
            return 'Field Service';
        }
        if (preg_match('/^(GSA|GSB|GAS|GS)/', $g) === 1) {
            return 'General Service';
        }

        return 'Other';
    }

    /**
     * Higher rank sorts above lower. GAS2 / GSA2 are near the bottom.
     */
    private function gradeRank(string $grade): int
    {
        $g = strtoupper(trim($grade));
        if ($g === '') {
            return 0;
        }
        if (preg_match('/^D(\d+)/', $g, $m)) {
            return 900 + (int) $m[1];
        }
        if (preg_match('/^P(\d+)/', $g, $m)) {
            return 700 + (int) $m[1];
        }
        if (preg_match('/^NO(\d+)/', $g, $m)) {
            return 500 + (int) $m[1];
        }
        if (preg_match('/^(?:GSA|GSB|GAS|GS)[A-Z]?(\d+)/', $g, $m)) {
            return 100 + (int) $m[1];
        }

        return 50;
    }
}
