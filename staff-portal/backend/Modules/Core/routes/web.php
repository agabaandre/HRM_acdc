<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\CbpHome;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/', CbpHome::class)->name('core.home');
});
