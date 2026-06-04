<?php

use Illuminate\Support\Facades\Route;
use Modules\Workplan\Livewire\WorkplanIndex;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/workplan', WorkplanIndex::class)->name('workplan.index');
    Route::redirect('/workplans', '/workplan');
});
