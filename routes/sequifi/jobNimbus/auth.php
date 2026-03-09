
<?php

use App\Http\Controllers\API\JobNimbus\configController;
use App\Http\Controllers\API\JobNimbus\ContactsController;
use App\Http\Controllers\API\JobNimbus\JobsControllerller;
use Illuminate\Support\Facades\Route;

// $token = 'your_api_token'; // Replace with your actual API token
// routing for jobnimbus contacts
Route::get('/contacts', [ContactsController::class, 'index'])->name('contacts.index');
Route::post('/contacts', [ContactsController::class, 'store'])->name('contacts.store');
Route::put('/contacts/{jnid}', [ContactsController::class, 'update'])->name('contacts.update');
Route::get('/contacts/{jnid}', [ContactsController::class, 'show'])->name('contacts.show');

// routing for jobnimbus jobs
Route::get('/jobs', [JobsControllerller::class, 'index'])->name('jobs.index');
Route::post('/jobs', [JobsControllerller::class, 'store'])->name('jobs.store');
Route::put('/jobs/{jnid}', [JobsControllerller::class, 'update'])->name('jobs.update');
Route::get('/jobs/{jnid}', [JobsControllerller::class, 'show'])->name('jobs.show');

// config routes
Route::post('config', [configController::class, 'JobNimbusConfig']);
