<?php

use Illuminate\Support\Facades\Route;
use Modules\Lookup\Http\Controllers\Api\V1\LookupController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('lookups/{type}', [LookupController::class, 'show'])->where('type', '[a-z]+');
});
