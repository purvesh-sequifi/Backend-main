<?php

use App\Http\Controllers\API\V2\CustomFields\CustomFieldsController;
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

Route::post('/add_custom_fields_setting', [CustomFieldsController::class, 'addCustomFieldsSetting']);
Route::post('/delete_custom_fields_setting', [CustomFieldsController::class, 'deleteCustomFieldsSetting']);
Route::get('/lead_column_list', [CustomFieldsController::class, 'leadColumnList']);
Route::get('/get_lead_custom_field_setting', [CustomFieldsController::class, 'getLeadCustomFieldSetting']);
Route::post('/post_lead_custom_field_setting', [CustomFieldsController::class, 'postLeadCustomFieldSetting']);
Route::post('/custom_lead_form_global_settings', [CustomFieldsController::class, 'customLeadFormGlobalSettings']);
