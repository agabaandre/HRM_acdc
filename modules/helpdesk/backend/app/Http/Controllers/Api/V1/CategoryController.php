<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\HelpdeskCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = HelpdeskCategory::query()
            ->where('is_active', true)
            ->with('businessUnit')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('business_unit_id')) {
            $query->where('business_unit_id', (int) $request->input('business_unit_id'));
        }

        return CategoryResource::collection($query->get());
    }
}
