<?php

use App\Http\Controllers\API\V2\MilestoneSchema\MilestoneSchemaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/milestones', [MilestoneSchemaController::class, 'index']);
Route::get('/milestoneshow/{id}', [MilestoneSchemaController::class, 'show']);
Route::get('milestone-audit-logs', [MilestoneSchemaController::class, 'getAuditLogs']);
Route::get('milestone-update-by', [MilestoneSchemaController::class, 'getUpdateByUsers']);
Route::get('milestone-dropdown', [MilestoneSchemaController::class, 'milestoneDropdown']);
Route::get('payment-dropdown/{id}', [MilestoneSchemaController::class, 'paymentDropdown']);
Route::get('milestone-trigger-date', [MilestoneSchemaController::class, 'milestoneTriggerDate']);
Route::post('/add-edit-milestone-schemas', [MilestoneSchemaController::class, 'storeMilestoneSchemas']);
Route::patch('/activatedeactive/{type}/{id}', [MilestoneSchemaController::class, 'activateDeActive']);
Route::post('create-trigger-date', [MilestoneSchemaController::class, 'createTriggerDate']);
Route::post('/delete-milestone-schemas/{id}', [MilestoneSchemaController::class, 'deleteMilestoneSchemas']);
Route::get('unique-schemas', [MilestoneSchemaController::class, 'uniqueSchemas']);
