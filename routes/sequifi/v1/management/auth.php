<?php

use App\Http\Controllers\API\management\OverridesProjectionsController;
use Illuminate\Support\Facades\Route;

Route::get('/generate_user_override', [OverridesProjectionsController::class, 'generate_user_override']);
Route::post('/list_overrides', [OverridesProjectionsController::class, 'list_overrides']);
Route::post('/my_overrides_cards', [OverridesProjectionsController::class, 'my_overrides_cards']);
Route::post('/my_overrides_graph', [OverridesProjectionsController::class, 'my_overrides_graph']);
Route::post('/applicableoverrides', [OverridesProjectionsController::class, 'applicableoverrides']);
