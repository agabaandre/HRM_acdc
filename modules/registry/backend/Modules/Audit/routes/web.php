<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\AuditController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('audits', AuditController::class)->names('audit');
});
