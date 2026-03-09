<?php

use App\Http\Controllers\API\Dashboard\DashboardController;

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

// dashboard
Route::get('/dashboard_alert_center', [DashboardController::class, 'dashboardAlertCenter']);

Route::get('/dashboard_item_section', [DashboardController::class, 'dashboardItemSection']);
Route::post('/action_item_status_change', [DashboardController::class, 'actionItemStatusChange']);

Route::post('/add_list_announcements', [DashboardController::class, 'addListAnnouncements']);

Route::get('/get_list_announcement', [DashboardController::class, 'getListAnnouncement']);

Route::get('/get_standard_announcement_card', [DashboardController::class, 'getStandardAnnouncementCard']);

Route::post('/delete_list_announcements', [DashboardController::class, 'deleteListAnnouncement']);

Route::post('/admin/get_payroll_summary', [DashboardController::class, 'getPayrollSummary']);

Route::post('/salesReport', [DashboardController::class, 'dashboardSalesReport']);

Route::post('/goalsTracker', [DashboardController::class, 'dashboardGoalsTracker']);

Route::post('/setGoals', [DashboardController::class, 'dashboardSetGoals']);

Route::post('/officePerformance', [DashboardController::class, 'dashboardOfficePerformance']);

Route::post('/officePerformanceSelesTeam', [DashboardController::class, 'dashboardOfficePerformanceSelesTeam']);

Route::post('/adminOfficePerformance', [DashboardController::class, 'adminDashboardOfficePerformance']);

Route::post('/adminTopPayRollLocation', [DashboardController::class, 'adminDashboardTopPayRollByLocation']);

Route::post('/officePerformanceSelesKw', [DashboardController::class, 'adminDashboardOfficePerformanceSelesKw']);

Route::post('/event_list', [DashboardController::class, 'eventList']);

Route::post('/announcement_disable', [DashboardController::class, 'announcementDisable']);

Route::post('/office_by_Position_list', [DashboardController::class, 'officeByPositionList']);
