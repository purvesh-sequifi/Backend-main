<?php

use App\Http\Controllers\API\AlertController;
use App\Http\Controllers\API\ApiMissingDataController;
use App\Http\Controllers\API\AppReset\AppResetController;
use App\Http\Controllers\API\CloserIdentifyController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\CostCenterController;
use App\Http\Controllers\API\CRMController;
use App\Http\Controllers\API\DepartmentController;
use App\Http\Controllers\API\Dropdown\DropdownController;
use App\Http\Controllers\API\Hiring\Filter\OnboardingFilterController;
use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\PastAccountAlertController;
use App\Http\Controllers\API\PositionController;
use App\Http\Controllers\API\SalesOfficesController;
use App\Http\Controllers\API\SetterIdentifyController;
use App\Http\Controllers\API\Setting\PositionCommissionController;
use App\Http\Controllers\API\SetupController;
use App\Http\Controllers\CompanyBillingAddressController;
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

// Protecting Routes

Route::get('/get-company-profile', [CompanyController::class, 'getCompanyProfile']);
Route::post('/store-company-profile', [CompanyController::class, 'storeCompanyProfile']);
Route::get('/company-setup-status', [CompanyController::class, 'getCompanySetupStatus']);
Route::post('/update-company-profile', [CompanyController::class, 'updateCompanyProfile']);
Route::post('/bankInfo', [CompanyController::class, 'bankInfo']);
Route::post('/employee', [CompanyController::class, 'employee']);

Route::get('/get-business-address', [CompanyController::class, 'getBusinessAddress']);
Route::post('/update-business-address', [CompanyController::class, 'updateBusinessAaddress']);
Route::post('/update_company_margin', [CompanyController::class, 'updateCompanyMargin']);
Route::get('/get_company_margin', [CompanyController::class, 'getCompanyMargin']);

// Billing module Address routes
Route::get('/get_billing_and_business_address', [CompanyBillingAddressController::class, 'get_billing_and_business_address']);
Route::post('/update_billing_address', [CompanyBillingAddressController::class, 'update_billing_address']);

Route::post('/setup-active-inactive', [SetupController::class, 'status']);
Route::post('/deduct-any-available-reconciliation', [SetupController::class, 'deduct_any_available_reconciliation']);
Route::get('/deduct-any-available-reconciliation-status', [SetupController::class, 'get_deduct_any_available_reconciliation_status']);
Route::post('advance-payment-setting', [SetupController::class, 'AdvancePaymentSetting']);
Route::get('/get-advance-payment-setting', [SetupController::class, 'getAdvancePaymentSetting']);

Route::post('timesheet-approval-setting', [SetupController::class, 'timesheetApprovalSetting']);
Route::get('/get-timesheet-approval-setting', [SetupController::class, 'getTimesheetApprovalSetting']);
Route::get('/is-approval-popup-visible/{id}', [SetupController::class, 'isApprovalPopupVisible']);

Route::get('/getReconciliationSchedule', [SetupController::class, 'getReconciliationSchedule']);
Route::get('/get-company-setting', [SetupController::class, 'getCompanySettingList']);
Route::put('/updateReconciliationSchedule', [SetupController::class, 'updateReconciliationSchedule']);
Route::delete('/deleteReconciliationSchedule', [SetupController::class, 'deleteReconciliationSchedule']);

Route::get('/get-overrides', [SetupController::class, 'getoverrides']);
Route::put('/overrides-update', [SetupController::class, 'updateOverrides']);
Route::get('/get-upfront-setting', [SetupController::class, 'getUpfrontSetting']);
Route::put('/add-update-upfront-setting-update', [SetupController::class, 'AddUpdateUpfrontSetting']);

