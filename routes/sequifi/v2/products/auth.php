<?php

use App\Http\Controllers\API\V2\Products\ProductController;
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

Route::get('/products', [ProductController::class, 'index']);
Route::post('/add-edit-products', [ProductController::class, 'storeProducts']);
Route::get('/productshow/{id}', [ProductController::class, 'show']);
Route::patch('/{type}/{id}', [ProductController::class, 'activateDeActive']);
Route::get('product-dropdown', [ProductController::class, 'productDropdown']);
Route::get('product-audit-logs', [ProductController::class, 'getAuditLogs']);
Route::get('product-by-position/{id}', [ProductController::class, 'productByPosition']);
Route::get('product-by-milestone/{id}', [ProductController::class, 'productByMilestone']);
Route::get('product-update-by', [ProductController::class, 'getUpdateByUsers']);
Route::post('/product-details', [ProductController::class, 'productDetails']);
Route::post('product-dropdown-by-reps', [ProductController::class, 'productDropdown']);
