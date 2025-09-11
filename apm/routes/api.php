<?php

use App\Http\Controllers\Api\SessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Session Management API Routes
Route::get('/validate-session', [SessionController::class, 'validateSession'])->name('api.validate-session');
Route::post('/extend-session', [SessionController::class, 'extendSession'])->name('api.extend-session');
Route::get('/session-status', [SessionController::class, 'getSessionStatus'])->name('api.session-status');