Route::get('/get-marketing-reconciliation', [SetupController::class, 'getMarketingdeal']);
Route::put('/marketing-reconciliation-update', [SetupController::class, 'updateMarketingdeal']);
Route::get('/get-margin-difference', [SetupController::class, 'getMargin']);
Route::put('/margin-difference-update', [SetupController::class, 'updateMargin']);
Route::get('/get-tier-duration', [SetupController::class, 'getTierDuration']);
Route::put('/tier-duration-level-update', [SetupController::class, 'updateTierDuration']);
Route::get('/get-tier-configure-list/{id}', [SetupController::class, 'getconfigure']);
Route::post('/create-tier-configure', [SetupController::class, 'createconfigure']);
// Route::put('/update-tier-configure',[SetupController::class, 'updateconfigure']);
Route::delete('/delete-tier-configure/{id}', [SetupController::class, 'deleteconfigure']);
Route::get('/get-company-payrolls', [SetupController::class, 'getCompanyPayroll']);
Route::put('/Company-payroll-update', [SetupController::class, 'updateCompanyPayroll']);
Route::post('/add_domain_setting', [SetupController::class, 'addDomainSetting']);
Route::post('/update_domain_setting', [SetupController::class, 'updateDomainSetting']);
Route::get('/get_domain_setting', [SetupController::class, 'getDomainSetting']);
Route::delete('/delete_domain_setting/{id}', [SetupController::class, 'deleteDomainSetting']);
// Drag And Drop Image Upload api
Route::post('/image_drag_and_drop_setting', [SetupController::class, 'imageDragAndDropSetting']);

// Location
Route::post('/get-locations-list', [LocationController::class, 'index']);
Route::post('/location_filter', [LocationController::class, 'locationFilter']);
Route::get('/getFutureRedLineByLocation/{location_id}', [LocationController::class, 'getFutureRedLineByLocation']);
Route::resource('locations', LocationController::class);
Route::put('/update-locations', [LocationController::class, 'update']);
Route::delete('/additionalLocationDelete/{id}', [LocationController::class, 'additionalLocationDelete']);
Route::post('/search', [LocationController::class, 'search']);
Route::get('/export_location', [LocationController::class, 'exportLocationData']);

// Sales Offices
Route::get('/sales-offices-list', [SalesOfficesController::class, 'index']);
Route::get('/sales-offices-users/{id}', [SalesOfficesController::class, 'usersByOfficeID']);
Route::post('/create-move-reps', [SalesOfficesController::class, 'createMoveReps']);
Route::get('/user-history/{id}', [SalesOfficesController::class, 'userHistory']);
Route::get('/office-location-list', [SalesOfficesController::class, 'getLocation']);
Route::get('/sales-offices-user-list/{id}', [SalesOfficesController::class, 'userList']);

Route::get('/frequency-type-dropdown-list', [DropdownController::class, 'frequencyList']);
Route::post('/create_weekly_pay_frequency', [SetupController::class, 'createWeeklyPayFrequency']);
Route::post('/create_weekly_pay_frequency1', [SetupController::class, 'createWeeklyPayFrequency1']);
Route::post('/create_monthly_pay_frequency', [SetupController::class, 'createMonthlyPayFrequency']);
Route::post('/AddPayfrequencySetting', [SetupController::class, 'AddPayfrequencySetting']);
Route::get('/listPayfrequencySetting', [SetupController::class, 'listPayfrequencySetting']);

Route::post('/email_notification_setting', [SetupController::class, 'emailNotificationSetting']);
Route::get('/get_email_notification_setting', [SetupController::class, 'getEmailNotificationSetting']);
Route::get('/get_everee_crms_setting', [SetupController::class, 'getEvereeCrmsSetting']);

// Department
Route::get('/drop-down-department', [DepartmentController::class, 'dropdown']);
Route::resource('department', DepartmentController::class);
Route::put('/update-department', [DepartmentController::class, 'update']);
Route::put('/delete-department', [DepartmentController::class, 'delete']);
Route::get('/department-people', [DepartmentController::class, 'departmentPeople']);

// cost-center
Route::post('/cost-center-list', [CostCenterController::class, 'index']);
Route::resource('cost-center', CostCenterController::class);
Route::put('cost-center-disable', [CostCenterController::class, 'disableCostCenter']);

// Alert
Route::get('get-marketing-deal-alert', [AlertController::class, 'getMarketingDealAlert']);
Route::get('get-incomplete-account-alert', [AlertController::class, 'getIncompleteAccountAlert']);
Route::resource('alert', AlertController::class);
Route::post('update-status-alert', [AlertController::class, 'updatestatus']);
Route::post('alert-marketing-deal', [AlertController::class, 'updateMarketingdeal']);
Route::post('alert-incomplete-account', [AlertController::class, 'updateIncompleteAccount']);
Route::put('alert-enable-disable', [AlertController::class, 'enableDisableAlert']);

