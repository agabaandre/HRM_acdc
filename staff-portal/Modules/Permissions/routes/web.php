<?php

use Illuminate\Support\Facades\Route;
use Modules\Permissions\Livewire\PermissionsIndex;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/permissions', PermissionsIndex::class)->name('permissions.index');
});
