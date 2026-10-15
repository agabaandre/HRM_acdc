<?php

use Illuminate\Support\Facades\Route;
use Modules\Lookup\Http\Controllers\ListsApiController;

/** CI3 HMVC paths: lists/jobs, lists/divisions, … */
Route::middleware('web')->prefix('lists')->group(function (): void {
    Route::get('{type}', [ListsApiController::class, 'show'])->where('type', '[a-z]+');
});
