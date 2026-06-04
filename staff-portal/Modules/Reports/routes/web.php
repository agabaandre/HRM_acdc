<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\ReportsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('reports', ReportsController::class)->names('reports');
});
