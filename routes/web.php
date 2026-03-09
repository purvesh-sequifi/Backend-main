<?php

use App\Http\Controllers\Account\SettingsController;
use App\Http\Controllers\Auth\SocialiteLoginController;
use App\Http\Controllers\CommissionCalculaterController;
use App\Http\Controllers\Documentation\LayoutBuilderController;
use App\Http\Controllers\Documentation\ReferencesController;
use App\Http\Controllers\Logs\AuditLogsController;
use App\Http\Controllers\Logs\SystemLogsController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\Web\FeatureFlagAuthController;
use App\Http\Controllers\Web\FeatureFlagWebController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

// Admin Portal Routes (Consolidated)
Route::prefix('admin')->middleware(['web'])->group(function () {
    // Public Authentication Routes
    Route::get('/login', [FeatureFlagAuthController::class, 'showLoginForm'])
        ->name('admin.login');
    Route::post('/login', [FeatureFlagAuthController::class, 'login'])
        ->name('admin.authenticate');
    Route::post('/logout', [FeatureFlagAuthController::class, 'logout'])
        ->name('admin.logout');
    
    // Protected Admin Routes (Super Admin Only)
    Route::middleware(['feature-flags.auth'])->group(function () {
        Route::get('/', function () {
            return view('admin.dashboard');
        })->name('admin.dashboard');
    });
});

// Feature Flags Management (Super Admin Only - Protected)
Route::prefix('feature-flags')
    ->middleware(['web', 'feature-flags.auth'])
    ->group(function () {
        Route::get('/', [FeatureFlagWebController::class, 'index'])
            ->name('feature-flags.index');
        Route::post('/toggle', [FeatureFlagWebController::class, 'toggle'])
            ->name('feature-flags.toggle');
        Route::get('/{feature}/usage', [FeatureFlagWebController::class, 'getUsage'])
            ->name('feature-flags.usage');
    });
