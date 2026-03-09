<?php

use App\Http\Controllers\API\management\DocumentController;
use App\Http\Controllers\API\management\EmployeeManagementController;
use App\Http\Controllers\API\management\TeamManagementController;

// management-employee
Route::post('/management-employee', [EmployeeManagementController::class, 'index'])->name('employee-report');
Route::get('/management-employee/{name}', [EmployeeManagementController::class, 'search'])->name('search-employee');
Route::post('/management-employee-filter', [EmployeeManagementController::class, 'filter'])->name('filter-employee');
// Route::post('/template-employee-filter',[EmployeeManagementController::class,'filterTemplate']);
Route::post('/managementEmployeeListByStateIds', [EmployeeManagementController::class, 'managementEmployeeListByStateIds']);
Route::post('/managementEmployeeList', [EmployeeManagementController::class, 'managementEmployeeList']);
Route::post('/userManagementList', [EmployeeManagementController::class, 'userManagementList']);
Route::post('/employeeListByOfficeID', [EmployeeManagementController::class, 'getmanagementEmployeeListByOfficeID']);
Route::post('/updateUserImage', [EmployeeManagementController::class, 'updateImage']);
Route::post('/updateDeviceToken', [EmployeeManagementController::class, 'updateDeviceToken']);
Route::get('getNotificationList', [EmployeeManagementController::class, 'getNotificationList']);
Route::get('sendOnesignalPushNotificationios', [EmployeeManagementController::class, 'sendOnesignalPushNotificationios']);
Route::get('/employee_network/{id}', [EmployeeManagementController::class, 'employee_network'])->name('employee_network');
Route::get('/my_overrides/{id}', [EmployeeManagementController::class, 'my_overrides'])->name('my_overrides');
Route::put('/my_overrides_enable_disable', [EmployeeManagementController::class, 'OverridesEnableDisable'])->name('my_overrides_enable_disable');
Route::get('/export_user', [EmployeeManagementController::class, 'exportUsersData'])->name('exportUserManagement');

Route::get('/export-user-management', [EmployeeManagementController::class, 'exportUserManagement']);

Route::get('/exportStandardEmployeeExport', [EmployeeManagementController::class, 'exportStandardEmployeeExport'])->name('exportStandardEmployeeExport');

Route::get('/mysale_overrides/{id}', [EmployeeManagementController::class, 'mysale_overrides'])->name('mysale_overrides');
Route::get('/get_mysale_overrides/{id}', [EmployeeManagementController::class, 'get_mysale_overrides']);
Route::post('/get_mysale_overrides', [EmployeeManagementController::class, 'get_mysale_overrides']);
Route::post('/manual_overrides', [EmployeeManagementController::class, 'manual_overrides'])->name('manual_overrides');
Route::post('/edit_manual_overrides', [EmployeeManagementController::class, 'edit_manual_overrides'])->name('edit_manual_overrides');
Route::post('/manual_overrides_from', [EmployeeManagementController::class, 'manual_overrides_from'])->name('manual_overrides_from');
Route::post('/edit_manual_overrides_from', [EmployeeManagementController::class, 'edit_manual_overrides_from']);
Route::get('/delete_manual_overrides/{id}', [EmployeeManagementController::class, 'delete_manual_overrides']);
Route::post('/delete_manual_overrides', [EmployeeManagementController::class, 'delete_manual_overrides']);

Route::post('/AddDocumentBYUserId', [DocumentController::class, 'AddDocumentBYUserId']);
Route::post('/createTemporary', [DocumentController::class, 'CreateTemporary']);
Route::get('/getDocumentBYUserId/{id}', [DocumentController::class, 'DocumentListBYUserId']);
Route::post('/updateDocumentBYUserId', [DocumentController::class, 'updateDocumentListBYUserId']);
Route::delete('/deleteDocumentBYUserId/{id}', [DocumentController::class, 'deleteDocumentListBYUserId']);
Route::get('/document-type-dropdown', [DocumentController::class, 'documentType']);
Route::post('/DocumentList', [DocumentController::class, 'DocumentList']);

// Management-team
Route::resource('/management-team', TeamManagementController::class);
Route::put('/update-management-team', [TeamManagementController::class, 'edit']);
Route::get('/search-management-team-employee/{name}', [TeamManagementController::class, 'search']);
route::post('/filter-management-team-employee', [TeamManagementController::class, 'filter']);
route::post('/delete-management-team-member', [TeamManagementController::class, 'delete']);
route::get('/dropdown-team-management', [TeamManagementController::class, 'dropdown']);
route::get('/select-team-management/{id}', [TeamManagementController::class, 'selectTeam'])->name('select_team');
route::get('/delete-team/{id}', [TeamManagementController::class, 'deleteTeamWhenMemeberNotadded']);

route::get('/delete_team/{id}', [TeamManagementController::class, 'deleteTeam']);

Route::get('/get-all-users-by-manger/{id}', [EmployeeManagementController::class, 'getAllUsersByManager'])->name('get-all-users-by-manger');
Route::put('/update-user-manager', [EmployeeManagementController::class, 'updateUserManager']);

Route::post('/my_overrides_filter', [EmployeeManagementController::class, 'my_overrides_filter'])->name('my_overrides_filter');

Route::post('/override_system_status', [EmployeeManagementController::class, 'overrideSettingStatus']);
Route::get('/get_override_system_status', [EmployeeManagementController::class, 'getOverrideSettingStatus']);
Route::put('/make_user_group_admin',[EmployeeManagementController::class, 'make_user_group_admin']);
Route::put('/suspend_user_access',[EmployeeManagementController::class, 'suspend_user_access']);
