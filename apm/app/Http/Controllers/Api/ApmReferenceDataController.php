<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Directorate;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\CostItem;
use App\Models\Staff;
use App\Models\Funder;
use App\Models\RequestType;
use App\Models\NonTravelMemoCategory;
use App\Models\Partner;
use App\Models\WorkflowDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApmReferenceDataController extends Controller
{
    /** Allowed reference data keys; if empty, all are returned. */
    private const ALLOWED_KEYS = [
        'divisions',
        'directorates',
        'fund_types',
        'fund_codes',
        'cost_items',
        'staff',
        'funders',
        'request_types',
        'non_travel_categories',
        'partners',
        'workflow_definitions',
    ];

    /**
     * Return reference/lookup data. Optional query param `include`: comma-separated list
     * of keys (e.g. ?include=divisions,staff,fund_types). If omitted, all are returned.
     * GET /reference-data
     * GET /reference-data?include=divisions,staff
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->attributes->get('api_user_session')) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $include = $this->parseInclude($request->query('include'));
        $data = [];

        // All values as lists (arrays) for easy mobile consumption
        if ($include === null || in_array('divisions', $include)) {
            $data['divisions'] = $this->getDivisions()->values()->all();
        }
        if ($include === null || in_array('directorates', $include)) {
            $data['directorates'] = $this->getDirectorates()->values()->all();
        }
        if ($include === null || in_array('fund_types', $include)) {
            $data['fund_types'] = $this->getFundTypes()->values()->all();
        }
        if ($include === null || in_array('fund_codes', $include)) {
            $data['fund_codes'] = $this->getFundCodes()->values()->all();
        }
        if ($include === null || in_array('cost_items', $include)) {
            $data['cost_items'] = $this->getCostItems()->values()->all();
        }
        if ($include === null || in_array('staff', $include)) {
            $data['staff'] = $this->getStaff()->values()->all();
        }
        if ($include === null || in_array('funders', $include)) {
            $data['funders'] = $this->getFunders()->values()->all();
        }
        if ($include === null || in_array('request_types', $include)) {
            $data['request_types'] = $this->getRequestTypes()->values()->all();
        }
        if ($include === null || in_array('non_travel_categories', $include)) {
            $data['non_travel_categories'] = $this->getNonTravelCategories()->values()->all();
        }
        if ($include === null || in_array('partners', $include)) {
            $data['partners'] = $this->getPartners()->values()->all();
        }
        if ($include === null || in_array('workflow_definitions', $include)) {
            $data['workflow_definitions'] = $this->getWorkflowDefinitions()->values()->all();
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Parse optional include param: comma-separated. Null = return all; [] = none (e.g. only invalid keys given).
     */
    private function parseInclude(?string $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $keys = array_map('trim', explode(',', $value));
        $allowed = array_values(array_unique(array_intersect($keys, self::ALLOWED_KEYS)));
        return $allowed;
    }

    private function getDivisions(): Collection
    {
        return Division::orderBy('division_name')->get(['id', 'division_name', 'division_short_name', 'directorate_id'])->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->division_name,
            'short_name' => $row->division_short_name ?? null,
            'directorate_id' => $row->directorate_id ?? null,
        ]);
    }

    private function getDirectorates(): Collection
    {
        return Directorate::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name]);
    }

    private function getFundTypes(): Collection
    {
        return FundType::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name]);
    }

    private function getFundCodes(): Collection
    {
        return FundCode::with(['fundType:id,name', 'funder:id,name', 'partner:id,name'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'activity', 'fund_type_id', 'funder_id', 'partner_id', 'division_id', 'year'])
            ->map(function ($row) {
                $item = ['id' => $row->id, 'code' => $row->code, 'activity' => $row->activity ?? null, 'fund_type_id' => $row->fund_type_id, 'funder_id' => $row->funder_id, 'partner_id' => $row->partner_id, 'division_id' => $row->division_id ?? null, 'year' => $row->year ?? null];
                if ($row->relationLoaded('fundType')) {
                    $item['fund_type_name'] = $row->fundType?->name;
                }
                if ($row->relationLoaded('funder')) {
                    $item['funder_name'] = $row->funder?->name;
                }
                if ($row->relationLoaded('partner')) {
                    $item['partner_name'] = $row->partner?->name;
                }
                return $item;
            });
    }

    private function getCostItems(): Collection
    {
        return CostItem::orderBy('name')->get(['id', 'name', 'cost_type'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'cost_type' => $row->cost_type ?? null]);
    }

    private function getStaff(): Collection
    {
        return Staff::active()->orderBy('fname')->orderBy('lname')->get(['staff_id', 'fname', 'lname', 'title', 'work_email', 'division_id'])->map(fn ($row) => [
            'id' => $row->staff_id,
            'name' => $row->name,
            'title' => $row->title ?? null,
            'work_email' => $row->work_email ?? null,
            'division_id' => $row->division_id ?? null,
        ]);
    }

    private function getFunders(): Collection
    {
        return Funder::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name]);
    }

    private function getRequestTypes(): Collection
    {
        return RequestType::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name]);
    }

    private function getNonTravelCategories(): Collection
    {
        return NonTravelMemoCategory::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name]);
    }

    private function getPartners(): Collection
    {
        return Partner::orderBy('name')->get(['id', 'name'])->map(fn ($row) => ['id' => $row->id, 'name' => $row->name]);
    }

    private function getWorkflowDefinitions(): Collection
    {
        return WorkflowDefinition::with(['workflow:id,workflow_name', 'approvers.staff:staff_id,fname,lname,title', 'approvers.oicStaff:staff_id,fname,lname,title'])
            ->where('is_enabled', true)
            ->orderBy('workflow_id')
            ->orderBy('approval_order')
            ->get()
            ->map(function ($def) {
                $approvers = $def->approvers->map(function ($app) {
                    $name = $app->staff ? trim(collect([$app->staff->title, $app->staff->fname, $app->staff->lname])->filter()->implode(' ')) : null;
                    $oicName = $app->oicStaff ? trim(collect([$app->oicStaff->title, $app->oicStaff->fname, $app->oicStaff->lname])->filter()->implode(' ')) : null;
                    return [
                        'id' => $app->id,
                        'staff_id' => $app->staff_id,
                        'staff_name' => $name,
                        'oic_staff_id' => $app->oic_staff_id,
                        'oic_staff_name' => $oicName,
                    ];
                })->values();
                return [
                    'id' => $def->id,
                    'workflow_id' => $def->workflow_id,
                    'workflow_name' => $def->workflow?->workflow_name,
                    'role' => $def->role,
                    'approval_order' => $def->approval_order,
                    'approvers' => $approvers,
                ];
            });
    }
}
