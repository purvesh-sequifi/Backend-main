<?php

use App\Http\Controllers\API\Bucket\BucketController;
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

Route::get('/list_buckets', [BucketController::class, 'list_buckets']);
Route::post('/add_update_bucket', [BucketController::class, 'addUpdateBucket']);
Route::post('/list_buckets_with_jobs', [BucketController::class, 'list_buckets_with_jobs']);
Route::post('/add_job', [BucketController::class, 'add_job']);
Route::post('/move_job', [BucketController::class, 'move_job']);
Route::post('/add_bucket_subtask', [BucketController::class, 'add_bucket_subtask']);
Route::post('/deletesubtask', [BucketController::class, 'deletesubtask']);
Route::post('/subtaskupdate', [BucketController::class, 'subtaskupdate']);
Route::post('/jobinfoupdate', [BucketController::class, 'jobinfoupdate']);

Route::post('/deletebucket', [BucketController::class, 'deletebucket']);
Route::post('/getbucketsubtasks', [BucketController::class, 'getbucketsubtasks']);

     // Users Preference Updated
Route::post('/user_preference_update', [BucketController::class, 'user_preference_update']);
Route::get('/getuserpreference', [BucketController::class, 'getuserpreference']);

Route::post('/jobs_export', [BucketController::class, 'jobs_export'])->name('jobs_export');
Route::post('/jobs_import', [BucketController::class, 'jobs_import'])->name('jobs_import');
Route::post('/testdocupload', [BucketController::class, 'testdocupload']);
