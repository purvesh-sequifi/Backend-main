<?php

use App\Http\Controllers\TestDbController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Database Read/Write Split Test Routes
|--------------------------------------------------------------------------
|
| These routes are for testing the database read/write splitting feature.
| They should be removed in production.
|
*/

Route::prefix('test-db')->group(function () {
    // Create test table if it doesn't exist
    Route::get('/create-table', [TestDbController::class, 'createTestTable']);

    // Test read operations
    Route::get('/read', [TestDbController::class, 'testRead']);

    // Test write operations
    Route::post('/write', [TestDbController::class, 'testWrite']);

    // Test read after write (sticky connections)
    Route::get('/read-after-write', [TestDbController::class, 'testReadAfterWrite']);
});
