<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RatingBandResolver;
use App\Services\RiskSettingsService;
use App\Support\RiskPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskSettingsController extends Controller
{
    public function __construct(
        private readonly RiskSettingsService $settings,
        private readonly RatingBandResolver $bands,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $working = $this->bands->workingBands();
        $usage = [];
        foreach ($working as $band) {
            $usage[$band['band_key']] = $this->bands->bandIsInUse($band['band_key'], $band['rating']);
        }

        return response()->json([
            'data' => $this->settings->all() + [
                'active_rating_key_version' => (string) $this->bands->activeVersion(),
            ],
            'rating_bands' => $working,
            'rating_band_usage' => $usage,
            'rating_key_versions' => DB::table('rr_rating_key_versions')
                ->orderByDesc('version')
                ->get(['id', 'version', 'label', 'created_at']),
            'lookups' => [
                'rr_likelihoods' => DB::table('rr_likelihoods')->orderBy('sort_order')->get(),
                'rr_impacts' => DB::table('rr_impacts')->orderBy('sort_order')->get(),
                'rr_risk_types' => DB::table('rr_risk_types')->orderBy('sort_order')->get(),
                'rr_enterprise_themes' => DB::table('rr_enterprise_themes')->orderBy('sort_order')->get(),
                'rr_statuses' => DB::table('rr_statuses')->orderBy('sort_order')->get(),
                'rr_mitigation_effectiveness' => DB::table('rr_mitigation_effectiveness')->orderBy('sort_order')->get(),
            ],
            'can_manage' => RiskPermissions::canManage($request->attributes->get('risk_permissions', [])),
            'can_delete_lookups' => RiskPermissions::canDeleteLookups($request->attributes->get('risk_permissions', [])),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->assertManage($request);
        $data = $request->validate([
            'import_enabled' => 'sometimes|boolean',
            'active_rating_key_version' => 'sometimes|integer|min:1',
        ]);
        if (array_key_exists('import_enabled', $data)) {
            $this->settings->setImportEnabled((bool) $data['import_enabled']);
        }
        if (array_key_exists('active_rating_key_version', $data)) {
            try {
                $this->bands->activateRatingVersion((int) $data['active_rating_key_version']);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json([
            'data' => $this->settings->all() + [
                'active_rating_key_version' => (string) $this->bands->activeVersion(),
            ],
            'rating_bands' => $this->bands->workingBands(),
        ]);
    }

    public function updateLookup(Request $request, string $table): JsonResponse
    {
        $this->assertManage($request);

        $allowed = [
            'rr_likelihoods' => ['label', 'score', 'sort_order'],
            'rr_impacts' => ['label', 'score', 'sort_order'],
            'rr_risk_types' => ['name', 'sort_order'],
            'rr_enterprise_themes' => ['code', 'name', 'sort_order'],
            'rr_statuses' => ['name', 'sort_order'],
            'rr_mitigation_effectiveness' => ['name', 'likelihood_reduction', 'is_assessed', 'sort_order'],
            'rr_rating_bands' => ['rating', 'band_key', 'min_score', 'max_score', 'fill_color', 'text_color', 'sort_order'],
        ];
        if (! isset($allowed[$table])) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }

        $data = $request->validate([
            'id' => 'nullable|integer',
            'fields' => 'required|array',
        ]);

        $fields = array_intersect_key($data['fields'], array_flip($allowed[$table]));
        if ($fields === []) {
            return response()->json(['message' => 'No valid fields.'], 422);
        }

        if ($table === 'rr_rating_bands') {
            $fields = $this->normalizeBandFields($fields);
        }

        if (! empty($data['id'])) {
            DB::table($table)->where('id', (int) $data['id'])->update($fields + ['updated_at' => now()]);
            $id = (int) $data['id'];
        } else {
            $id = (int) DB::table($table)->insertGetId($fields + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->bands->forgetLookupsCache();

        return response()->json(['data' => DB::table($table)->where('id', $id)->first()]);
    }

    public function deleteLookup(Request $request, string $table, int $id): JsonResponse
    {
        $this->assertManage($request);
        $this->assertDeleteLookups($request);
        $allowed = [
            'rr_likelihoods', 'rr_impacts', 'rr_risk_types', 'rr_enterprise_themes',
            'rr_statuses', 'rr_mitigation_effectiveness', 'rr_rating_bands',
        ];
        if (! in_array($table, $allowed, true)) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }

        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $block = $this->lookupDeleteBlockReason($table, $row);
        if ($block !== null) {
            return response()->json(['message' => $block], 422);
        }

        DB::table($table)->where('id', $id)->delete();
        $this->bands->forgetLookupsCache();

        return response()->json(['ok' => true]);
    }

    /**
     * Activate an existing published version as the default for new risk profiling.
     */
    public function activateRatingVersion(Request $request): JsonResponse
    {
        $this->assertManage($request);
        $data = $request->validate([
            'version' => 'required|integer|min:1',
        ]);
        try {
            $this->bands->activateRatingVersion((int) $data['version']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'active_rating_key_version' => (string) $this->bands->activeVersion(),
                'bands' => $this->bands->workingBands(),
            ],
        ]);
    }

    /**
     * Replace working bands and publish a new immutable rating-key version.
     */
    public function publishRatingBands(Request $request): JsonResponse
    {
        $this->assertManage($request);
        $data = $request->validate([
            'label' => 'nullable|string|max:128',
            'bands' => 'required|array|min:1',
            'bands.*.rating' => 'required|string|max:32',
            'bands.*.band_key' => 'nullable|string|max:32',
            'bands.*.min_score' => 'required|integer|min:1|max:25',
            'bands.*.max_score' => 'required|integer|min:1|max:25',
            'bands.*.fill_color' => 'nullable|string|max:7',
            'bands.*.text_color' => 'nullable|string|max:7',
            'bands.*.sort_order' => 'nullable|integer|min:0',
            'bands.*.id' => 'nullable|integer',
        ]);

        // Refuse dropping a band key that is already stored on risks.
        $incomingKeys = [];
        foreach ($data['bands'] as $band) {
            $key = strtolower(trim((string) ($band['band_key'] ?? $band['rating'])));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?: strtolower((string) $band['rating']);
            $incomingKeys[$key] = true;
        }
        $canDelete = RiskPermissions::canDeleteLookups($request->attributes->get('risk_permissions', []));
        foreach ($this->bands->workingBands() as $existing) {
            if (isset($incomingKeys[$existing['band_key']])) {
                continue;
            }
            if (! $canDelete) {
                return response()->json([
                    'message' => 'Removing rating bands requires the delete_risk_lookups permission (Admin).',
                ], 403);
            }
            if ($this->bands->bandIsInUse($existing['band_key'], $existing['rating'])) {
                return response()->json([
                    'message' => "Cannot remove band \"{$existing['rating']}\" — it is used on existing risks.",
                ], 422);
            }
        }

        DB::transaction(function () use ($data) {
            DB::table('rr_rating_bands')->delete();
            foreach (array_values($data['bands']) as $i => $band) {
                $fields = $this->normalizeBandFields([
                    'rating' => $band['rating'],
                    'band_key' => $band['band_key'] ?? null,
                    'min_score' => $band['min_score'],
                    'max_score' => $band['max_score'],
                    'fill_color' => $band['fill_color'] ?? null,
                    'text_color' => $band['text_color'] ?? null,
                    'sort_order' => $band['sort_order'] ?? ($i + 1),
                ]);
                if ($fields['min_score'] > $fields['max_score']) {
                    abort(response()->json(['message' => 'min_score cannot exceed max_score.'], 422));
                }
                DB::table('rr_rating_bands')->insert($fields + [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $version = $this->bands->publish($data['label'] ?? null);

        return response()->json([
            'data' => [
                'version' => $version,
                'bands' => $this->bands->workingBands(),
                'active_rating_key_version' => (string) $version,
            ],
        ]);
    }

    private function lookupDeleteBlockReason(string $table, object $row): ?string
    {
        if ($table === 'rr_rating_bands') {
            $key = (string) ($row->band_key ?? '');
            $rating = (string) ($row->rating ?? '');
            if ($this->bands->bandIsInUse($key, $rating)) {
                return 'Cannot delete this rating band — it is used on existing risks.';
            }

            return null;
        }

        $checks = [
            'rr_likelihoods' => ['rr_risks' => 'inherent_likelihood', 'score_col' => 'score'],
            'rr_impacts' => ['rr_risks' => 'inherent_impact', 'score_col' => 'score'],
            'rr_risk_types' => ['rr_risks' => 'risk_type_id'],
            'rr_enterprise_themes' => ['rr_risks' => 'enterprise_theme_id'],
            'rr_statuses' => ['rr_risks' => 'status_id'],
            'rr_mitigation_effectiveness' => ['rr_risks' => 'mitigation_effectiveness_id'],
        ];
        if (! isset($checks[$table])) {
            return null;
        }
        $cfg = $checks[$table];
        $col = $cfg['rr_risks'];
        if (isset($cfg['score_col'])) {
            $score = (int) ($row->{$cfg['score_col']} ?? 0);
            if ($score > 0 && DB::table('rr_risks')->where($col, $score)->exists()) {
                return 'Cannot delete — this value is used on existing risks.';
            }

            return null;
        }
        if (DB::table('rr_risks')->where($col, (int) $row->id)->exists()) {
            return 'Cannot delete — this value is used on existing risks.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalizeBandFields(array $fields): array
    {
        if (isset($fields['rating'])) {
            $fields['rating'] = mb_substr(trim((string) $fields['rating']), 0, 32);
        }
        if (array_key_exists('band_key', $fields)) {
            $key = strtolower(trim((string) ($fields['band_key'] ?? '')));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?: '';
            if ($key === '' && isset($fields['rating'])) {
                $key = strtolower(str_replace(' ', '_', (string) $fields['rating']));
            }
            $fields['band_key'] = mb_substr($key, 0, 32);
        }
        foreach (['fill_color', 'text_color'] as $c) {
            if (! array_key_exists($c, $fields) || $fields[$c] === null || $fields[$c] === '') {
                continue;
            }
            $hex = strtoupper(trim((string) $fields[$c]));
            if (! str_starts_with($hex, '#')) {
                $hex = '#'.$hex;
            }
            if (! preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                unset($fields[$c]);
            } else {
                $fields[$c] = $hex;
            }
        }
        foreach (['min_score', 'max_score', 'sort_order'] as $n) {
            if (isset($fields[$n])) {
                $fields[$n] = (int) $fields[$n];
            }
        }

        return $fields;
    }

    private function assertManage(Request $request): void
    {
        if (! RiskPermissions::canManage($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Forbidden.'], 403));
        }
    }

    private function assertDeleteLookups(Request $request): void
    {
        if (! RiskPermissions::canDeleteLookups($request->attributes->get('risk_permissions', []))) {
            abort(response()->json(['message' => 'Delete requires the delete_risk_lookups permission (Admin).'], 403));
        }
    }
}