Route::get('/positions_status/{id}', [PositionCommissionController::class, 'positionsStatus']);
Route::resource('position-commission', PositionCommissionController::class);
Route::get('get-all-position-commission', [PositionCommissionController::class, 'getallpositioncommission']);
Route::post('/add-position', [PositionCommissionController::class, 'Addposition']);
Route::put('/update-position/{id}', [PositionCommissionController::class, 'Updateposition']);
Route::delete('/delete-position/{id}', [PositionCommissionController::class, 'Deleteposition']);
Route::post('search-position-commission', [PositionCommissionController::class, 'search']);
Route::post('update_position_hierarchy', [PositionCommissionController::class, 'update_position_hierarchy']);
Route::put('/update-position-group/{id}', [PositionCommissionController::class, 'Updategroup']);
Route::get('/positionOrgChartByID/{id}', [PositionCommissionController::class, 'positionOrgChartByID']);
Route::get('/positionOrgChart', [PositionCommissionController::class, 'orgChart']);
Route::get('/checkReconciliationSetting', [PositionCommissionController::class, 'checkReconciliationSetting']);
Route::get('/get_data_position_from_department', [PositionCommissionController::class, 'getDataPositionFromDepartment']);
Route::get('/get_position_from_department_package_update', [PositionCommissionController::class, 'getPositionFromDepartmentPackageUpdate']);

Route::post('/position_commission_create', [PositionCommissionController::class, 'positionCommissionCreate']);
Route::post('/position_commission_upfront', [PositionCommissionController::class, 'PositionCommissionUpfront']);
Route::post('/position_commission_deduction', [PositionCommissionController::class, 'PositionCommissionDeduction']);
Route::post('/position_commission_override', [PositionCommissionController::class, 'PositionCommissionOverride']);
Route::post('/position_commission_settelement', [PositionCommissionController::class, 'PositionCommissionSettelement']);
Route::post('/position_commission_payfrequency', [PositionCommissionController::class, 'PositionCommissionPayfrequency']);
Route::delete('/delete_position_deduction/{id}', [PositionCommissionController::class, 'deletePositionDeduction']);

//  CRM Setting
Route::get('/crm_setting_list', [CRMController::class, 'crmSettingList']);
Route::get('/get_crm_setting_by_id/{id}', [CRMController::class, 'getCrmSettingById']);
Route::post('/crm_setting', [CRMController::class, 'crmSetting']);
Route::post('/crm_setting_update', [CRMController::class, 'crmSettingUpdates']);
Route::post('/crm_setting_disconnect', [CRMController::class, 'crmSettingActiveInactive']);

// Positions
// Route::resource('position', PositionController::class);

// Dropdown
Route::get('/commission-by-position-id-dropdown/{id}', [DropdownController::class, 'commission']);
Route::get('/department-dropdown', [DropdownController::class, 'department']);
Route::get('/hiring-status-list', [DropdownController::class, 'hiringstatus']);
// Route::get('/manager-list-dropdown',[DropdownController::class,'manager']);
Route::get('/manager-list-dropdown/{position_id}', [DropdownController::class, 'manager']);
Route::get('/manager-office-list-dropdown/{id}', [DropdownController::class, 'managerOfficeDropdown']);
Route::get('weeklyPayFrequencyDropdown', [DropdownController::class, 'weeklyPayFrequencyDropdown']);
Route::get('dailyPayFrequencyDropdown', [DropdownController::class, 'DailyPayFrequencyDropdown']);

