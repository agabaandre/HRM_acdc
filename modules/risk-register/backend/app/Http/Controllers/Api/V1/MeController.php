<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RiskSettingsService;
use App\Services\StaffPortalOrgClient;
use App\Support\StaffDivisionContext;
use App\Support\StaffPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MeController extends Controller
{
    public function __invoke(Request $request, RiskSettingsService $settings, StaffPortalOrgClient $org): JsonResponse
    {
        $importEnabled = $settings->importEnabled();
        $staffId = (int) $request->attributes->get('risk_staff_id');
        $divisionId = (int) $request->attributes->get('risk_division_id') ?: 0;
        $identity = $this->resolveIdentity($staffId, $org);

        return response()->json([
            'data' => [
                'id' => $staffId > 0 ? $staffId : 0,
                'name' => $identity['name'],
                'email' => $identity['email'],
                'avatar_url' => $identity['avatar_url'],
                'profile' => [
                    'staff_id' => $staffId > 0 ? $staffId : 0,
                    'role' => 'staff',
                    'role_id' => 0,
                    'division_id' => $divisionId > 0 ? $divisionId : null,
                    'permissions' => $request->attributes->get('risk_permissions', []),
                ],
                'division_context' => StaffDivisionContext::payloadFor($staffId, $divisionId),
                'enabled_modules' => [
                    'risks' => true,
                    'dashboard' => true,
                    'reports' => true,
                    'import' => $importEnabled,
                    'reference' => true,
                    'settings' => true,
                ],
                'settings' => [
                    'import_enabled' => $importEnabled,
                ],
            ],
        ]);
    }

    /**
     * @return array{name:string,email:string,avatar_url:?string}
     */
    private function resolveIdentity(int $staffId, StaffPortalOrgClient $org): array
    {
        $fallback = [
            'name' => $staffId > 0 ? ('Staff #'.$staffId) : 'Risk Register user',
            'email' => '',
            'avatar_url' => null,
        ];

        if ($staffId < 1) {
            return $fallback;
        }

        if (Schema::hasTable('staff')) {
            $cols = ['staff_id', 'fname', 'lname', 'oname', 'work_email'];
            if (Schema::hasColumn('staff', 'photo')) {
                $cols[] = 'photo';
            }
            $row = DB::table('staff')->where('staff_id', $staffId)->first($cols);
            if ($row) {
                $fname = trim((string) ($row->fname ?? ''));
                $lname = trim((string) ($row->lname ?? ''));
                $name = trim($fname.' '.$lname);
                if ($name === '') {
                    $name = trim((string) ($row->oname ?? ''));
                }
                if ($name === '') {
                    $names = $org->staffNamesForIds([$staffId]);
                    $name = $names[$staffId] ?? $fallback['name'];
                }

                $photo = isset($row->photo) ? trim((string) $row->photo) : '';

                return [
                    'name' => $name,
                    'email' => trim((string) ($row->work_email ?? '')),
                    'avatar_url' => $this->photoUrl($photo),
                ];
            }
        }

        $names = $org->staffNamesForIds([$staffId]);
        if (isset($names[$staffId]) && $names[$staffId] !== '') {
            $fallback['name'] = $names[$staffId];
        }

        return $fallback;
    }

    /**
     * Same staff.photo filename as Staff Portal — only link when the file resolves.
     */
    private function photoUrl(string $filename): ?string
    {
        if ($filename === '' || ! StaffPhoto::exists($filename)) {
            return null;
        }

        $safe = basename(str_replace('\\', '/', $filename));

        return url('/staff-media/photo/'.$safe);
    }
}
