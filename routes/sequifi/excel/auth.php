<?php

use App\Http\Controllers\API\Excel\ExcelImportExportController;
use App\Http\Controllers\API\Plaid\PlaidController;

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

// Excel File upload

Route::resource('/import-excel', ExcelImportExportController::class);

Route::post('/excel-import', [ExcelImportExportController::class, 'excelImport']);

Route::post('/legacy_data', [ExcelImportExportController::class, 'LegacyData']);

Route::post('/excel_import_user_list', [ExcelImportExportController::class, 'UserImport']);
Route::post('/excel_import_onboarding_user', [ExcelImportExportController::class, 'onboardingUserImport']);

Route::post('/legacy_data_update', [ExcelImportExportController::class, 'LegacyDataUpdate']);

Route::post('/createLinkToken', [PlaidController::class, 'index']);
Route::post('/plaid', [PlaidController::class, 'publicToken']);
Route::post('/plaid-exchange', [PlaidController::class, 'ExchangePlaid']);
Route::post('/Retrieve-Auth', [PlaidController::class, 'RetrieveAuth']);

Route::post('/clawbackSalesExport', [ExcelImportExportController::class, 'exportClawbackData']);

Route::get('/get_excel_import_list', [ExcelImportExportController::class, 'getexcelImportList']);
Route::get('/get_user_excel_import_list', [ExcelImportExportController::class, 'getUserexcelImportList']);
