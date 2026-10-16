<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StaffDirectoryLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItReferenceController extends Controller
{
    public function index(StaffDirectoryLookupService $directory): JsonResponse
    {
        return response()->json([
            'data' => [
                'divisions' => $directory->divisionsForSelect(),
            ],
        ]);
    }

    public function staff(Request $request, StaffDirectoryLookupService $directory): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => [
                'staff' => $directory->searchStaff($q),
            ],
        ]);
    }
}
