<?php

use Illuminate\Support\Facades\Route;
use Modules\Permissions\Http\Controllers\Api\V1\PermissionsApiController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::get('permissions/bootstrap', [PermissionsApiController::class, 'bootstrap']);
    Route::get('permissions/catalog', [PermissionsApiController::class, 'catalog']);
    Route::get('permissions/groups', [PermissionsApiController::class, 'groups']);
    Route::post('permissions/groups', [PermissionsApiController::class, 'storeGroup']);
    Route::get('permissions/groups/{id}/assignments', [PermissionsApiController::class, 'groupAssignments'])
        ->whereNumber('id');
    Route::put('permissions/groups/{id}/assignments', [PermissionsApiController::class, 'updateGroupAssignments'])
        ->whereNumber('id');
    Route::get('permissions/users', [PermissionsApiController::class, 'users']);
    Route::get('permissions/users/{id}/assignments', [PermissionsApiController::class, 'userAssignments'])
        ->whereNumber('id');
    Route::put('permissions/users/{id}/assignments', [PermissionsApiController::class, 'updateUserAssignments'])
        ->whereNumber('id');
    Route::post('permissions/users/{id}/copy-group', [PermissionsApiController::class, 'copyGroupToUser'])
        ->whereNumber('id');
    Route::post('permissions/definitions', [PermissionsApiController::class, 'storeDefinition']);
});
