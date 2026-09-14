<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MeResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): MeResource
    {
        return new MeResource($request->user()->load('helpdeskProfile'));
    }
}
