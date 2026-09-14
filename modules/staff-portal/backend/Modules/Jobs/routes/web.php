<?php

use Illuminate\Support\Facades\Route;
use Modules\Jobs\Http\Controllers\JobsController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('jobs', JobsController::class)->names('jobs');
});
