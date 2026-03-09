<?php

use App\Http\Controllers\API\Crmsale\CrmsaleController;
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

// Plaid API

// Route::resource('/',PlaidTransactionController::class);
Route::post('/addcrmjob', [CrmsaleController::class, 'addcrmjob']);
Route::post('/add_comment', [CrmsaleController::class, 'addcomment']);
Route::post('/adddocuments', [CrmsaleController::class, 'adddocuments']);

Route::post('/getcomments', [CrmsaleController::class, 'getcomments']);
Route::post('/getdocuments', [CrmsaleController::class, 'getdocuments']);
Route::post('/deleteattachments', [CrmsaleController::class, 'deleteattachments']);
Route::post('/deletecomment', [CrmsaleController::class, 'deletecomment']);
Route::post('/jobinformation', [CrmsaleController::class, 'jobinformation']);

     Route::post('/buckettaskinfo', [CrmsaleController::class, 'buckettaskinfo']);
Route::post('/listbucketmovejob', [CrmsaleController::class, 'listbucketmovejob']);

Route::post('/activedeactivecrm', [CrmsaleController::class, 'activedeactivecrm']);
Route::post('/crmplanaddupgrade', [CrmsaleController::class, 'crmplanaddupgrade']);
Route::post('/getplanactive', [CrmsaleController::class, 'getplanactive']);

Route::post('/addupdatecustomefield', [CrmsaleController::class, 'addupdatecustomefield']);
Route::post('/deletecustomfield', [CrmsaleController::class, 'deletecustomfield']);
Route::post('/saveupdatecustomfildjob', [CrmsaleController::class, 'saveupdatecustomfildjob']);
Route::get('/getcustomefields', [CrmsaleController::class, 'getcustomefields']);
