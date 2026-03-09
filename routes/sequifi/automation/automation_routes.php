<?php

use App\Http\Controllers\API\Automation\AutomationController;

Route::get('listing', [AutomationController::class, 'index']);
Route::post('save', [AutomationController::class, 'store']);
Route::get('edit/{id}', [AutomationController::class, 'show']);
Route::post('update', [AutomationController::class, 'update']);
Route::get('destroy/{id}', [AutomationController::class, 'destroy']);
Route::post('search', [AutomationController::class, 'search']);
Route::get('deactivate/{id}', [AutomationController::class, 'deactivate']);
Route::get('activate/{id}', [AutomationController::class, 'activate']);
Route::get('categories', [AutomationController::class, 'categories']);
Route::get('events/{category}', [AutomationController::class, 'events']);
Route::get('event_actions/{category}', [AutomationController::class, 'getEventActions']);
