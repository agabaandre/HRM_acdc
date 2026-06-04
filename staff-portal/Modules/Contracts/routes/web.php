<?php

use Illuminate\Support\Facades\Route;
use Modules\Contracts\Http\Controllers\ContractsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('contracts', ContractsController::class)->names('contracts');
});