Route::get('monthlyPayFrequencyDropdown', [DropdownController::class, 'monthlyPayFrequencyDropdown']);
Route::get('weeklyPayFrequencyExecutedDropdown', [DropdownController::class, 'weeklyPayFrequencyExecutedDropdown']);
Route::get('monthlyPayFrequencyExecutedDropdown', [DropdownController::class, 'monthlyPayFrequencyExecutedDropdown']);
Route::get('weeklyPayFrequencyDropdownAll', [DropdownController::class, 'weeklyPayFrequencyDropdownAll']);
Route::get('monthlyPayFrequencyDropdownAll', [DropdownController::class, 'monthlyPayFrequencyDropdownAll']);
Route::get('/getUserByOfficeID/{id}', [DropdownController::class, 'getUserByOfficeID']);
Route::get('/getUserByOfficeIDForTeamMember/{id}', [DropdownController::class, 'getUserByOfficeIDForTeamMember']);
Route::get('/getUserByOfficeIDForTeamLead/{id}', [DropdownController::class, 'getUserByOfficeIDForTeamLead']);
Route::get('/managerList', [DropdownController::class, 'managerList']);
Route::post('/managerList_by_effective_date', [DropdownController::class, 'managerList_by_effective_date']);

// Bi Weekly Dropdown API
Route::get('bi-weekly-frequency-dropdown', [DropdownController::class, 'biWeeklyFrequencyDropdown']);
Route::get('bi-weekly-frequency-dropdown-all', [DropdownController::class, 'biWeeklyFrequencyDropdownAll']);
Route::get('bi-weekly-executed-frequency-dropdown', [DropdownController::class, 'biWeeklyExecutedFrequencyDropdown']);

// Semi Monthly Dropdown API
Route::get('semi-monthly-frequency-dropdown', [DropdownController::class, 'semiMonthlyFrequencyDropdown']);
Route::get('semi-monthly-frequency-dropdown-all', [DropdownController::class, 'semiMonthlyFrequencyDropdownAll']);
Route::get('semi-monthly-executed-frequency-dropdown', [DropdownController::class, 'semiMonthlyExecutedFrequencyDropdown']);

// Route::get('/compensation-plan-by-position-id-dropdown/{id}',[DropdownController::class,'compensationPlanByPosition']);
Route::get('/redline-by-state-id-dropdown/{id}', [DropdownController::class, 'redlineByState']);
Route::get('/overrides-list-by-state-id-dropdown/{id}', [DropdownController::class, 'overridesByState']);
Route::get('/state', [DropdownController::class, 'state']);
Route::get('/get-all-user-state', [DropdownController::class, 'getAllUserState']);

Route::get('/cost-center-dropdown', [DropdownController::class, 'costCenter']);
Route::get('/position-dropdown', [DropdownController::class, 'positions']);
Route::get('/recon-position-dropdown', [DropdownController::class, 'recoPositionsDropdown']);
Route::get('/recon-office-dropdown', [DropdownController::class, 'recoOfficerDropdown']);
Route::get('/city_by_state', [DropdownController::class, 'stateCity']);
Route::get('/AllUseLocation', [DropdownController::class, 'AllUseLocation']);
Route::get('/AllGeneralCodeList', [DropdownController::class, 'AllGeneralCodeList']);
Route::get('/getGeneralCodeListByStateID/{id}', [DropdownController::class, 'getGeneralCodeListByStateID']);
Route::get('/offer-letter-dropdown', [DropdownController::class, 'offerLetterList']);

Route::get('/get_location_office', [DropdownController::class, 'getLocationOffice']);
Route::get('/get_location_office_by_state/{id}', [DropdownController::class, 'locationsOfficeByStateID']);
Route::get('/get_all_state_with_office', [DropdownController::class, 'getAllStateWithOffice']);
Route::get('/usersByOfficeID/{id}', [DropdownController::class, 'usersByOfficeID']);

Route::get('/teams', [DropdownController::class, 'teamList']);
Route::get('/cities_by_state_id/{id}', [DropdownController::class, 'citiesByStateID']);
Route::get('/location_by_state_id/{id}', [DropdownController::class, 'locationsByStateID']);
Route::get('/positionByDepartmentID/{id}', [DropdownController::class, 'positionByDepartmentID']);
Route::get('/usersBypositionID/{id}', [DropdownController::class, 'usersBypositionID']);
// Filter
Route::get('/filter-recruiter', [OnboardingFilterController::class, 'filterRecruiter']);
Route::post('/closer-filter', [ApiMissingDataController::class, 'closerFilter']);
Route::post('/setter-filter', [ApiMissingDataController::class, 'setterFilter']);
// Route::get('/get_api_raw_missing_data',[LegacyApiRowDataController::class,'getApiRowMissingData']);

