<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AskHelpdeskRequest;
use App\Services\HelpdeskAskService;
use Illuminate\Http\JsonResponse;

class HelpdeskAskController extends Controller
{
    public function __invoke(AskHelpdeskRequest $request, HelpdeskAskService $service): JsonResponse
    {
        $data = $service->ask($request->user(), (string) $request->validated('question'));

        return response()->json(['data' => $data]);
    }
}
