<?php

use App\Http\Controllers\API\Plaid\PlaidTransactionController;

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

Route::post('/public_token/create', [PlaidTransactionController::class, 'publicToken']);

Route::post('/item/public_token/exchange', [PlaidTransactionController::class, 'publicTokenExchange']);

Route::post('/auth/get', [PlaidTransactionController::class, 'authGet']);

Route::post('/identity/get', [PlaidTransactionController::class, 'identityGet']);

Route::post('/transactions/get', [PlaidTransactionController::class, 'transactionsGet']);

Route::post('/identity_verification/get', [PlaidTransactionController::class, 'identityVerification']);