Route::post('/get_sales_data_by_super_admin', [ApiMissingDataController::class, 'disableLoginStatus']);

// Missing data update
Route::resource('/alert-center', ApiMissingDataController::class);
Route::get('/sales-detail-by-pid/{id}', [ApiMissingDataController::class, 'salesDetailByPid']);
Route::get('/missing-sales-detail-by-pid/{id}', [ApiMissingDataController::class, 'MissingSalesDetailByPid']);
Route::get('/adders_by_pid/{id}', [ApiMissingDataController::class, 'adders_by_pid']);
Route::post('/update-alert-center-data', [ApiMissingDataController::class, 'updateMissingData']);
Route::post('/add_manual_sale_data', [ApiMissingDataController::class, 'addManualSaleData'])->middleware('throttle.custom:5,1');
Route::post('/recalculate-sales', [ApiMissingDataController::class, 'recalculateSales'])->middleware('throttle.custom:5,1');
Route::get('/adjust_clawback_sale_data/{pid}', [ApiMissingDataController::class, 'adJustClawbackSaleData']);
Route::get('/alert_center_count', [ApiMissingDataController::class, 'alert_center_count']);
Route::get('/alert_center_details', [ApiMissingDataController::class, 'alert_center_details']);
Route::post('/refresh_alert_center_details', [ApiMissingDataController::class, 'refresh_alert_center_details']); // added
Route::get('/integration_missing_sales_record', [ApiMissingDataController::class, 'integrationMissingSalesRecord']);

// Setter Identify
Route::get('/get_setter_dropdown', [SetterIdentifyController::class, 'getSetterDropdown']);
Route::get('/get-setter-missing-by-pid', [SetterIdentifyController::class, 'getSetterMissingByPid']);
Route::post('/update-setter-by-pid', [SetterIdentifyController::class, 'updateSetterByPid']);
Route::get('/subroutine_process', [SetterIdentifyController::class, 'subroutine_process']);
Route::post('/digisigners', [SetterIdentifyController::class, 'digisigners']);
Route::post('/signature_requests', [SetterIdentifyController::class, 'signatureRequests']);
Route::get('/download_document', [SetterIdentifyController::class, 'downloadDocument']);

// closer Identify
Route::get('/get-closer', [CloserIdentifyController::class, 'getCloserDropdown']);
Route::get('/closer-identify-by-pid', [CloserIdentifyController::class, 'closerData']);
Route::post('/update_pid_with_closer', [CloserIdentifyController::class, 'updateSalesProcessInCloser']);

// costcenter
Route::get('/parent-child-cost-center', [DropdownController::class, 'ParentCostCenter']);
Route::get('/parent-cost-center', [DropdownController::class, 'ParentCostCenterList']);
Route::post('/get_data_from_location', [DropdownController::class, 'getDataFromLocation']);
Route::get('/check_setting_status',[DropdownController::class, 'checkSettingStatus']);

Route::post('/setterCloserListByEffectiveDate',[DropdownController::class, 'setter_closer_list_by_effective_date']);

// closed payroll
Route::get('/payRollMissingDetailByPid/{pid}',[PastAccountAlertController::class, 'payRollMissingDetailByPid']);
Route::post('/updatePayRollMissingPeriod',[PastAccountAlertController::class, 'updatePayRollMissingPeriod']);
Route::get('/getMissingDetailByPid/{pid}',[PastAccountAlertController::class, 'getMissingDetailByPid']);

Route::post('app/reset', [AppResetController::class, 'reset']);
Route::post('app/migrate', [AppResetController::class, 'migrate']);

Route::get('/install-partner', [DropdownController::class, 'installpartner']);
Route::get('/job-status', [DropdownController::class, 'jobstatus']);
Route::get('/data-source-types', [DropdownController::class, 'dataSourceTypes']);
Route::get('/product-ids', [DropdownController::class, 'productIds']);
Route::get('/product-names', [DropdownController::class, 'productNames']);
