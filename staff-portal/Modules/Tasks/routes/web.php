<?php

use Illuminate\Support\Facades\Route;
use Modules\Tasks\Livewire\TasksHub;
use Modules\Tasks\Livewire\WeeklyTasks;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/tasks', TasksHub::class)->name('tasks.index');
    Route::get('/tasks/activity', TasksHub::class)->name('tasks.activities');
    Route::get('/weektasks/tasks', WeeklyTasks::class)->name('tasks.weekly');
});
