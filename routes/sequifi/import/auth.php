<?php

use App\Http\Controllers\API\V2\Import\ExcelSheetImportController;
use App\Http\Controllers\API\V2\Import\ExcelImportErrorsController;
use Illuminate\Support\Facades\Route;

Route::get('/download-sale-sample', [ExcelSheetImportController::class, 'downloadSaleSample']);
Route::post('/sales-import-validation', [ExcelSheetImportController::class, 'salesImportValidation']);
Route::post('/sales-import', [ExcelSheetImportController::class, 'salesImport']);
Route::get('/excel-import-history', [ExcelSheetImportController::class, 'excelImportHistory']);
Route::get('/excel-import-errors', [ExcelImportErrorsController::class, 'getImportErrors']);
