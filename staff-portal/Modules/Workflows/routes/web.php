<?php

use Illuminate\Support\Facades\Route;
use Modules\Workflows\Http\Controllers\WorkflowsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('workflows', WorkflowsController::class)->names('workflows');
});
