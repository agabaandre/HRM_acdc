<?php

use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Session Management API Routes
Route::get('/validate-session', [SessionController::class, 'validateSession'])->name('api.validate-session');
Route::post('/extend-session', [SessionController::class, 'extendSession'])->name('api.extend-session');
Route::get('/session-status', [SessionController::class, 'getSessionStatus'])->name('api.session-status');
Route::get('/session-debug', [SessionController::class, 'getSessionDebug'])->name('api.session-debug');

// Logout API route (called from CodeIgniter logout)
Route::post('/logout', [AuthController::class, 'apiLogout'])->name('api.logout');
