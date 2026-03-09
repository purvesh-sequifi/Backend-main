<?php

use App\Http\Controllers\API\TicketSystem\Confluence\ConfluenceController;
use App\Http\Controllers\API\TicketSystem\Faq\FaqController;
use App\Http\Controllers\API\TicketSystem\Module\TicketModuleController;
use App\Http\Controllers\API\TicketSystem\Ticket\TicketController;
use Illuminate\Support\Facades\Route;

// Ticket Faq's API
Route::get('/faqs', [FaqController::class, 'index']);

// Ticket API
Route::get('/', [TicketController::class, 'index']);
Route::post('/store', [TicketController::class, 'store']);
Route::get('/view/{id}', [TicketController::class, 'view']);
Route::post('/delete/{id}', [TicketController::class, 'delete']);
Route::post('/sync', [TicketController::class, 'sync']);

// Ticket Module API
Route::get('/module/dropdown', [TicketModuleController::class, 'dropdown']);

Route::prefix('confluence')->group(function () {
    Route::get('/', [ConfluenceController::class, 'index']);
    Route::get('/view', [ConfluenceController::class, 'view']);
    Route::get('/dropdown/filter', [ConfluenceController::class, 'dropdownFilter']);
});
