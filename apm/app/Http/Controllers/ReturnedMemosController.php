<?php

namespace App\Http\Controllers;

use App\Services\ReturnedMemosService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class ReturnedMemosController extends Controller
{
    protected $returnedMemosService;

    public function __construct()
    {
        // Pass session data to the service
        $sessionData = [
            'staff_id' => user_session('staff_id'),
            'division_id' => user_session('division_id'),
            'permissions' => user_session('permissions', []),
            'name' => user_session('name'),
            'email' => user_session('email'),
            'base_url' => user_session('base_url')
        ];
        
        $this->returnedMemosService = new ReturnedMemosService($sessionData);
    }

    /**
     * Display the returned memos dashboard
     */
    public function index(Request $request): View
    {
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        
        // Get all returned memos
        $returnedMemos = $this->returnedMemosService->getReturnedMemos();
        
        // Get summary statistics
        $summaryStats = $this->returnedMemosService->getSummaryStats();
        
        // Get divisions for filter dropdown
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        
        // Get categories for filter dropdown - group similar items (before filtering)
        $groupedCategories = $this->groupCategoriesForFilter($returnedMemos, $summaryStats);
        
        // Filter by category if specified (after creating grouped categories)
        if ($category !== 'all') {
            $returnedMemos = $this->filterByGroupedCategory($returnedMemos, $category);
        }

        return view('returned-memos.index', [
            'pageConfig' => [
                'csrf' => csrf_token(),
                'filters' => [
                    'category' => $category,
                    'division' => $division,
                ],
                'summaryStats' => $this->serializeReturnedSummaryStats($summaryStats),
                'returnedMemos' => $this->serializeReturnedMemos($returnedMemos),
                'groupedCategories' => $groupedCategories,
                'routes' => [
                    'index' => route('returned-memos.index'),
                    'api' => route('returned-memos.api'),
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $summaryStats
     * @return array<string, mixed>
     */
    private function serializeReturnedSummaryStats(array $summaryStats): array
    {
        $byCategory = $summaryStats['by_category'] ?? [];
        if ($byCategory instanceof \Illuminate\Support\Collection) {
            $byCategory = $byCategory->all();
        }

        return [
            'total_returned' => (int) ($summaryStats['total_returned'] ?? 0),
            'by_category' => array_map('intval', (array) $byCategory),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $returnedMemos
     * @return array<string, list<array<string, mixed>>>
     */
    private function serializeReturnedMemos(array $returnedMemos): array
    {
        $serialized = [];
        foreach ($returnedMemos as $category => $items) {
            $serialized[$category] = array_map(function (array $item): array {
                unset($item['model']);
                if (! empty($item['date_received'])) {
                    try {
                        $item['date_received'] = \Carbon\Carbon::parse($item['date_received'])->toIso8601String();
                    } catch (\Throwable $e) {
                        $item['date_received'] = null;
                    }
                }
                if (! empty($item['submitted_by'])) {
                    $item['submitted_by_text'] = trim(strip_tags((string) $item['submitted_by']));
                } else {
                    $item['submitted_by_text'] = '';
                }

                return $item;
            }, $items);
        }

        return $serialized;
    }

    /**
     * Group categories for filter dropdown
     */
    private function groupCategoriesForFilter(array $returnedMemos, array $summaryStats): array
    {
        $categories = [];
        
        // All Categories
        $categories[] = [
            'value' => 'all',
            'label' => 'All Categories',
            'count' => $summaryStats['total_returned']
        ];
        
        // Matrices
        $matrixCount = count($returnedMemos['Matrix'] ?? []);
        if ($matrixCount > 0) {
            $categories[] = [
                'value' => 'Matrix',
                'label' => 'Matrices',
                'count' => $matrixCount
            ];
        }
        
        // Memos (Special Memo + Non-Travel Memo + Single Memo + Other Memo)
        $memoCount = (count($returnedMemos['Special Memo'] ?? []) +
                     count($returnedMemos['Non-Travel Memo'] ?? []) +
                     count($returnedMemos['Single Memo'] ?? []) +
                     count($returnedMemos['Other Memo'] ?? []));
        if ($memoCount > 0) {
            $categories[] = [
                'value' => 'memos',
                'label' => 'Memos',
                'count' => $memoCount
            ];
        }
        
        // Requests (Service Request + ARF)
        $requestCount = (count($returnedMemos['Service Request'] ?? []) + 
                        count($returnedMemos['ARF'] ?? []));
        if ($requestCount > 0) {
            $categories[] = [
                'value' => 'requests',
                'label' => 'Requests',
                'count' => $requestCount
            ];
        }
        
        // Change Requests
        $changeRequestCount = count($returnedMemos['Change Request'] ?? []);
        if ($changeRequestCount > 0) {
            $categories[] = [
                'value' => 'Change Request',
                'label' => 'Change Requests',
                'count' => $changeRequestCount
            ];
        }
        
        return $categories;
    }

    /**
     * Filter returned memos by grouped category
     */
    private function filterByGroupedCategory(array $returnedMemos, string $category): array
    {
        if ($category === 'all') {
            return $returnedMemos;
        }
        
        $filtered = [];
        
        switch ($category) {
            case 'Matrix':
                $filtered['Matrix'] = $returnedMemos['Matrix'] ?? [];
                break;
            case 'memos':
                $filtered['Special Memo'] = $returnedMemos['Special Memo'] ?? [];
                $filtered['Non-Travel Memo'] = $returnedMemos['Non-Travel Memo'] ?? [];
                $filtered['Single Memo'] = $returnedMemos['Single Memo'] ?? [];
                $filtered['Other Memo'] = $returnedMemos['Other Memo'] ?? [];
                break;
            case 'requests':
                $filtered['Service Request'] = $returnedMemos['Service Request'] ?? [];
                $filtered['ARF'] = $returnedMemos['ARF'] ?? [];
                break;
            case 'Change Request':
                $filtered['Change Request'] = $returnedMemos['Change Request'] ?? [];
                break;
            default:
                // For specific categories, just include that category
                if (isset($returnedMemos[$category])) {
                    $filtered[$category] = $returnedMemos[$category];
                }
                break;
        }
        
        return $filtered;
    }

    /**
     * Get filter options for AJAX requests
     */
    public function getFilterOptions(): JsonResponse
    {
        $returnedMemos = $this->returnedMemosService->getReturnedMemos();
        $summaryStats = $this->returnedMemosService->getSummaryStats();
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        $groupedCategories = $this->groupCategoriesForFilter($returnedMemos, $summaryStats);
        
        return response()->json([
            'success' => true,
            'categories' => $groupedCategories,
            'divisions' => $divisions->map(function ($division) {
                return [
                    'id' => $division->id,
                    'name' => $division->division_name
                ];
            })
        ]);
    }

    /**
     * Get returned memos data for AJAX requests
     */
    public function getReturnedMemosData(Request $request): JsonResponse
    {
        $category = $request->get('category', 'all');
        $division = $request->get('division', 'all');
        
        $returnedMemos = $this->returnedMemosService->getReturnedMemos();
        $summaryStats = $this->returnedMemosService->getSummaryStats();
        
        // Filter by category if specified
        if ($category !== 'all') {
            $returnedMemos = $this->filterByGroupedCategory($returnedMemos, $category);
        }
        
        return response()->json([
            'success' => true,
            'returnedMemos' => $returnedMemos,
            'summaryStats' => $summaryStats
        ]);
    }
}
