<?php

use App\Http\Controllers\API\Permission\GroupPermissionsController;
use App\Http\Controllers\API\Permission\PermissionsController;

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

// Plaid API

// Route::resource('/',PlaidTransactionController::class);

Route::resource('/user_permission', PermissionsController::class);

Route::get('/postion_list', [PermissionsController::class, 'postion_list']);

Route::get('/module_list', [PermissionsController::class, 'module_list']);

Route::post('/submodule_list', [PermissionsController::class, 'submodule_list']);

Route::post('/permissionmodule_list', [PermissionsController::class, 'permissionmodule_list']);

Route::post('/send_onesignal_email', [PermissionsController::class, 'sendOnesignalPushNotificationios']);

// Group Permissions Routes

Route::get('/group_policies_list', [GroupPermissionsController::class, 'index']);
Route::get('/get_permission/{id}', [GroupPermissionsController::class, 'get_permission']);
Route::post('/add_group_permission', [GroupPermissionsController::class, 'addGroupPermission']);
Route::get('/group_list', [GroupPermissionsController::class, 'group_list']);
Route::get('/policies_list', [GroupPermissionsController::class, 'policies_list']);

Route::get('/delete_permission/{id}', [GroupPermissionsController::class, 'delete_permission']);
Route::post('/update_group_permission', [GroupPermissionsController::class, 'updateGroupPermission']);
Route::post('/update_user_group', [GroupPermissionsController::class, 'updateUserGroup']);
Route::get('/group_by_user_list', [GroupPermissionsController::class, 'groupByUserList']);
