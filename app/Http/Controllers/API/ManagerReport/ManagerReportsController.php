<?php

namespace App\Http\Controllers\API\ManagerReport;

use App\Exports\ExportReportOfficeStandard;
use App\Exports\ExportReportReconciliationStandard;
use App\Exports\ExportReportSales;
use App\Exports\ManagerReportDataExport; // need to change
use App\Http\Controllers\Controller;
use App\Models\AdditionalLocations;
use App\Models\AdditionalPayFrequency;
use App\Models\ApprovalsAndRequestLock;
use App\Models\ClawbackSettlement;
use App\Models\ClawbackSettlementLock;
use App\Models\CompanyProfile;
use App\Models\Crms;
use App\Models\GetPayrollData;
use App\Models\Locations;
use App\Models\ManagementTeam;
use App\Models\Payroll;
use App\Models\PayrollAdjustmentDetailLock;
use App\Models\PayrollAdjustmentLock;
use App\Models\PayrollDeductionLock;
use App\Models\PayrollDeductions;
use App\Models\PayrollHistory;
use App\Models\PayrollHourlySalaryLock;
use App\Models\paystubEmployee;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionReconciliations;
use App\Models\SaleMasterProcess;
use App\Models\SaleProductMaster;
use App\Models\SalesMaster;
use App\Models\Settings;
use App\Models\State;
use App\Models\User;
use App\Models\UserCommissionHistory;
use App\Models\UserCommissionLock;
use App\Models\UserOverridesLock;
use App\Models\UserReconciliationCommissionLock;
use App\Models\W2PayrollTaxDeduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request; // need to change
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ManagerReportsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    // public function officeReport(Request $request)
    // {
    //     if (!empty($request->perpage)) {
    //         $perpage = $request->perpage;
    //     } else {
    //         $perpage = 10;
    //     }
    //     $uid = [];
    //     $totalSales = [];
    //     $officeRanking = '';
    //     $totalUserByOffice = '';
    //     $totalManager = '';
    //     $totalCloser = '';
    //     $totalSetter = '';
    //     $totalJuniorSetter = '';
    //     $totalRep = '';
    //     $totalRevenue = '';
    //     $totalKW = '';
    //     $dayKw = '';
    //     $graph = '';
    //     $totalSalesData = [];
    //     $office_id = Auth()->user()->office_id;
    //     //$office_id = 1;
    //     $stateId = Auth()->user()->state_id;
    //     $managerId = Auth()->user()->manager_id;
    //     $positionId = Auth()->user()->position_id;
    //     $stateCode = $request->location;
    //     if ($request->filter == 'this_year') {
    //         $now = Carbon::now();
    //         $monthStart = $now->startOfYear();
    //         $startDate =  date('Y-m-d', strtotime($monthStart));
    //         $endDate =  date('Y-m-d', strtotime(Carbon::now()->endOfYear()));
    //     } else
    //     if ($request->filter == 'last_year') {
    //         $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
    //         $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
    //     } else
    //     if ($request->filter == 'this_quarter') {
    //         $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
    //         $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
    //     } else
    //     if ($request->filter == 'last_quarter') {
    //         // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
    //         // $endDate   =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
    //         $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
    //         $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
    //         $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
    //     } else
    //     if ($request->filter == 'this_month') {
    //         $new = Carbon::now(); //returns current day
    //         $firstDay = $new->firstOfMonth();
    //         $startDate =  date('Y-m-d', strtotime($firstDay));
    //         $end = Carbon::now();
    //         $endDate =  date('Y-m-d', strtotime($end));
    //     } else
    //     if ($request->filter == 'last_month') {
    //         $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
    //         $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
    //     } else
    //     if ($request->filter == 'this_week') {
    //         $currentDate = \Carbon\Carbon::now();
    //         $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
    //         $endDate =  date('Y-m-d', strtotime(now()));
    //     } else
    //     if ($request->filter == 'last_week') {
    //         $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
    //         $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
    //         $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
    //         $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
    //     } else if ($request->filter == 'last_12_months') {
    //         $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
    //         $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
    //     } else if ($request->filter == 'custom') {
    //         $startDate = $request->input('start_date');
    //         $endDate = $request->input('end_date');
    //     }

    //     $companyProfile = CompanyProfile::first();

    //     // total office ranking
    //     // $stateIds =  AdditionalLocations::where('user_id', auth()->user()->id)->pluck('state_id')->toArray();
    //     $stateIds =  AdditionalLocations::pluck('state_id')->toArray();
    //     array_push($stateIds, auth()->user()->state_id);
    //     $managerState = State::whereIn('id', $stateIds)->pluck('state_code')->toArray();

    //     // Office Id............................................................
    //     if (isset($request->office_id) && $request->office_id != 'all') {

    //         $officeId = $request->office_id;

    //         $office = Locations::where('type', 'Office')->get();

    //         $rank = [];

    //         foreach ($office as $key => $offices) {
    //             $userId = User::where('office_id', $offices['id'])->pluck('id');

    //             $renkOfficeSale = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $officeSalesKw = SalesMaster::whereIn('pid', $renkOfficeSale)->whereBetween('customer_signoff', [$startDate, $endDate])->sum('gross_account_value');
    //             } else {
    //                 $officeSalesKw = SalesMaster::whereIn('pid', $renkOfficeSale)->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw');
    //             }
    //             if ($officeSalesKw > 0) {
    //                 $rank[$offices->id] = $officeSalesKw;
    //             }
    //         }

    //         arsort($rank);
    //         // return $rank;
    //         $i = 1;
    //         $officeRank = 0;
    //         foreach ($rank as $key => $ranks) {
    //             if ($key == $officeId) {
    //                 $office = Locations::where('id', $officeId)->first();
    //                 $officeRank = $i;
    //             }
    //             $i = $i + 1;
    //         }

    //         if ($request->input('user_id') != '') {
    //             $userId = User::where('id', $request->input('user_id'))->pluck('id');
    //         } else {
    //             $userId = User::pluck('id');
    //         }

    //         $team = ManagementTeam::with('user')->where('office_id', $officeId)->get();
    //         $teamUser = [];
    //         $bestTeam = [];
    //         $teamName = [];
    //         $uid = [];
    //         foreach ($team as $teams) {
    //             $teamUser =  $teams->user;
    //             $teamName[] =  $teams->team_name;
    //             foreach ($teamUser as  $teamUsers) {
    //                 $uid[] = $teamUsers->id;
    //             }

    //             if ($request->input('user_id') != '') {
    //                 $userId = User::where('id', $request->input('user_id'))->pluck('id');
    //             } else {
    //                 $userId = $uid;
    //             }
    //             $totalAccount = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->sum('gross_account_value');
    //                 $totalSale = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->count();
    //             } else {
    //                 $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->sum('kw');
    //                 $totalSale = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->count();
    //             }
    //             $bestTeam[$teams->team_name] = [
    //                 'total_kw' => round($totalKW, 5),
    //                 'total_sale' => $totalSale
    //             ];
    //         }

    //         $manager = User::where(['office_id' => $officeId, 'is_manager' => '1'])->get();
    //         $managerUser = [];
    //         $bestManager = [];
    //         $managerName = [];
    //         $uid = [];
    //         foreach ($manager as $managers) {
    //             $managerUser = User::where('manager_id', $managers->id)->get();
    //             $managerName[] = $managers->name;
    //             foreach ($managerUser as $managerUsers) {
    //                 $uid[] = $managerUsers->id;
    //             }

    //             if ($request->input('user_id') != '') {
    //                 $userId = User::where('id', $request->input('user_id'))->pluck('id');
    //             } else {
    //                 $userId = $uid;
    //             }

    //             $totalAccount = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->count();
    //             } else {
    //                 $totalKWSum = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->sum('kw');
    //                 $totalKW = round($totalKWSum, 5);
    //             }

    //             $bestManager[$managers->name] = $totalKW;
    //         }
    //     } else {

    //         // return $office_id;
    //         $office = Locations::where('type', 'Office')->get();

    //         $rank = [];
    //         foreach ($office as $key => $offices) {
    //             $userId = User::where('office_id', $offices['id'])->pluck('id');
    //             $officeSales = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->count();
    //             if ($officeSales > 0) {
    //                 $rank[$office_id] = $officeSales;
    //             }
    //         }
    //         arsort($rank);
    //         $i = 1;
    //         $officeRank = 0;

    //         foreach ($rank as $key => $ranks) {
    //             if ($key == $office_id) {
    //                 $office = Locations::where('id', $office_id)->first();
    //                 $officeRank = $i;
    //             }
    //             $i = $i + 1;
    //         }
    //         if ($request->filter == 'this_year') {
    //             $now = Carbon::now();
    //             $monthStart = $now->startOfYear();
    //             $startDate =  date('Y-m-d', strtotime($monthStart));
    //             $endDate =  date('Y-m-d', strtotime(Carbon::now()->endOfYear()));
    //         } else
    //         if ($request->filter == 'last_year') {
    //             $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
    //             $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
    //         } else
    //         if ($request->filter == 'this_quarter') {
    //             $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
    //             $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
    //         } else
    //         if ($request->filter == 'last_quarter') {
    //             // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
    //             // $endDate   =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
    //             $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
    //             $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
    //             $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
    //         } else
    //         if ($request->filter == 'this_month') {
    //             $new = Carbon::now(); //returns current day
    //             $firstDay = $new->firstOfMonth();
    //             $startDate =  date('Y-m-d', strtotime($firstDay));
    //             $end = Carbon::now();
    //             $endDate =  date('Y-m-d', strtotime($end));
    //         } else
    //         if ($request->filter == 'last_month') {
    //             $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
    //             $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
    //         } else
    //         if ($request->filter == 'this_week') {
    //             $currentDate = \Carbon\Carbon::now();
    //             $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
    //             $endDate =  date('Y-m-d', strtotime(now()));
    //         } else
    //         if ($request->filter == 'last_week') {
    //             $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
    //             $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
    //             $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
    //             $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
    //         } else if ($request->filter == 'last_12_months') {
    //             $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
    //             $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
    //         } else
    //         if ($request->filter == 'custom') {
    //             $startDate = $request->input('start_date');
    //             $endDate = $request->input('end_date');
    //         }
    //         // team .................
    //         //$team = ManagementTeam::with('user')->where('office_id',$office_id)->get();
    //         $team = ManagementTeam::with('user')->get();

    //         $teamUser = [];
    //         $bestTeam = [];
    //         $teamName = [];
    //         foreach ($team as $teams) {
    //             $teamUser =  $teams->user;
    //             $teamName[] =  $teams->team_name;
    //             foreach ($teamUser as  $teamUsers) {
    //                 $uid[] = $teamUsers->id;
    //             }

    //             if ($request->input('user_id') != '') {
    //                 $userId = User::where('id', $request->input('user_id'))->pluck('id');
    //             } else {
    //                 $userId = $uid;
    //             }
    //             $totalAccount = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->sum('gross_account_value');
    //                 $totalSale = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->count();
    //             } else {
    //                 $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->sum('kw');
    //                 $totalSale = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->count();
    //             }
    //             $bestTeam[$teams->team_name] = [
    //                 'total_kw' => round($totalKW, 5),
    //                 'total_sale' => $totalSale
    //             ];
    //         }

    //         $manager = User::where(['is_manager' => '1'])->get();
    //         $managerUser = [];
    //         $bestManager = [];
    //         $managerName = [];
    //         $uid = [];
    //         foreach ($manager as $managers) {
    //             $managerUser = User::where('manager_id', $managers->id)->get();
    //             $managerName[] = $managers->name;
    //             foreach ($managerUser as $managerUsers) {
    //                 $uid[] = $managerUsers->id;
    //             }

    //             if ($request->input('user_id') != '') {
    //                 $userId = User::where('id', $request->input('user_id'))->pluck('id');
    //             } else {
    //                 $userId = $uid;
    //             }

    //             $totalAccount = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->count();
    //             } else {
    //                 $totalKWSum = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalAccount)->sum('kw');
    //                 $totalKW = round($totalKWSum, 5);
    //             }
    //             $bestManager[$managers->name] = $totalKW;
    //         }
    //     }

    //     // Office Id............................................................
    //     $j = 1;
    //     $teamBest = [];
    //     arsort($bestTeam);
    //     $bestTeamName = "";
    //     foreach ($bestTeam as $key => $bestTeams) {
    //         if (in_array($key, $teamName)) {
    //             $bestTeamName =  $key;
    //         }
    //         if ($j == 1 && $bestTeams['total_sale'] > 0) {
    //             $teamBest = [
    //                 "team_name" => $bestTeamName,
    //                 "total_kw" => $bestTeams['total_kw'],
    //                 "total_sale" => $bestTeams['total_sale'],
    //             ];
    //         }
    //         $j = $j + 1;
    //     }

    //     $j = 1;
    //     $managerBest = [];
    //     arsort($bestManager);
    //     foreach ($bestManager as $key => $bestManagers) {

    //         if ($j == 1) {
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $managerBest = [
    //                     "manager_name" => $key,
    //                     "total_sale" => $bestManagers,
    //                 ];
    //             } else {
    //                 $managerBest = [
    //                     "manager_name" => $key,
    //                     "total_kw" => $bestManagers,
    //                 ];
    //             }
    //         }
    //         $j = $j + 1;
    //     }

    //     // Office Id............................................................
    //     if (isset($request->office_id) && $request->office_id != 'all') {
    //         $officeId = $request->office_id;
    //         $totalRevenue = 0;
    //         if (isset($request->filter) && $request->filter != '') {
    //             if ($request->filter == 'this_year') {
    //                 $now = Carbon::now();
    //                 $monthStart = $now->startOfYear();
    //                 $startDate =  date('Y-m-d', strtotime($monthStart));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->endOfYear()));
    //             } else
    //             if ($request->filter == 'last_year') {
    //                 $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
    //             } else
    //             if ($request->filter == 'this_quarter') {
    //                 $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
    //             } else
    //             if ($request->filter == 'last_quarter') {
    //                 // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
    //                 // $endDate   =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
    //                 $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
    //                 $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
    //                 $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
    //             } else
    //             if ($request->filter == 'this_month') {
    //                 $new = Carbon::now(); //returns current day
    //                 $firstDay = $new->firstOfMonth();
    //                 $startDate =  date('Y-m-d', strtotime($firstDay));
    //                 $end = Carbon::now();
    //                 $endDate =  date('Y-m-d', strtotime($end));
    //             } else
    //             if ($request->filter == 'last_month') {
    //                 $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
    //             } else
    //             if ($request->filter == 'this_week') {
    //                 $currentDate = \Carbon\Carbon::now();
    //                 $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
    //                 $endDate =  date('Y-m-d', strtotime(now()));
    //             } else
    //             if ($request->filter == 'last_week') {
    //                 $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
    //                 $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
    //                 $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
    //                 $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
    //             } else if ($request->filter == 'last_12_months') {
    //                 $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
    //                 $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
    //             } else
    //             if ($request->filter == 'custom') {
    //                 $startDate = $request->input('start_date');
    //                 $endDate = $request->input('end_date');
    //             }
    //             // total office ranking

    //             $officeRanking = $officeRank;

    //             // total manager
    //             $totalManager = User::where('office_id', $officeId)->where('is_manager', 1)->count();
    //             // total closer

    //             //$totalCloser = User::where('state_id',$stateId)->where('position_id',2)->where('manager_id',$managerId)->count();
    //             $totalCloser = User::where('is_manager', '!=', 1)->where('office_id', $officeId)->where('position_id', 2)->count();
    //             // total setter
    //             //$totalSetter = User::where('state_id',$stateId)->where('position_id',3)->where('manager_id',$managerId)->count();
    //             $totalSetter = User::where('is_manager', '!=', 1)->where('office_id', $officeId)->where('position_id', 3)->count();
    //             // total junior Setter
    //             //$totalJuniorSetter = User::where('state_id',$stateId)->where('position_id',4)->where('manager_id',$managerId)->count();
    //             $totalJuniorSetter = User::where('office_id', $officeId)->where('position_id', 4)->count();

    //             $totalUserByOffice = $totalManager + $totalCloser + $totalSetter + $totalJuniorSetter;
    //             // total rep count
    //             if ($request->user_id != '') {
    //                 $userId = User::where('id', $request->user_id)->pluck('id');
    //             } else {
    //                 $userId = User::where('office_id', $officeId)->pluck('id');
    //             }

    //             $salesPid = SaleMasterProcess::whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
    //             $totalRep = SalesMaster::whereIn('pid', $salesPid)
    //                 ->whereBetween('customer_signoff', [$startDate, $endDate])
    //                 ->where('date_cancelled', null)
    //                 ->count();
    //             // total Revenue
    //             $totalRevenuevalues = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->get();
    //             foreach ($totalRevenuevalues as $totalRevenuevalue) {
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $totalRevenue += $totalRevenuevalue->gross_account_value;
    //                 } else {
    //                     $totalRevenue += $totalRevenuevalue->epc * $totalRevenuevalue->kw * 1000;
    //                 }
    //             }

    //             // total Revenue
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $totalKW = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->sum('gross_account_value');
    //                 $bestDay = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('kw', 'DESC')->sum('gross_account_value');
    //             } else {
    //                 $totalKW = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->sum('kw');
    //                 $bestDay = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('kw', 'DESC')->sum('kw');
    //             }
    //             $var = date('Y');
    //             $date = Carbon::now();
    //             $j = 1;
    //             $bestDay = [];
    //             for ($j; $j <= 365; $j++) {
    //                 $now = Carbon::now();
    //                 $monthStart = $now->startOfYear();
    //                 $newDate =  date('Y-m-d', strtotime($monthStart));
    //                 $dateStart =  date('Y-m-d', strtotime($newDate . ' + ' . $j . ' days'));
    //                 $FirstDate =  date('Y-m-d', strtotime($dateStart));
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $dayKw = SalesMaster::whereIn('pid', $salesPid)->where('customer_signoff', $FirstDate)->sum('gross_account_value');
    //                 } else {
    //                     $dayKw = SalesMaster::whereIn('pid', $salesPid)->where('customer_signoff', $FirstDate)->sum('kw');
    //                 }
    //                 if ($dayKw > 0) {
    //                     $bestDay[] = [
    //                         //'week' => 'week-'.$i.'/'.$FirstDate.'/'.$eDate,
    //                         'day' => $FirstDate,
    //                         'dayKw' => round($dayKw, 5)
    //                     ];
    //                 } else {
    //                     $bestDay[] = [
    //                         //'week' => 'week-'.$i.'/'.$FirstDate.'/'.$eDate,
    //                         'day' => null,
    //                         'dayKw' => 0
    //                     ];
    //                 }
    //             }

    //             $maxDayKw = max(array_column($bestDay, 'dayKw'));

    //             if (!empty($bestDay)) {
    //                 foreach ($bestDay as $val) {

    //                     if ($val['dayKw'] ==  $maxDayKw) {
    //                         $dayKw = [
    //                             'day' => $val['day'],
    //                             'dayKw' => $val['dayKw']
    //                         ];
    //                     }
    //                 }
    //             }

    //             $i = 1;
    //             $bestWeek = [];
    //             for ($i; $i <= 52; $i++) {
    //                 $date->setISODate($var, $i);
    //                 $startWeekDate = $date->startOfWeek();

    //                 $sDate =  date('Y-m-d', strtotime($startWeekDate));
    //                 $endWeekDate = $date->endOfWeek();
    //                 $eDate =  date('Y-m-d', strtotime($endWeekDate));
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $weekAmount = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$sDate, $eDate])->sum('gross_account_value');
    //                 } else {
    //                     $weekAmount = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$sDate, $eDate])->sum('kw');
    //                 }

    //                 if ($weekAmount > 0) {
    //                     $bestWeek[] = [
    //                         //'week' => 'week-'.$i.'/'.$sDate.'/'.$eDate,
    //                         'week' => $sDate . ',' . $eDate,
    //                         'amount' => round($weekAmount, 5)
    //                     ];
    //                 } else {
    //                     $bestWeek[] = [
    //                         'week' => '0',
    //                         'amount' => 0
    //                     ];;
    //                 }
    //             }
    //             $maxWeekAmount =  max(array_column($bestWeek, 'amount'));
    //             if (!empty($bestWeek)) {
    //                 foreach ($bestWeek as $val) {
    //                     if ($val['amount'] ==  $maxWeekAmount) {
    //                         $weekAmount = [
    //                             'week' => $val['week'],
    //                             'amount' => $val['amount']
    //                         ];
    //                     }
    //                 }
    //             }

    //             //Best Week Query changes
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $bestweekNew =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
    //                 sum(cast(gross_account_value as decimal(5,2))) As amount ,
    //                 STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
    //                 adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
    //                     ->whereBetween('customer_signoff', [$startDate, $endDate])
    //                     ->whereIn('pid', $salesPid)
    //                     ->groupBy('week')
    //                     ->orderBy('amount', 'desc')
    //                     ->first();
    //                 if (!empty($bestweekNew)) {
    //                     $bestweekNew = $bestweekNew->toArray();
    //                 }
    //             } else {
    //                 $bestweekNew =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
    //                 sum(cast(kw as decimal(5,2))) As amount ,
    //                 STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
    //                 adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
    //                     ->whereBetween('customer_signoff', [$startDate, $endDate])
    //                     ->whereIn('pid', $salesPid)
    //                     ->groupBy('week')
    //                     ->orderBy('amount', 'desc')
    //                     ->first();
    //                 if (!empty($bestweekNew)) {
    //                     $bestweekNew = $bestweekNew->toArray();
    //                 }
    //             }
    //             if (!empty($bestweekNew)) {
    //                 $weekAmount = [
    //                     'week' => $bestweekNew['startweek'] . ',' . $bestweekNew['endweek'],
    //                     'amount' => $bestweekNew['amount']
    //                 ];
    //             }

    //             $j = 1;
    //             $bestMonth = [];
    //             $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    //             for ($j; $j < 12; $j++) {
    //                 $array = [];
    //                 $sDate = Carbon::create()->month($j)->startOfMonth()->format($var . '-m-d');
    //                 $eDate = Carbon::create()->month($j)->endOfMonth()->format($var . '-m-d');
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $monthAmount = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$sDate, $eDate])->sum('gross_account_value');
    //                 } else {
    //                     $monthAmount = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$sDate, $eDate])->sum('kw');
    //                 }

    //                 if ($monthAmount > 0) {
    //                     $bestMonth[] = [
    //                         'month' => $month[$j - 1],
    //                         'amount' => round($monthAmount, 5)
    //                     ];
    //                 } else {
    //                     $bestMonth[] = [
    //                         'month' => 0,
    //                         'amount' => 0
    //                     ];
    //                 }
    //             }

    //             $maxYearAmount =  max(array_column($bestMonth, 'amount'));
    //             if (!empty($bestMonth)) {
    //                 foreach ($bestMonth as $val) {
    //                     if ($val['amount'] ==  $maxYearAmount) {
    //                         $yearAmount = [
    //                             'month' => $val['month'],
    //                             'amount' => $val['amount']
    //                         ];
    //                     }
    //                 }
    //             } else {
    //                 $yearAmount = 0;
    //             }
    //             // $result = User::where('state_id',$stateId)->where('manager_id',$managerId);

    //             $bestTeamId = User::selectRaw('team_id,count(team_id) as countId')->where('office_id', $office_id)->groupBy('team_id')->orderBy('countId', 'desc')->first();
    //             if ($request->user_id != '') {
    //                 $result = User::where('id', $request->user_id)->orderBy('first_name', 'ASC');
    //             } else {
    //                 $result = User::where('office_id', $officeId)->orderBy('first_name', 'ASC');
    //             }

    //             if ($request->has('search') && !empty($request->input('search'))) {
    //                 $result->where(function ($query) use ($request) {
    //                     return $query->where('first_name', 'LIKE', '%' . $request->input('search') . '%')
    //                         ->orWhere('last_name', 'LIKE', '%' . $request->input('search') . '%');
    //                 });
    //             }
    //             $total = $result->orderBy('first_name', 'DESC')->get();

    //             $totalSales = [];
    //             $graph = [];
    //             $topTeam = [];
    //             $topCloser = [];
    //             $topSetter = [];
    //             $totalAcc = [];
    //             $teamVal = [];

    //             foreach ($total as $key => $totals) {

    //                 $team = ManagementTeam::with('user')->where('office_id', $officeId)->where('id', $totals->team_id)->first();
    //                 // return $team;
    //                 $totalCancelled = [];
    //                 $totalPending = [];
    //                 $totalInstall = [];
    //                 $avgSystemSize = 0;
    //                 $userId = [];
    //                 $userTeamId = [];
    //                 $userTeamName = [];
    //                 $userKw = [];
    //                 $totalAccount = SaleMasterProcess::where('closer1_id', $totals->id)->orWhere('closer2_id', $totals->id)->orWhere('setter1_id', $totals->id)->orWhere('setter2_id', $totals->id)->get();
    //                 $userPid = SaleMasterProcess::where('closer1_id', $totals->id)->orWhere('closer2_id', $totals->id)->orWhere('setter1_id', $totals->id)->orWhere('setter2_id', $totals->id)->pluck('pid');
    //                 $totalUserRep = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $userPid)->count();

    //                 foreach ($totalAccount as $totalAccounts) {
    //                     $teamVal['teamId'] = isset($totals->team_id) ? $totals->team_id : null;
    //                     $teamVal['teamName'] = isset($team->team_name) ? $team->team_name : null;
    //                     $teamVal['userId'] = isset($totals->id) ? $totals->id : null;

    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $userKw = SalesMaster::where('pid', $totalAccounts->pid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->sum('gross_account_value');
    //                     } else {
    //                         $userKw = SalesMaster::where('pid', $totalAccounts->pid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw');
    //                     }

    //                     //echo"DASD";die;

    //                     $teamVal['userKw'] = $userKw;
    //                     $totalCancel = SalesMaster::where('pid', $totalAccounts->pid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                     if ($totalCancel != '') {
    //                         $totalCancelled[] = $totalCancel;
    //                     }
    //                     $pending = SalesMaster::where('pid', $totalAccounts->pid)->where('m2_date', null)->where('date_cancelled', null)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                     if ($pending != '') {
    //                         $totalPending[] = $pending;
    //                     }
    //                     $install = SalesMaster::where('pid', $totalAccounts->pid)->where('m2_date', '!=', null)->where('date_cancelled', null)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                     if ($install != '') {
    //                         $totalInstall[] = $install;
    //                     }
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $systemSize = SalesMaster::select('id', 'gross_account_value')->where('pid', $totalAccounts->pid)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                         if ($systemSize != '') {
    //                             $avgSystemSize = $avgSystemSize + $systemSize->gross_account_value;
    //                         }
    //                     } else {
    //                         $systemSize = SalesMaster::select('id', 'kw')->where('pid', $totalAccounts->pid)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                         if ($systemSize != '') {
    //                             $avgSystemSize = $avgSystemSize + $systemSize->kw;
    //                         }
    //                     }
    //                 }

    //                 $serviced = 0;
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $serviced = SalesMaster::whereIn('pid', $totalAccount->pluck('pid'))->whereNotNull('m1_date')->whereNull('date_cancelled')->whereBetween('customer_signoff', [$startDate, $endDate])->count();
    //                 }

    //                 $totalSales[] = [
    //                     'user_id' => $totals->id,
    //                     'user_name' => $totals->first_name . ' ' . $totals->last_name,
    //                     'position_id' => isset($totals->position_id) ? $totals->position_id : null,
    //                     'sub_position_id' => isset($totals->sub_position_id) ? $totals->sub_position_id : null,
    //                     'is_super_admin' => isset($totals->is_super_admin) ? $totals->is_super_admin : null,
    //                     'is_manager' => isset($totals->is_manager) ? $totals->is_manager : null,
    //                     'user_image' => $totals->image,
    //                     'team' => isset($team->team_name) ? $team->team_name : null,
    //                     'account' => $totalUserRep,
    //                     'pending' => count($totalPending),
    //                     'pending_percentage' => isset($totalUserRep) && ($totalUserRep) > 0 ? round((count($totalPending) / ($totalUserRep)) * 100, 2) : 0,
    //                     'install' => count($totalInstall),
    //                     'install_percentage' => isset($totalUserRep) && ($totalUserRep) > 0 ? round((count($totalInstall) / ($totalUserRep)) * 100, 2) : 0,
    //                     'cancelled' => count($totalCancelled),
    //                     'cancelled_percentage' => isset($totalUserRep) && ($totalUserRep) > 0 ? round((count($totalCancelled) / ($totalUserRep)) * 100, 2) : 0,
    //                     'team_lead' => isset($team->user[0]->first_name) ? $team->user[0]->first_name : null,
    //                     'team_leader_image' => isset($team->user[0]->image) ? $team->user[0]->image : null,
    //                     // 'closing_ratio' => isset($totalAccount) && count($totalAccount) > 0?round(count($totalInstall)/count($totalAccount)*100,5):0,
    //                     'closing_ratio' => isset($totalInstall) && count($totalInstall) > 0 ? round((count($totalInstall) / (count($totalInstall) + count($totalCancelled))) * 100, 2) : 0,
    //                     'avg_system_size' =>  isset($totalAccount) && count($totalAccount) > 0 ? round($avgSystemSize / count($totalAccount), 5) : 0,
    //                     'serviced' => $serviced,
    //                     'serviced_percentage' => isset($totalAccount) && count($totalAccount) > 0 ? round(($serviced / count($totalAccount)) * 100, 2) : 0,
    //                 ];

    //                 if ($totals->team_id == $bestTeamId->team_id) {
    //                     $topTeam[] = [
    //                         'team' => isset($team->team_name) ? $team->team_name : null,
    //                         'kw' =>  isset($totalAccount) && count($totalAccount) > 0 ? round($avgSystemSize, 5) : 0,
    //                     ];
    //                 }

    //                 if ($totals->position_id == 2) {
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $closerKw = SalesMaster::whereIn('pid', $userPid)->sum('gross_account_value');
    //                         $closerSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     } else {
    //                         $closerKw = SalesMaster::whereIn('pid', $userPid)->sum('kw');
    //                         $closerSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     }
    //                     $topCloser[] = [
    //                         'closer' => $totals->first_name . ' ' . $totals->last_name,
    //                         'image' => $totals->image,
    //                         'kw' =>  isset($closerKw) ? round($closerKw, 5) : 0,
    //                         'sale' => isset($closerSale) ? round($closerSale, 5) : 0
    //                     ];
    //                 } else
    //                 if ($totals->position_id == 3) {
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $setterKw = SalesMaster::whereIn('pid', $userPid)->sum('gross_account_value');
    //                         $setterSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     } else {
    //                         $setterKw = SalesMaster::whereIn('pid', $userPid)->sum('kw');
    //                         $setterSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     }
    //                     $topSetter[] = [
    //                         'setter' => $totals->first_name . ' ' . $totals->last_name,
    //                         'image' => $totals->image,
    //                         'kw' =>  isset($setterKw) ? round($setterKw, 5) : 0,
    //                         'sale' => isset($setterSale) ? round($setterSale, 5) : 0
    //                     ];
    //                 }

    //                 // account graph
    //                 if (count($totalPending) > 0 || count($totalInstall) > 0 || count($totalCancelled) > 0) {
    //                     $graph[] = [
    //                         'userName' => $totals->first_name,
    //                         'pending' => count($totalPending),
    //                         'install' => count($totalInstall),
    //                         'cancelled' => count($totalCancelled),
    //                     ];
    //                 }
    //             }

    //             if (!empty($topTeam)) {
    //                 $teamKw = 0;
    //                 foreach ($topTeam as $teamval) {
    //                     $teamName = $teamval['team'];
    //                     $teamKw = ($teamKw + $teamval['kw']);
    //                 }
    //             } else {
    //                 $teamName = null;
    //                 $teamKw = 0;
    //             }

    //             $topTeamdata = [
    //                 'team_name' => $teamName,
    //                 'total_kw' => $teamKw
    //             ];

    //             if (!empty($topCloser)) {

    //                 $maxCloserKw = max(array_column($topCloser, 'kw'));
    //                 foreach ($topCloser as $val) {
    //                     if ($val['kw'] ==  $maxCloserKw) {
    //                         $topCloser = [
    //                             'closer_name' => $val['closer'],
    //                             'closer_image' => $val['image'],
    //                             'total_kw' => $val['kw'],
    //                             'total_sale' => $val['sale']
    //                         ];
    //                     }
    //                 }
    //             } else {
    //                 $topCloser = 0;
    //             }

    //             if (!empty($topSetter)) {
    //                 $maxSetterKw =  max(array_column($topSetter, 'kw'));
    //                 foreach ($topSetter as $val) {
    //                     if ($val['kw'] ==  $maxSetterKw) {
    //                         $topSetter = [
    //                             'setter_name' => $val['setter'],
    //                             'setter_image' => $val['image'],
    //                             'total_kw' => $val['kw'],
    //                             'total_sale' => $val['sale']
    //                         ];
    //                     }
    //                 }
    //             } else {
    //                 $topSetter = 0;
    //             }
    //         }
    //     } else if (isset($request->office_id) && $request->office_id == 'all') {
    //         $totalRevenue = 0;

    //         if ($positionId != 1) {
    //             if (isset($request->filter) && $request->filter != '') {
    //                 if ($request->filter == 'this_year') {
    //                     $now = Carbon::now();
    //                     $monthStart = $now->startOfYear();
    //                     $startDate =  date('Y-m-d', strtotime($monthStart));
    //                     $endDate =  date('Y-m-d', strtotime(Carbon::now()->endOfYear()));
    //                 } else
    //                 if ($request->filter == 'last_year') {
    //                     $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
    //                     $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
    //                 } else
    //                 if ($request->filter == 'this_quarter') {
    //                     $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
    //                     $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
    //                 } else
    //                 if ($request->filter == 'last_quarter') {
    //                     // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
    //                     // $endDate   =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
    //                     $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
    //                     $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
    //                     $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
    //                 } else
    //                 if ($request->filter == 'this_month') {
    //                     $new = Carbon::now(); //returns current day
    //                     $firstDay = $new->firstOfMonth();
    //                     $startDate =  date('Y-m-d', strtotime($firstDay));
    //                     $end = Carbon::now();
    //                     $endDate =  date('Y-m-d', strtotime($end));
    //                 } else
    //                 if ($request->filter == 'last_month') {
    //                     $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
    //                     $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
    //                 } else
    //                 if ($request->filter == 'this_week') {
    //                     $currentDate = \Carbon\Carbon::now();
    //                     $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
    //                     $endDate =  date('Y-m-d', strtotime(now()));
    //                 } else
    //                 if ($request->filter == 'last_week') {
    //                     $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
    //                     $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
    //                     $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
    //                     $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
    //                 } else if ($request->filter == 'last_12_months') {
    //                     $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
    //                     $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
    //                 } else
    //                 if ($request->filter == 'custom') {
    //                     $startDate = $request->input('start_date');
    //                     $endDate = $request->input('end_date');
    //                 }

    //                 // total office ranking

    //                 $officeRanking =   $officeRank;
    //                 // total manager
    //                 //$totalManager = User::where('is_manager',1)->count();
    //                 // total closer
    //                 //$totalCloser = User::where('position_id',2)->count();
    //                 // total setter
    //                 //$totalSetter = User::where('position_id',3)->count();
    //                 // total junior Setter
    //                 //$totalJuniorSetter = User::where('position_id',4)->count();

    //                 // $totalManager = User::where('is_manager',1)->where('office_id',$office_id)->count();
    //                 // // total closer
    //                 // $totalCloser = User::where('is_manager','!=',1)->where('position_id',2)->where('office_id',$office_id)->count();
    //                 // // total setter
    //                 // $totalSetter = User::where('is_manager','!=',1)->where('position_id',3)->where('office_id',$office_id)->count();
    //                 // // total junior Setter
    //                 // $totalJuniorSetter = User::where('position_id',4)->where('office_id',$office_id)->count();

    //                 $totalManager = User::where('is_manager', 1)->count();
    //                 // total closer
    //                 $totalCloser = User::where('is_manager', '!=', 1)->where('position_id', 2)->count();
    //                 // total setter
    //                 $totalSetter = User::where('is_manager', '!=', 1)->where('position_id', 3)->count();
    //                 // total junior Setter
    //                 $totalJuniorSetter = User::where('position_id', 4)->count();

    //                 $totalUserByOffice = $totalManager + $totalCloser + $totalSetter + $totalJuniorSetter;
    //                 // total rep count
    //                 if ($request->user_id != '') {
    //                     $totalUid = User::where('id', $request->user_id)->pluck('id');
    //                 } else {
    //                     $totalUid = User::pluck('id');
    //                 }

    //                 $totalPid = SaleMasterProcess::whereIn('closer1_id', $totalUid)->orWhereIn('closer2_id', $totalUid)->orWhereIn('setter1_id', $totalUid)->orWhereIn('setter2_id', $totalUid)->pluck('pid');

    //                 $totalRep = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
    //                     ->whereIn('pid', $totalPid)
    //                     ->where('date_cancelled', null)
    //                     ->count();
    //                 // total Revenue
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $totalRevenuevalues = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->where('date_cancelled', null)->get();
    //                     foreach ($totalRevenuevalues as $totalRevenuevalue) {
    //                         $totalRevenue += $totalRevenuevalue->gross_account_value;
    //                     }
    //                 } else {
    //                     $totalRevenuevalues = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->where('date_cancelled', null)->get();
    //                     foreach ($totalRevenuevalues as $totalRevenuevalue) {
    //                         $totalRevenue += $totalRevenuevalue->epc * $totalRevenuevalue->kw * 1000;
    //                     }
    //                 }

    //                 // total Revenue
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->where('date_cancelled', null)->whereNotNull('m1_date')->count();
    //                     $bestDay = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->orderBy('gross_account_value', 'DESC')->first();
    //                 } else {
    //                     $totalKWSum = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->where('date_cancelled', null)->sum('kw');
    //                     $totalKW = round((float)($totalKWSum), 5);
    //                     $bestDay = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->orderBy('kw', 'DESC')->first();
    //                 }

    //                 $var = date('Y');
    //                 $date = Carbon::now();
    //                 $j = 1;
    //                 // $bestDay = [];
    //                 //     for($j; $j<=365; $j++){
    //                 //         $now = Carbon::now(); //2025-03-13 02:08:38
    //                 //         $monthStart = $now->startOfYear();//2025-01-01 00:00:00
    //                 //         $newDate =  date('Y-m-d', strtotime($monthStart)); //2025-01-01
    //                 //         $dateStart =  date('Y-m-d', strtotime($newDate. ' + '.$j.' days'));
    //                 //         $FirstDate =  date('Y-m-d', strtotime($dateStart));
    //                 //         if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 //             $dayKw = SalesMaster::whereIn('pid',$totalPid)->where('customer_signoff',$FirstDate)->count();
    //                 //         } else {
    //                 //             $dayKw = SalesMaster::whereIn('pid',$totalPid)->where('customer_signoff',$FirstDate)->sum('kw');
    //                 //         }

    //                 //         if($dayKw>0)
    //                 //         {
    //                 //             $bestDay[] =[
    //                 //                 //'week' => 'week-'.$i.'/'.$FirstDate.'/'.$eDate,
    //                 //                 'day' => $FirstDate,
    //                 //                 'dayKw' => round($dayKw,5)
    //                 //             ];
    //                 //         }else{
    //                 //             $bestDay[] =[
    //                 //                 //'week' => 'week-'.$i.'/'.$FirstDate.'/'.$eDate,
    //                 //                 'day' => null,
    //                 //                 'dayKw' => 0
    //                 //             ];

    //                 //         }

    //                 //     }

    //                 $bestDay = [];
    //                 $now = Carbon::now();
    //                 $monthStart = $now->startOfYear(); // Store once

    //                 // Fetch all necessary sales data at once
    //                 $salesData = SalesMaster::whereIn('pid', $totalPid)
    //                     ->whereBetween('customer_signoff', [$monthStart, $monthStart->copy()->addDays(365)])
    //                     ->get();

    //                 // Group sales data by date
    //                 $groupedSales = [];
    //                 foreach ($salesData as $sale) {
    //                     $date = $sale->customer_signoff;
    //                     if (!isset($groupedSales[$date])) {
    //                         $groupedSales[$date] = 0;
    //                     }
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $groupedSales[$date] += 1; // Count
    //                     } else {
    //                         $groupedSales[$date] += $sale->kw; // Sum
    //                     }
    //                 }

    //                 // Loop with optimized logic
    //                 for ($j = 0; $j <= 365; $j++) {
    //                     $dateStart = $monthStart->copy()->addDays($j)->format('Y-m-d');
    //                     $dayKw = $groupedSales[$dateStart] ?? 0;

    //                     $bestDay[] = [
    //                         'day' => $dayKw > 0 ? $dateStart : null,
    //                         'dayKw' => round($dayKw, 5)
    //                     ];
    //                 }

    //                 $maxDayKw =  max(array_column($bestDay, 'dayKw'));
    //                 if (!empty($bestDay)) {
    //                     foreach ($bestDay as $val) {

    //                         if ($val['dayKw'] ==  $maxDayKw) {
    //                             $dayKw = [
    //                                 'day' => $val['day'],
    //                                 'dayKw' => $val['dayKw']
    //                             ];
    //                         }
    //                     }
    //                 }

    //                 $dateWeek = strtotime($startDate);
    //                 $startWeek =  date("W", $dateWeek);
    //                 $eDate = strtotime($endDate);
    //                 $endWeek = date("W", $eDate);
    //                 $findLastYear =  date('Y', strtotime($startDate));
    //                 $date = Carbon::now();
    //                 $startCurrent = $date->startOfQuarter();
    //                 $startPrevious = $startCurrent->subQuarter();

    //                 $bestWeek = [];
    //                 $var =  date('Y', strtotime($startDate));

    //                 // if($endWeek<$startWeek)
    //                 // {
    //                 //     if($startWeek==52)
    //                 //     {
    //                 //         $i = 1;
    //                 //     }
    //                 //     else{
    //                 //         $i = $startWeek;
    //                 //     }
    //                 //     $sDate =  date('Y-m-d', strtotime($startDate));
    //                 //      $endWeekDate =$date->endOfWeek();
    //                 //     for($i; $i<=52; $i++);
    //                 // }
    //                 // else{
    //                 //     if($startWeek==52)
    //                 //     {
    //                 //         $i = 1;
    //                 //     }
    //                 //     else{
    //                 //         $i = $startWeek;
    //                 //     }
    //                 //     for($i; $i<=$endWeek; $i++);
    //                 //     return 'Ram';
    //                 // }
    //                 for ($i; $i <= $endWeek; $i++) {
    //                     $date->setISODate($var, $i);
    //                     $startWeekDate = $date->startOfWeek();
    //                     $sDate =  date('Y-m-d', strtotime($startWeekDate));
    //                     $endWeekDate = $date->endOfWeek();
    //                     $eDate =  date('Y-m-d', strtotime($endWeekDate));
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $weekAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('customer_state', $managerState)->sum('gross_account_value');
    //                     } else {
    //                         $weekAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('customer_state', $managerState)->sum('kw');
    //                     }
    //                     if ($weekAmount > 0) {
    //                         $bestWeek[] = [
    //                             //'week' => 'week-'.$i.'/'.$sDate.'/'.$eDate,
    //                             'week' => $sDate . ',' . $eDate,
    //                             'amount' => round($weekAmount, 5)
    //                         ];
    //                     } else {
    //                         $bestWeek[] = [
    //                             'week' => '0',
    //                             'amount' => 0
    //                         ];
    //                     }
    //                 }

    //                 // return $bestWeek;

    //                 //   $maxWeekAmount =  max(array_column($bestWeek, 'amount'));
    //                 if (!empty($bestWeek)) {
    //                     $maxWeekAmount =  max(array_column($bestWeek, 'amount'));
    //                     foreach ($bestWeek as $val) {
    //                         if ($val['amount'] ==  $maxWeekAmount) {
    //                             $weekAmount = [
    //                                 'week' => $val['week'],
    //                                 'amount' => $val['amount']
    //                             ];
    //                         }
    //                     }
    //                 }

    //                 //Best Week Query changes
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $bestweekNew =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
    //                         count(id) As amount ,
    //                         STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
    //                         adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
    //                         ->whereBetween('customer_signoff', [$startDate, $endDate])
    //                         ->whereIn('pid', $totalPid)
    //                         ->groupBy('week')
    //                         ->orderBy('amount', 'desc')
    //                         ->first();
    //                     if (!empty($bestweekNew)) {
    //                         $bestweekNew = $bestweekNew->toArray();
    //                     }
    //                 } else {
    //                     $bestweekNew =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
    //                         sum(cast(kw as decimal(5,2))) As amount ,
    //                         STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
    //                         adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
    //                         ->whereBetween('customer_signoff', [$startDate, $endDate])
    //                         ->whereIn('pid', $totalPid)
    //                         ->groupBy('week')
    //                         ->orderBy('amount', 'desc')
    //                         ->first();
    //                     if (!empty($bestweekNew)) {
    //                         $bestweekNew = $bestweekNew->toArray();
    //                     }
    //                 }
    //                 if (!empty($bestweekNew)) {
    //                     $weekAmount = [
    //                         'week' => $bestweekNew['startweek'] . ',' . $bestweekNew['endweek'],
    //                         'amount' => $bestweekNew['amount']
    //                     ];
    //                 }

    //                 $j = 1;
    //                 $bestMonth = [];
    //                 $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    //                 for ($j; $j < 12; $j++) {
    //                     $array = [];
    //                     $sDate = Carbon::create()->month($j)->startOfMonth()->format($var . '-m-d');
    //                     $eDate = Carbon::create()->month($j)->endOfMonth()->format($var . '-m-d');
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $monthAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $totalPid)->count();
    //                     } else {
    //                         $monthAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $totalPid)->sum('kw');
    //                     }
    //                     if ($monthAmount > 0) {
    //                         $bestMonth[] = [
    //                             'month' => $month[$j - 1],
    //                             'amount' => round($monthAmount, 5)
    //                         ];
    //                     } else {
    //                         $bestMonth[] = [
    //                             'month' => 0,
    //                             'amount' => 0
    //                         ];
    //                     }
    //                 }
    //                 $maxYearAmount =  max(array_column($bestMonth, 'amount'));
    //                 if (!empty($bestMonth)) {
    //                     foreach ($bestMonth as $val) {
    //                         if ($val['amount'] ==  $maxYearAmount) {
    //                             $yearAmount = [
    //                                 'month' => $val['month'],
    //                                 'amount' => $val['amount']
    //                             ];
    //                         }
    //                     }
    //                 } else {
    //                     $yearAmount = 0;
    //                 }
    //                 //$bestTeamId = User::selectRaw('team_id,count(team_id) as countId')->where('state_id',$stateId)->groupBy('team_id')->orderBy('countId','desc')->first();

    //                 // $bestTeamId = User::selectRaw('team_id,count(team_id) as countId')->groupBy('team_id')->orderBy('countId','desc')->first();

    //                 // if($request->user_id != '')
    //                 // {
    //                 //     $result = User::orderBy('users.id','desc')->where('users.id',$request->user_id);
    //                 // }else
    //                 // {
    //                 //     $result = User::orderBy('users.id','desc');
    //                 // }

    //                 // if ($request->has('search') && !empty($request->input('search'))) {
    //                 //     $result->where(function ($query) use ($request) {
    //                 //         return $query->where('first_name', 'LIKE', '%' . $request->input('search') . '%')
    //                 //             ->orWhere('last_name', 'LIKE', '%' . $request->input('search') . '%') ;
    //                 //     });
    //                 // }
    //                 // // $result->with('SaleMasterProcess',function ($query){
    //                 // //     $query->orWhere('closer2_id', $this->getKey())
    //                 // //     ->orWhere('setter1_id', $this->getKey())
    //                 // //     ->orWhere('setter2_id', $this->getKey());
    //                 // // });
    //                 // $result->with('teamsDetail');
    //                 // $total = $result->get();
    //                 // // print_r($total->toArray()); die;
    //                 // // return $total;
    //                 // $totalSales = [];
    //                 // $graph=[];
    //                 // $topTeam=[];
    //                 // $topCloser=[];
    //                 // $topSetter=[];
    //                 // $totalAcc=[];
    //                 // $teamVal=[];

    //                 // // echo $startDate." ".$endDate;
    //                 // // die;
    //                 // // dd(1);
    //                 // // for ($i=0; $i<$total; $i++) {

    //                 //     $total->transform(function ($result) use ($startDate,$endDate, $bestTeamId , &$totalSales, &$graph, &$topTeam, &$topCloser, &$topSetter, &$teamVal, $companyProfile)
    //                 //     {
    //                 //         $totalAccountQuery = SaleMasterProcess::where('closer1_id',$result->id)->orWhere('closer2_id',$result->id)->orWhere('setter1_id',$result->id)->orWhere('setter2_id',$result->id);
    //                 //         $totalAccountData = $totalAccountQuery->get();
    //                 //         $totalAccount =  $totalAccountQuery->count();
    //                 //         $userPid = $totalAccountQuery->pluck('pid');
    //                 //         // $SalesMasterQuery = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate]);
    //                 //         $totalUserRep = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->count();
    //                 //         $totalCancelled= SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->whereNotNull('date_cancelled')->count();
    //                 //         if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 //             $avgSystemSize = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->sum('gross_account_value');
    //                 //             $avgTotalSale = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->count();
    //                 //             $totalInstall = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->whereNotNull('m1_date')->count();
    //                 //             $totalPending = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->whereNull('m1_date')->whereNull('date_cancelled')->count();
    //                 //         } else {
    //                 //             $avgSystemSize = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->sum('kw');
    //                 //             $avgTotalSale = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->count();
    //                 //             $totalInstall = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->whereNotNull('m2_date')->count();
    //                 //             $totalPending = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->whereNull('m2_date')->whereNull('date_cancelled')->count();
    //                 //         }

    //                 //         if ($totalAccountData->isNotEmpty()) {
    //                 //         foreach ($totalAccountData as $key =>  $accountVal) {
    //                 //             // echo $key . '<br>';
    //                 //             $teamVal['teamId']=isset($result->team_id)?$result->team_id:null;
    //                 //             $teamVal['teamName']=isset($result->teamsDetail->team_name)?$result->teamsDetail->team_name:null;
    //                 //             $teamVal['userId']=isset($result->id)?$result->id:null;
    //                 //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 //                 $userKw = SalesMaster::where('pid',$accountVal->id)->whereNotNull('date_cancelled')->whereBetween('customer_signoff',[$startDate,$endDate])->sum('gross_account_value');
    //                 //             } else {
    //                 //                 $userKw = SalesMaster::where('pid',$accountVal->id)->whereNotNull('date_cancelled')->whereBetween('customer_signoff',[$startDate,$endDate])->sum('kw');
    //                 //             }
    //                 //             $teamVal['userKw'] = $userKw;
    //                 //         }
    //                 //     }

    //                 //         // $result->SaleMasterProcess->transform(function ($saleMasterProcess) use ($startDate,$endDate, &$avgSystemSize){
    //                 //         // });

    //                 //         $serviced = 0;
    //                 //         if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 //             // $serviced = SalesMaster::whereIn('pid', $totalAccount->pluck('pid'))->whereNotNull('m1_date')->whereNull('date_cancelled')->whereBetween('customer_signoff', [$startDate, $endDate])->count();
    //                 //             $serviced = SalesMaster::whereIn('pid', $totalAccountQuery->pluck('pid'))->whereNotNull('m1_date')->whereNull('date_cancelled')->whereBetween('customer_signoff', [$startDate, $endDate])->count();
    //                 //         }

    //                 //         $totalSales[] = [
    //                 //             'user_id' => $result->id,
    //                 //             'user_name' => $result->first_name.' '.$result->last_name,
    //                 //             'position_id' => isset($result->position_id) ? $result->position_id : null,
    //                 //             'sub_position_id' => isset($result->sub_position_id) ? $result->sub_position_id : null,
    //                 //             'is_super_admin' => isset($result->is_super_admin) ? $result->is_super_admin : null,
    //                 //             'is_manager' => isset($result->is_manager) ? $result->is_manager : null,
    //                 //             'user_image' => $result->image,
    //                 //             'team' => isset($result->teamsDetail->team_name)?$result->teamsDetail->team_name:null,
    //                 //             'account' => isset($totalUserRep)?$totalUserRep:0,
    //                 //             'pending' => $totalPending,
    //                 //             'pending_percentage' => isset($totalUserRep) && $totalUserRep > 0?round(($totalPending / $totalUserRep)*100,2) : 0,
    //                 //             'install' => $totalInstall,
    //                 //             'install_percentage' => isset($totalUserRep) && $totalUserRep > 0?round(($totalInstall/$totalUserRep)*100,2) :0,
    //                 //             'cancelled' => $totalCancelled,
    //                 //             'cancelled_percentage' => isset($totalUserRep) && $totalUserRep > 0?round(($totalCancelled /$totalUserRep)*100,2) :0,
    //                 //             'team_lead' => isset($result->teamsDetail->user[0]->first_name)?$result->teamsDetail->user[0]->first_name:null,
    //                 //             'team_leader_image' => isset($result->teamsDetail->user[0]->image)?$result->teamsDetail->user[0]->image:null,
    //                 //            // 'closing_ratio' => isset($totalAccount) && $totalAccount > 0?round($totalInstall/$totalAccount*100,5):0,
    //                 //             'closing_ratio' => isset($totalInstall) && $totalInstall > 0?round(($totalInstall/($totalInstall+$totalCancelled))*100,2):0,
    //                 //             'avg_system_size' =>  isset($totalAccount) && $totalAccount > 0? round($avgSystemSize/$totalAccount,5):0,
    //                 //             'aa' =>  $avgSystemSize,
    //                 //             'serviced' => $serviced,
    //                 //             // 'serviced_percentage' => isset($totalAccount) && count($totalAccount) > 0 ? round(($serviced / count($totalAccount)) * 100, 2) : 0,
    //                 //             'serviced_percentage' => isset($totalAccount) && ($totalAccount) > 0 ? round(($serviced / ($totalAccount)) * 100, 2) : 0,
    //                 //         ];

    //                 //         if($result->team_id == $bestTeamId->team_id){
    //                 //             $topTeam[] = [
    //                 //                 'team' => isset($result->teamsDetail->team_name)?$result->teamsDetail->team_name:null,
    //                 //                 'kw' =>  isset($totalAccount) && $totalAccount > 0? round($avgSystemSize,5):0,
    //                 //             ];
    //                 //         }

    //                 //         if($result->position_id==2){
    //                 //             $topCloser[] = [
    //                 //                 'closer' => $result->first_name.' '.$result->last_name,
    //                 //                 'image' => $result->image,
    //                 //                 'kw' =>  isset($totalAccount) && $totalAccount > 0? round($avgSystemSize,5):0,
    //                 //                 'sale' =>  isset($totalAccount) && $totalAccount > 0? $avgTotalSale:0
    //                 //             ];
    //                 //         }
    //                 //         elseif($result->position_id==3){
    //                 //             $topSetter[] = [
    //                 //                 'setter' => $result->first_name.' '.$result->last_name,
    //                 //                 'image' => $result->image,
    //                 //                 'kw' =>  round($avgSystemSize,5),
    //                 //                 'sale' => $avgTotalSale
    //                 //             ];
    //                 //         }
    //                 //         // account graph

    //                 //         if($totalPending>0 || $totalInstall>0 || $totalCancelled>0){
    //                 //             $graph[] = [
    //                 //                 'userName' => $result->first_name,
    //                 //                 'pending' => $totalPending,
    //                 //                 'install' => $totalInstall,
    //                 //                 'cancelled' => $totalCancelled,
    //                 //             ];
    //                 //         }

    //                 //         // return $result;
    //                 //     });

    //                 /////--- New Code

    //                 $bestTeamId = User::groupBy('team_id')
    //                     ->selectRaw('team_id, COUNT(team_id) as countId')
    //                     ->orderByDesc('countId')
    //                     ->pluck('team_id')
    //                     ->first();

    //                 $result = User::orderByDesc('users.id')->with(['teamsDetail', 'teamsDetail.user']);

    //                 if (!empty($request->user_id)) {
    //                     $result->where('users.id', $request->user_id);
    //                 }

    //                 if ($request->has('search') && !empty($request->input('search'))) {
    //                     $searchTerm = '%' . $request->input('search') . '%';
    //                     $result->where(function ($query) use ($searchTerm) {
    //                         $query->where('first_name', 'LIKE', $searchTerm)
    //                             ->orWhere('last_name', 'LIKE', $searchTerm);
    //                     });
    //                 }

    //                 $result->with('teamsDetail');
    //                 $total = $result->get();

    //                 $totalSales = [];
    //                 $graph = [];
    //                 $topTeam = [];
    //                 $topCloser = [];
    //                 $topSetter = [];
    //                 $totalAcc = [];
    //                 $teamVal = [];

    //                 $total->transform(function ($result) use ($startDate, $endDate, $bestTeamId, &$totalSales, &$graph, &$topTeam, &$topCloser, &$topSetter, &$teamVal, $companyProfile) {
    //                     $totalAccountQuery = SaleMasterProcess::whereIn('closer1_id', [$result->id])
    //                         ->orWhereIn('closer2_id', [$result->id])
    //                         ->orWhereIn('setter1_id', [$result->id])
    //                         ->orWhereIn('setter2_id', [$result->id]);
    //                     $userPid = $totalAccountQuery->pluck('pid');

    //                     $totalAccountData = $totalAccountQuery->get();
    //                     $totalAccount =  $totalAccountQuery->count();
    //                     $userPid = $totalAccountQuery->pluck('pid');

    //                     $salesMasterQuery = SalesMaster::whereIn('pid', $userPid)
    //                         ->whereBetween('customer_signoff', [$startDate, $endDate]);

    //                     $totalUserRep = (clone $salesMasterQuery)->count();
    //                     $totalCancelled = (clone $salesMasterQuery)->whereNotNull('date_cancelled')->count();

    //                     // $totalUserRep = SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->count();
    //                     // $totalCancelled= SalesMaster::whereIn('pid',$userPid)->whereBetween('customer_signoff',[$startDate,$endDate])->whereNotNull('date_cancelled')->count();

    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $avgSystemSize = (clone $salesMasterQuery)->sum('gross_account_value');
    //                         $totalInstall = (clone $salesMasterQuery)->whereNotNull('m1_date')->count();
    //                         $totalPending = (clone $salesMasterQuery)->whereNull('m1_date')->whereNull('date_cancelled')->count();
    //                         $avgTotalSale = (clone $salesMasterQuery)->count();
    //                     } else {
    //                         $avgSystemSize = (clone $salesMasterQuery)->sum('kw');
    //                         $totalInstall = (clone $salesMasterQuery)->whereNotNull('m2_date')->count();
    //                         $totalPending = (clone $salesMasterQuery)->whereNull('m2_date')->whereNull('date_cancelled')->count();
    //                         $avgTotalSale = (clone $salesMasterQuery)->count();
    //                     }

    //                     if ($totalAccountData->isNotEmpty()) {
    //                         foreach ($totalAccountData as $key =>  $accountVal) {

    //                             $teamVal['teamId'] = isset($result->team_id) ? $result->team_id : null;
    //                             $teamVal['teamName'] = isset($result->teamsDetail->team_name) ? $result->teamsDetail->team_name : null;
    //                             $teamVal['userId'] = isset($result->id) ? $result->id : null;
    //                             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                                 $userKw = SalesMaster::where('pid', $accountVal->id)->whereNotNull('date_cancelled')->whereBetween('customer_signoff', [$startDate, $endDate])->sum('gross_account_value');
    //                             } else {
    //                                 $userKw = SalesMaster::where('pid', $accountVal->id)->whereNotNull('date_cancelled')->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw');
    //                             }
    //                             $teamVal['userKw'] = $userKw;
    //                         }
    //                     }

    //                     $serviced = 0;
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $serviced = SalesMaster::whereIn('pid', $totalAccountQuery->pluck('pid'))->whereNotNull('m1_date')->whereNull('date_cancelled')->whereBetween('customer_signoff', [$startDate, $endDate])->count();
    //                     }

    //                     $totalSales[] = [
    //                         'user_id' => $result->id,
    //                         'user_name' => $result->first_name . ' ' . $result->last_name,
    //                         'position_id' => isset($result->position_id) ? $result->position_id : null,
    //                         'sub_position_id' => isset($result->sub_position_id) ? $result->sub_position_id : null,
    //                         'is_super_admin' => isset($result->is_super_admin) ? $result->is_super_admin : null,
    //                         'is_manager' => isset($result->is_manager) ? $result->is_manager : null,
    //                         'user_image' => $result->image,
    //                         'team' => isset($result->teamsDetail->team_name) ? $result->teamsDetail->team_name : null,
    //                         'account' => isset($totalUserRep) ? $totalUserRep : 0,
    //                         'pending' => $totalPending,
    //                         'pending_percentage' => isset($totalUserRep) && $totalUserRep > 0 ? round(($totalPending / $totalUserRep) * 100, 2) : 0,
    //                         'install' => $totalInstall,
    //                         'install_percentage' => isset($totalUserRep) && $totalUserRep > 0 ? round(($totalInstall / $totalUserRep) * 100, 2) : 0,
    //                         'cancelled' => $totalCancelled,
    //                         'cancelled_percentage' => isset($totalUserRep) && $totalUserRep > 0 ? round(($totalCancelled / $totalUserRep) * 100, 2) : 0,
    //                         'team_lead' => isset($result->teamsDetail->user[0]->first_name) ? $result->teamsDetail->user[0]->first_name : null,
    //                         'team_leader_image' => isset($result->teamsDetail->user[0]->image) ? $result->teamsDetail->user[0]->image : null,

    //                         'closing_ratio' => isset($totalInstall) && $totalInstall > 0 ? round(($totalInstall / ($totalInstall + $totalCancelled)) * 100, 2) : 0,
    //                         'avg_system_size' =>  isset($totalAccount) && $totalAccount > 0 ? round($avgSystemSize / $totalAccount, 5) : 0,
    //                         'aa' =>  $avgSystemSize,
    //                         'serviced' => $serviced,
    //                         'serviced_percentage' => isset($totalAccount) && ($totalAccount) > 0 ? round(($serviced / ($totalAccount)) * 100, 2) : 0,
    //                     ];

    //                     if ($result->team_id == $bestTeamId) {
    //                         $topTeam[] = [
    //                             'team' => isset($result->teamsDetail->team_name) ? $result->teamsDetail->team_name : null,
    //                             'kw' =>  isset($totalAccount) && $totalAccount > 0 ? round($avgSystemSize, 5) : 0,
    //                         ];
    //                     }

    //                     if ($result->position_id == 2) {
    //                         $topCloser[] = [
    //                             'closer' => $result->first_name . ' ' . $result->last_name,
    //                             'image' => $result->image,
    //                             'kw' =>  isset($totalAccount) && $totalAccount > 0 ? round($avgSystemSize, 5) : 0,
    //                             'sale' =>  isset($totalAccount) && $totalAccount > 0 ? $avgTotalSale : 0
    //                         ];
    //                     } elseif ($result->position_id == 3) {
    //                         $topSetter[] = [
    //                             'setter' => $result->first_name . ' ' . $result->last_name,
    //                             'image' => $result->image,
    //                             'kw' =>  round($avgSystemSize, 5),
    //                             'sale' => $avgTotalSale
    //                         ];
    //                     }
    //                     // account graph

    //                     if ($totalPending > 0 || $totalInstall > 0 || $totalCancelled > 0) {
    //                         $graph[] = [
    //                             'userName' => $result->first_name,
    //                             'pending' => $totalPending,
    //                             'install' => $totalInstall,
    //                             'cancelled' => $totalCancelled,
    //                         ];
    //                     }
    //                 });

    //                 // }
    //                 // die;
    //                 // foreach($total as $key => $totals)
    //                 // {
    //                 //     //$team = ManagementTeam::with('user')->where('office_id',$office_id)->where('id',$totals->team_id)->first();
    //                 //     $team = ManagementTeam::with('user')->where('id',$totals->team_id)->first();
    //                 //     // return $team;
    //                 //     $totalCancelled=[];
    //                 //     $totalPending=[];
    //                 //     $totalInstall=[];
    //                 //     $avgSystemSize=0;
    //                 //     $userId=[];
    //                 //     $userTeamId=[];
    //                 //     $userTeamName=[];
    //                 //     $userKw=[];

    //                 //     $totalAccount = SaleMasterProcess::where('closer1_id',$totals->id)->orWhere('closer2_id',$totals->id)->orWhere('setter1_id',$totals->id)->orWhere('setter2_id',$totals->id)->get();
    //                 //     $userPid = SaleMasterProcess::where('closer1_id',$totals->id)->orWhere('closer2_id',$totals->id)->orWhere('setter1_id',$totals->id)->orWhere('setter2_id',$totals->id)->pluck('pid');
    //                 //     $totalUserRep = SalesMaster::whereBetween('customer_signoff',[$startDate,$endDate])->whereIn('pid',$userPid)->count();
    //                 //     foreach($totalAccount as $totalAccounts)
    //                 //     {
    //                 //         $teamVal['teamId']=isset($totals->team_id)?$totals->team_id:null;
    //                 //         $teamVal['teamName']=isset($team->team_name)?$team->team_name:null;
    //                 //         $teamVal['userId']=isset($totals->id)?$totals->id:null;

    //                 //         $userKw= SalesMaster::where('pid',$totalAccounts->pid)->where('date_cancelled','!=',null)->whereBetween('customer_signoff',[$startDate,$endDate])->sum('kw');
    //                 //         //echo"DASD";die;
    //                 //         $teamVal['userKw'] = $userKw;
    //                 //         $totalCancel= SalesMaster::where('pid',$totalAccounts->pid)->where('date_cancelled','!=',null)->whereBetween('customer_signoff',[$startDate,$endDate])->first();
    //                 //         if($totalCancel!='')
    //                 //         {
    //                 //             $totalCancelled[]=$totalCancel;
    //                 //         }

    //                 //         $pending = SalesMaster::where('pid',$totalAccounts->pid)->where('m2_date',null)->whereBetween('customer_signoff',[$startDate,$endDate])->first();
    //                 //         if($pending!='')
    //                 //         {
    //                 //             $totalPending[]=$pending;
    //                 //         }
    //                 //         $install = SalesMaster::where('pid',$totalAccounts->pid)->where('m2_date','!=',null)->whereBetween('customer_signoff',[$startDate,$endDate])->first();
    //                 //         if($install!='')
    //                 //         {
    //                 //             $totalInstall[]=$install;
    //                 //         }
    //                 //         $systemSize = SalesMaster::select('id','kw')->where('pid',$totalAccounts->pid)->whereBetween('customer_signoff',[$startDate,$endDate])->first();
    //                 //     // return $systemSize;
    //                 //         if($systemSize!='')
    //                 //         {
    //                 //         $avgSystemSize=$avgSystemSize+$systemSize->kw;
    //                 //         }
    //                 //     }

    //                 //     $totalSales[] = [
    //                 //         'user_id' => $totals->id,
    //                 //         'user_name' => $totals->first_name.' '.$totals->last_name,
    //                 //         'user_image' => $totals->image,
    //                 //         'team' => isset($team->team_name)?$team->team_name:null,
    //                 //         'account' => isset($totalUserRep)?$totalUserRep:0,
    //                 //         'pending' => count($totalPending),
    //                 //         'pending_percentage' => isset($totalAccount) && count($totalAccount) > 0?(count($totalPending) / count($totalAccount))*100 : 0,
    //                 //         'install' => count($totalInstall),
    //                 //         'install_percentage' => isset($totalAccount) && count($totalAccount) > 0?(count($totalInstall)/count($totalAccount))*100 :0,
    //                 //         'cancelled' => count($totalCancelled),
    //                 //         'cancelled_percentage' => isset($totalAccount) && count($totalAccount) > 0?(count($totalCancelled)/count($totalAccount))*100 :0,
    //                 //         'team_lead' => isset($team->user[0]->first_name)?$team->user[0]->first_name:null,
    //                 //         'team_leader_image' => isset($team->user[0]->image)?$team->user[0]->image:null,
    //                 //         'closing_ratio' => isset($totalAccount) && count($totalAccount) > 0?round(count($totalInstall)/count($totalAccount)*100,5):0,
    //                 //         'avg_system_size' =>  isset($totalAccount) && count($totalAccount) > 0? round($avgSystemSize/count($totalAccount),5):0,
    //                 //         'aa' =>  $avgSystemSize,
    //                 //     ];

    //                 //     if($totals->team_id == $bestTeamId->team_id)
    //                 //     {
    //                 //         $topTeam[] = [
    //                 //             'team' => isset($team->team_name)?$team->team_name:null,
    //                 //             'kw' =>  isset($totalAccount) && count($totalAccount) > 0? round($avgSystemSize,5):0,
    //                 //         ];
    //                 //     }

    //                 //     if($totals->position_id==2)
    //                 //     {
    //                 //         $topCloser[] = [
    //                 //             'closer' => $totals->first_name,
    //                 //             'image' => $totals->image,
    //                 //             'kw' =>  isset($totalAccount) && count($totalAccount) > 0? round($avgSystemSize,5):0,
    //                 //         ];
    //                 //     }else
    //                 //     if($totals->position_id==3)
    //                 //     {
    //                 //         $topSetter[] = [
    //                 //             'setter' => $totals->first_name,
    //                 //             'image' => $totals->image,
    //                 //             'kw' =>  isset($totalAccount) && count($totalAccount) > 0? round($avgSystemSize,5):0,
    //                 //         ];
    //                 //     }
    //                 //     // account graph

    //                 //     if(count($totalPending)>0 || count($totalInstall)>0 || count($totalCancelled)>0)
    //                 //     {

    //                 //         $graph[] = [
    //                 //             'userName' => $totals->first_name,
    //                 //             'pending' => count($totalPending),
    //                 //             'install' => count($totalInstall),
    //                 //             'cancelled' => count($totalCancelled),
    //                 //         ];
    //                 //     }
    //                 // }
    //                 // return $topCloser;
    //                 if (!empty($topTeam)) {
    //                     $teamKw = 0;
    //                     foreach ($topTeam as $teamval) {
    //                         $teamName = $teamval['team'];
    //                         $teamKw = ($teamKw + $teamval['kw']);
    //                     }
    //                 } else {
    //                     $teamName = null;
    //                     $teamKw = 0;
    //                 }

    //                 $topTeamdata = [
    //                     'team_name' => $teamName,
    //                     'total_kw' => $teamKw
    //                 ];

    //                 // print_r($topCloser); die;

    //                 if (!empty($topCloser)) {
    //                     $maxCloserKw = max(array_column($topCloser, 'kw'));
    //                     foreach ($topCloser as $val) {
    //                         if ($val['kw'] ==  $maxCloserKw) {
    //                             $topCloser = [
    //                                 'closer_name' => $val['closer'],
    //                                 'closer_image' => $val['image'],
    //                                 'total_kw' => $val['kw'],
    //                                 'total_sale' => $val['sale']
    //                             ];
    //                         }
    //                     }
    //                 } else {
    //                     $topCloser = 0;
    //                 }

    //                 if (!empty($topSetter)) {
    //                     $maxSetterKw =  max(array_column($topSetter, 'kw'));
    //                     foreach ($topSetter as $val) {
    //                         if ($val['kw'] ==  $maxSetterKw) {
    //                             $topSetter = [
    //                                 'setter_name' => $val['setter'],
    //                                 'setter_image' => $val['image'],
    //                                 'total_kw' => $val['kw'],
    //                                 'total_sale' => $val['sale']
    //                             ];
    //                         }
    //                     }
    //                 } else {
    //                     $topSetter = 0;
    //                 }
    //             }
    //         } else
    //         if (isset($request->filter) && $request->filter != '') {

    //             if ($request->filter == 'this_year') {
    //                 $now = Carbon::now();
    //                 $monthStart = $now->startOfYear();
    //                 $startDate =  date('Y-m-d', strtotime($monthStart));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->endOfYear()));
    //             } else
    //             if ($request->filter == 'last_year') {
    //                 $startDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
    //             } else
    //             if ($request->filter == 'this_quarter') {
    //                 $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->startOfMonth()));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
    //             } else
    //             if ($request->filter == 'last_quarter') {
    //                 // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
    //                 // $endDate   =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));

    //                 $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
    //                 $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
    //                 $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
    //             } else
    //             if ($request->filter == 'this_month') {
    //                 $new = Carbon::now(); //returns current day
    //                 $firstDay = $new->firstOfMonth();
    //                 $startDate =  date('Y-m-d', strtotime($firstDay));
    //                 $end = Carbon::now();
    //                 $endDate =  date('Y-m-d', strtotime($end));
    //             } else
    //             if ($request->filter == 'last_month') {
    //                 $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
    //                 $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
    //             } else
    //             if ($request->filter == 'this_week') {
    //                 $currentDate = \Carbon\Carbon::now();
    //                 $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
    //                 $endDate =  date('Y-m-d', strtotime(now()));
    //             } else
    //             if ($request->filter == 'last_week') {
    //                 $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
    //                 $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
    //                 $startDate =  date('Y-m-d', strtotime($startOfLastWeek));
    //                 $endDate =  date('Y-m-d', strtotime($endOfLastWeek));
    //             } else if ($request->filter == 'last_12_months') {
    //                 $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
    //                 $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
    //             } else
    //             if ($request->filter == 'custom') {
    //                 $startDate = $request->input('start_date');
    //                 $endDate = $request->input('end_date');
    //             }

    //             // total office ranking

    //             $officeRanking =   $officeRank;
    //             // total manager
    //             // $totalManager = User::where('is_manager',1)->where('office_id',$office_id)->count();
    //             // // total closer
    //             // $totalCloser = User::where('is_manager','!=',1)->where('position_id',2)->where('office_id',$office_id)->count();
    //             // // total setter
    //             // $totalSetter = User::where('is_manager','!=',1)->where('position_id',3)->where('office_id',$office_id)->count();
    //             // // total junior Setter
    //             // $totalJuniorSetter = User::where('position_id',4)->where('office_id',$office_id)->count();

    //             $totalManager = User::where('is_manager', 1)->count();
    //             // total closer
    //             $totalCloser = User::where('is_manager', '!=', 1)->where('position_id', 2)->count();
    //             // total setter
    //             $totalSetter = User::where('is_manager', '!=', 1)->where('position_id', 3)->count();
    //             // total junior Setter
    //             $totalJuniorSetter = User::where('position_id', 4)->count();

    //             $totalUserByOffice = $totalManager + $totalCloser + $totalSetter + $totalJuniorSetter;
    //             if ($request->input('user_id') != '') {
    //                 $userId = $request->input('user_id');
    //                 $totalUid = User::where('id', $userId)->pluck('id');
    //             } else {
    //                 //$totalUid = User::where('office_id',$office_id)->pluck('id');
    //                 //$totalUid = User::pluck('id');
    //             }

    //             $totalPid = SaleMasterProcess::whereIn('closer1_id', $totalUid)->orWhereIn('closer2_id', $totalUid)->orWhereIn('setter1_id', $totalUid)->orWhereIn('setter2_id', $totalUid)->pluck('pid');

    //             // total rep count

    //             $totalRep = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])
    //                 ->whereIn('pid', $totalPid)
    //                 ->where('date_cancelled', null)
    //                 ->count();

    //             // total Revenue

    //             // total Revenue
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $totalRevenuevalues = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->whereIn('pid', $totalPid)->get();
    //                 foreach ($totalRevenuevalues as $totalRevenuevalue) {
    //                     $totalRevenue += $totalRevenuevalue->gross_account_value;
    //                 }

    //                 $totalKW = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->where('date_cancelled', null)->whereNotNull('m1_date')->count();
    //                 $bestDay = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->orderBy('gross_account_value', 'DESC')->first();
    //             } else {
    //                 $totalRevenuevalues = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->whereIn('pid', $totalPid)->get();
    //                 foreach ($totalRevenuevalues as $totalRevenuevalue) {
    //                     $totalRevenue += $totalRevenuevalue->epc * $totalRevenuevalue->kw * 1000;
    //                 }

    //                 $totalKWSum = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->where('date_cancelled', null)->sum('kw');
    //                 $totalKW = round((float)($totalKWSum), 5);
    //                 $bestDay = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $totalPid)->orderBy('kw', 'DESC')->first();
    //             }

    //             $var = date('Y');
    //             $date = Carbon::now();
    //             $dateWeek = strtotime($startDate);
    //             $startWeek =  date("W", $dateWeek);
    //             $eDate = strtotime($endDate);
    //             $endWeek = date("W", $eDate);
    //             if ($startWeek == 52) {
    //                 $i = 1;
    //             } else {
    //                 $i = $startWeek;
    //             }
    //             $bestWeek = [];

    //             for ($i; $i <= $endWeek; $i++) {
    //                 $date->setISODate($var, $i);
    //                 $startWeekDate = $date->startOfWeek();

    //                 $sDate =  date('Y-m-d', strtotime($startWeekDate));
    //                 $endWeekDate = $date->endOfWeek();
    //                 $eDate =  date('Y-m-d', strtotime($endWeekDate));
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $weekAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $totalPid)->sum('gross_account_value');
    //                 } else {
    //                     $weekAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $totalPid)->sum('kw');
    //                 }

    //                 if ($weekAmount > 0) {
    //                     $bestWeek[] = [
    //                         //'week' => 'week-'.$i.'/'.$sDate.'/'.$eDate,
    //                         //'week' => [$sDate,$eDate],
    //                         'week' => $sDate . ',' . $eDate,
    //                         'amount' => round($weekAmount, 5)
    //                     ];
    //                 } else {
    //                     $bestWeek[] = [
    //                         'week' => '0',
    //                         'amount' => 0
    //                     ];;
    //                 }
    //             }
    //             //  return $bestWeek;
    //             $maxWeekAmount =  max(array_column($bestWeek, 'amount'));
    //             if (!empty($bestWeek)) {
    //                 foreach ($bestWeek as $val) {
    //                     if ($val['amount'] ==  $maxWeekAmount) {
    //                         $weekAmount = [
    //                             'week' => $val['week'],
    //                             'amount' => $val['amount']
    //                         ];
    //                     }
    //                 }
    //             }

    //             //Best Week Query changes
    //             if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                 $bestweekNew =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
    //                 sum(cast(gross_account_value as decimal(5,2))) As amount ,
    //                 STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
    //                 adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
    //                     ->whereBetween('customer_signoff', [$startDate, $endDate])
    //                     ->whereIn('pid', $totalPid)
    //                     ->groupBy('week')
    //                     ->orderBy('amount', 'desc')
    //                     ->first();
    //                 if (!empty($bestweekNew)) {
    //                     $bestweekNew = $bestweekNew->toArray();
    //                 }
    //             } else {
    //                 $bestweekNew =  SalesMaster::selectRaw("customer_signoff as date, week(customer_signoff) as week,
    //                 sum(cast(kw as decimal(5,2))) As amount ,
    //                 STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W') as startweek,
    //                 adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff),' ',DAYNAME(customer_signoff)), '%X%V %W'), INTERVAL 6 DAY) as endweek")
    //                     ->whereBetween('customer_signoff', [$startDate, $endDate])
    //                     ->whereIn('pid', $totalPid)
    //                     ->groupBy('week')
    //                     ->orderBy('amount', 'desc')
    //                     ->first();
    //                 if (!empty($bestweekNew)) {
    //                     $bestweekNew = $bestweekNew->toArray();
    //                 }
    //             }
    //             if (!empty($bestweekNew)) {
    //                 $weekAmount = [
    //                     'week' => $bestweekNew['startweek'] . ',' . $bestweekNew['endweek'],
    //                     'amount' => $bestweekNew['amount']
    //                 ];
    //             }

    //             $j = 1;
    //             $bestMonth = [];
    //             $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    //             for ($j; $j < 12; $j++) {
    //                 $array = [];
    //                 $sDate = Carbon::create()->month($j)->startOfMonth()->format($var . '-m-d');
    //                 $eDate = Carbon::create()->month($j)->endOfMonth()->format($var . '-m-d');
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $monthAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
    //                         ->whereIn('pid', $totalPid)
    //                         ->sum('gross_account_value');
    //                 } else {
    //                     $monthAmount = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
    //                         ->whereIn('pid', $totalPid)
    //                         ->sum('kw');
    //                 }

    //                 if ($monthAmount > 0) {
    //                     $bestMonth[] = [
    //                         'month' => $month[$j - 1],
    //                         'amount' => round($monthAmount, 5)
    //                     ];
    //                 } else {
    //                     $bestMonth[] = [
    //                         'month' => 0,
    //                         'amount' => 0
    //                     ];
    //                 }
    //             }
    //             $maxYearAmount =  max(array_column($bestMonth, 'amount'));
    //             if (!empty($bestMonth)) {
    //                 foreach ($bestMonth as $val) {
    //                     if ($val['amount'] ==  $maxYearAmount) {
    //                         $yearAmount = [
    //                             'month' => $val['month'],
    //                             'amount' => $val['amount']
    //                         ];
    //                     }
    //                 }
    //             } else {
    //                 $yearAmount = 0;
    //             }
    //             //$bestTeamId = User::selectRaw('team_id,count(team_id) as countId')->where('office_id',$office_id)->groupBy('team_id')->orderBy('countId','desc')->first();
    //             $bestTeamId = User::selectRaw('team_id,count(team_id) as countId')->groupBy('team_id')->orderBy('countId', 'desc')->first();
    //             if ($request->input('user_id') != '') {
    //                 $userId = $request->input('user_id');
    //                 $result = User::orderBy('id', 'desc')->where('id', $userId);
    //             } else {
    //                 //$result = User::orderBy('id','desc')->where('office_id',$office_id);
    //                 $result = User::orderBy('id', 'desc');
    //             }
    //             if ($request->has('search') && !empty($request->input('search'))) {
    //                 $result->where(function ($query) use ($request) {
    //                     return $query->where('first_name', 'LIKE', '%' . $request->input('search') . '%')
    //                         ->orWhere('last_name', 'LIKE', '%' . $request->input('search') . '%');
    //                 });
    //             }

    //             $total = $result->get();
    //             //  echo "asdasd";   print_r($total); die;
    //             $totalSales = [];
    //             $graph = [];
    //             $topTeam = [];
    //             $topCloser = [];
    //             $topSetter = [];
    //             $totalAcc = [];
    //             $teamVal = [];
    //             foreach ($total as $key => $totals) {
    //                 //$team = ManagementTeam::with('user')->where('office_id',$office_id)->where('id',$totals->team_id)->first();
    //                 $team = ManagementTeam::with('user')->where('id', $totals->team_id)->first();
    //                 // return $team;
    //                 $totalCancelled = [];
    //                 $totalPending = [];
    //                 $totalInstall = [];
    //                 $avgSystemSize = 0;
    //                 $userId = [];
    //                 $userTeamId = [];
    //                 $userTeamName = [];
    //                 $userKw = [];
    //                 $totalAccount = SaleMasterProcess::where('closer1_id', $totals->id)->orWhere('closer2_id', $totals->id)->orWhere('setter1_id', $totals->id)->orWhere('setter2_id', $totals->id)->get();
    //                 $userPid = SaleMasterProcess::where('closer1_id', $totals->id)->orWhere('closer2_id', $totals->id)->orWhere('setter1_id', $totals->id)->orWhere('setter2_id', $totals->id)->pluck('pid');
    //                 foreach ($totalAccount as $totalAccounts) {
    //                     $teamVal['teamId'] = isset($totals->team_id) ? $totals->team_id : null;
    //                     $teamVal['teamName'] = isset($team->team_name) ? $team->team_name : null;
    //                     $teamVal['userId'] = isset($totals->id) ? $totals->id : null;
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $userKw = SalesMaster::where('pid', $totalAccounts->pid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->sum('gross_account_value');
    //                     } else {
    //                         $userKw = SalesMaster::where('pid', $totalAccounts->pid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->sum('kw');
    //                     }

    //                     //echo"DASD";die;
    //                     $teamVal['userKw'] = $userKw;
    //                     $totalCancel = SalesMaster::where('pid', $totalAccounts->pid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                     if ($totalCancel != '') {
    //                         $totalCancelled[] = $totalCancel;
    //                     }
    //                     $pending = SalesMaster::where('pid', $totalAccounts->pid)->where('m2_date', null)->where('date_cancelled', null)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                     if ($pending != '') {
    //                         $totalPending[] = $pending;
    //                     }
    //                     $install = SalesMaster::where('pid', $totalAccounts->pid)->where('m2_date', '!=', null)->where('date_cancelled', null)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                     if ($install != '') {
    //                         $totalInstall[] = $install;
    //                     }
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $systemSize = SalesMaster::select('id', 'gross_account_value')->where('pid', $totalAccounts->pid)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                         if ($systemSize != '') {
    //                             $avgSystemSize = $avgSystemSize + $systemSize->gross_account_value;
    //                         }
    //                     } else {
    //                         $systemSize = SalesMaster::select('id', 'kw')->where('pid', $totalAccounts->pid)->whereBetween('customer_signoff', [$startDate, $endDate])->first();
    //                         if ($systemSize != '') {
    //                             $avgSystemSize = $avgSystemSize + $systemSize->kw;
    //                         }
    //                     }
    //                 }

    //                 $serviced = 0;
    //                 if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                     $serviced = SalesMaster::whereIn('pid', $totalAccount->pluck('pid'))->whereNotNull('m1_date')->whereNull('date_cancelled')->whereBetween('customer_signoff', [$startDate, $endDate])->count();
    //                 }

    //                 $totalSales[] = [
    //                     'user_id' => $totals->id,
    //                     'user_name' => $totals->first_name . ' ' . $totals->last_name,
    //                     'position_id' => isset($totals->position_id) ? $totals->position_id : null,
    //                     'sub_position_id' => isset($totals->sub_position_id) ? $totals->sub_position_id : null,
    //                     'is_super_admin' => isset($totals->is_super_admin) ? $totals->is_super_admin : null,
    //                     'is_manager' => isset($totals->is_manager) ? $totals->is_manager : null,
    //                     'user_image' => $totals->image,
    //                     'team' => isset($team->team_name) ? $team->team_name : null,
    //                     'account' => count($totalAccount),
    //                     'pending' => count($totalPending),
    //                     'pending_percentage' => isset($totalAccount) && count($totalAccount) > 0 ? round((count($totalPending) / count($totalAccount)) * 100, 2) : 0,
    //                     'install' => count($totalInstall),
    //                     'install_percentage' => isset($totalAccount) && count($totalAccount) > 0 ? round((count($totalInstall) / count($totalAccount)) * 100, 2) : 0,
    //                     'cancelled' => count($totalCancelled),
    //                     'cancelled_percentage' => isset($totalAccount) && count($totalAccount) > 0 ? round((count($totalCancelled) / count($totalAccount)) * 100, 2) : 0,
    //                     'team_lead' => isset($team->user[0]->first_name) ? $team->user[0]->first_name : null,
    //                     'team_leader_image' => isset($team->user[0]->image) ? $team->user[0]->image : null,
    //                     //'closing_ratio' => isset($totalAccount) && count($totalAccount) > 0?round(count($totalInstall)/count($totalAccount)*100,5):0,
    //                     'closing_ratio' => isset($totalInstall) && count($totalInstall) > 0 ? round((count($totalInstall) / count($totalInstall) + count($totalCancelled)) * 100, 2) : 0,
    //                     'avg_system_size' =>  isset($totalAccount) && count($totalAccount) > 0 ? round($avgSystemSize / count($totalAccount), 5) : 0,
    //                     'serviced' => $serviced,
    //                     'serviced_percentage' => isset($totalAccount) && count($totalAccount) > 0 ? round(($serviced / count($totalAccount)) * 100, 2) : 0,
    //                 ];

    //                 if ($totals->team_id == $bestTeamId->team_id) {
    //                     $topTeam[] = [
    //                         'team' => isset($team->team_name) ? $team->team_name : null,
    //                         'kw' =>  isset($totalAccount) && count($totalAccount) > 0 ? round($avgSystemSize, 5) : 0,
    //                     ];
    //                 }

    //                 if ($totals->position_id == 2) {
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $closerKw = SalesMaster::whereIn('pid', $userPid)->sum('gross_account_value');
    //                         $closerSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     } else {
    //                         $closerKw = SalesMaster::whereIn('pid', $userPid)->sum('kw');
    //                         $closerSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     }

    //                     $topCloser[] = [
    //                         'closer' => $totals->first_name . ' ' . $totals->last_name,
    //                         'image' => $totals->image,
    //                         'kw' =>  isset($closerKw) ? round($closerKw, 5) : 0,
    //                         'sale' => isset($closerSale) ? round($closerSale, 5) : 0,
    //                     ];
    //                 } else
    //                 if ($totals->position_id == 3) {
    //                     if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
    //                         $setterKw = SalesMaster::whereIn('pid', $userPid)->sum('gross_account_value');
    //                         $setterSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     } else {
    //                         $setterKw = SalesMaster::whereIn('pid', $userPid)->sum('kw');
    //                         $setterSale = SalesMaster::whereIn('pid', $userPid)->count();
    //                     }
    //                     $topSetter[] = [
    //                         'setter' => $totals->first_name . ' ' . $totals->last_name,
    //                         'image' => $totals->image,
    //                         'kw' =>  isset($setterKw) ? round($setterKw, 5) : 0,
    //                         'sale' =>  isset($setterSale) ? round($setterSale, 5) : 0,
    //                     ];
    //                 }
    //                 // account graph

    //                 if (count($totalPending) > 0 || count($totalInstall) > 0 || count($totalCancelled) > 0) {

    //                     $graph[] = [
    //                         'userName' => $totals->first_name,
    //                         'pending' => count($totalPending),
    //                         'install' => count($totalInstall),
    //                         'cancelled' => count($totalCancelled),
    //                     ];
    //                 }
    //             }
    //             if (!empty($topTeam)) {
    //                 $teamKw = 0;
    //                 foreach ($topTeam as $teamval) {
    //                     $teamName = $teamval['team'];
    //                     $teamKw = ($teamKw + $teamval['kw']);
    //                 }
    //             } else {
    //                 $teamName = null;
    //                 $teamKw = 0;
    //             }

    //             $topTeamdata = [
    //                 'team_name' => $teamName,
    //                 'total_kw' => $teamKw
    //             ];
    //             //return $topCloser;
    //             if (!empty($topCloser)) {
    //                 $maxCloserKw = max(array_column($topCloser, 'kw'));
    //                 foreach ($topCloser as $val) {
    //                     if ($val['kw'] ==  $maxCloserKw) {
    //                         $topCloser = [
    //                             'closer_name' => $val['closer'],
    //                             'closer_image' => $val['image'],
    //                             'total_kw' => $val['kw'],
    //                             'total_sale' => $val['sale']
    //                         ];
    //                     }
    //                 }
    //             } else {
    //                 $topCloser = 0;
    //             }

    //             if (!empty($topSetter)) {
    //                 $maxSetterKw =  max(array_column($topSetter, 'kw'));
    //                 foreach ($topSetter as $val) {
    //                     if ($val['kw'] ==  $maxSetterKw) {
    //                         $topSetter = [
    //                             'setter_name' => $val['setter'],
    //                             'setter_image' => $val['image'],
    //                             'total_kw' => $val['kw'],
    //                             'total_sale' => $val['sale']
    //                         ];
    //                     }
    //                 }
    //             } else {
    //                 $topSetter = 0;
    //             }
    //         }
    //     }
    //     // Office Id............................................................

    //     if ($request->has('sort') &&  $request->input('sort') == 'account') {
    //         // $totalSales = json_decode($totalSales);
    //         if ($request->input('sort_val') == 'desc') {
    //             array_multisort(array_column($totalSales, 'account'), SORT_DESC, $totalSales);
    //         } else {
    //             array_multisort(array_column($totalSales, 'account'), SORT_ASC, $totalSales);
    //         }
    //     }
    //     if ($request->has('sort') &&  $request->input('sort') == 'pending') {
    //         // $totalSales = json_decode($totalSales);
    //         if ($request->input('sort_val') == 'desc') {
    //             array_multisort(array_column($totalSales, 'pending'), SORT_DESC, $totalSales);
    //         } else {
    //             array_multisort(array_column($totalSales, 'pending'), SORT_ASC, $totalSales);
    //         }
    //     }
    //     if ($request->has('sort') &&  $request->input('sort') == 'install') {
    //         // $totalSales = json_decode($totalSales);
    //         if ($request->input('sort_val') == 'desc') {
    //             array_multisort(array_column($totalSales, 'install'), SORT_DESC, $totalSales);
    //         } else {
    //             array_multisort(array_column($totalSales, 'install'), SORT_ASC, $totalSales);
    //         }
    //     }
    //     if ($request->has('sort') &&  $request->input('sort') == 'cancelled') {
    //         // $totalSales = json_decode($totalSales);
    //         if ($request->input('sort_val') == 'desc') {
    //             array_multisort(array_column($totalSales, 'cancelled'), SORT_DESC, $totalSales);
    //         } else {
    //             array_multisort(array_column($totalSales, 'cancelled'), SORT_ASC, $totalSales);
    //         }
    //     }
    //     if ($request->has('sort') &&  $request->input('sort') == 'closing_ratio') {
    //         // $totalSales = json_decode($totalSales);
    //         if ($request->input('sort_val') == 'desc') {
    //             array_multisort(array_column($totalSales, 'closing_ratio'), SORT_DESC, $totalSales);
    //         } else {
    //             array_multisort(array_column($totalSales, 'closing_ratio'), SORT_ASC, $totalSales);
    //         }
    //     }
    //     if ($request->has('sort') &&  $request->input('sort') == 'avg_system_size') {
    //         // $totalSales = json_decode($totalSales);
    //         if ($request->input('sort_val') == 'desc') {
    //             array_multisort(array_column($totalSales, 'avg_system_size'), SORT_DESC, $totalSales);
    //         } else {
    //             array_multisort(array_column($totalSales, 'avg_system_size'), SORT_ASC, $totalSales);
    //         }
    //     }
    //     // print_r($totalSales); die;
    //     //array_multisort( array_column( $totalSales, 'account' ), SORT_DESC, $totalSales );
    //     foreach ($totalSales as $totalSale) {
    //         if (isset($totalSale['user_image']) && $totalSale['user_image'] != null) {
    //             $s3_image = s3_getTempUrl(config('app.domain_name') . '/' . $totalSale['user_image']);
    //         } else {
    //             $s3_image = null;
    //         }
    //         $totalSalesData[] = [
    //             'user_id' => $totalSale['user_id'],
    //             'user_name' => $totalSale['user_name'],
    //             'position_id' => isset($totalSale['position_id']) ? $totalSale['position_id'] : null,
    //             'sub_position_id' => isset($totalSale['sub_position_id']) ? $totalSale['sub_position_id'] : null,
    //             'is_super_admin' => isset($totalSale['is_super_admin']) ? $totalSale['is_super_admin'] : null,
    //             'is_manager' => isset($totalSale['is_manager']) ? $totalSale['is_manager'] : null,
    //             //'user_image' => $totalSale['user_image'],
    //             'user_image_s3' => $s3_image,
    //             'team' => $totalSale['team'],
    //             'account' => $totalSale['account'],
    //             'pending' => $totalSale['pending'],
    //             'pending_percentage' => $totalSale['pending_percentage'],
    //             'install' => $totalSale['install'],
    //             'install_percentage' => $totalSale['install_percentage'],
    //             'cancelled' => $totalSale['cancelled'],
    //             'cancelled_percentage' => $totalSale['cancelled_percentage'],
    //             'team_lead' => $totalSale['team_lead'],
    //             'team_leader_image' => $totalSale['team_leader_image'],
    //             'closing_ratio' => $totalSale['closing_ratio'],
    //             'avg_system_size' =>  $totalSale['avg_system_size'],
    //             'serviced' => $totalSale['serviced'],
    //             'serviced_percentage' =>  $totalSale['serviced_percentage'],
    //             'dismiss' => isUserDismisedOn($totalSale['user_id'],date('Y-m-d')) ? 1 : 0,
    //             'terminate' => isUserTerminatedOn($totalSale['user_id'],date('Y-m-d')) ? 1 : 0,
    //             'contract_ended' => isUserContractEnded($totalSale['user_id']) ? 1 : 0,
    //         ];
    //     }

    //     $totalSalesCount = 0;
    //     if (isset($request->office_id) && $request->office_id != 'all') {
    //         $userIds = User::where('office_id', $request->office_id)->pluck('id');
    //         $salesPid = SaleMasterProcess::whereIn('closer1_id', $userIds)->orWhereIn('closer2_id', $userIds)->orWhereIn('setter1_id', $userIds)->orWhereIn('setter2_id', $userIds)->pluck('pid');
    //         $totalSalesCount = SalesMaster::whereIn('pid', $salesPid)->whereBetween('customer_signoff', [$startDate, $endDate])->count();
    //     }

    //     $data['office_ranking'] = $officeRanking;
    //     $data['total_rep'] = $totalUserByOffice;
    //     $data['office_name'] = isset($office->office_name) ? $office->office_name : null;
    //     $data['total_manager'] = $totalManager;
    //     $data['total_closer'] = $totalCloser;
    //     $data['total_setter'] = $totalSetter;
    //     $data['total_sale'] = $totalSalesCount;
    //     $data['total_junior_setter'] = $totalJuniorSetter;
    //     $data['total_account'] = $totalRep;
    //     $data['total_revenue'] = round((float)($totalRevenue), 5);
    //     $data['total_kw'] = $totalKW;

    //     $data['best_day'] = $dayKw;
    //     $data['best_week'] = isset($weekAmount) ? $weekAmount : null;
    //     $data['best_month'] = isset($yearAmount) ? $yearAmount : null;
    //     $data['best_team'] = isset($teamBest) ? $teamBest : null;
    //     $data['best_manager'] = isset($managerBest) ? $managerBest : null;
    //     $data['best_closer'] = isset($topCloser) ? $topCloser : null;
    //     $data['best_setter'] = isset($topSetter) ? $topSetter : null;
    //     $data['graph'] = $graph;
    //     //return $totalSalesData;

    //     $totalSalesDatas = $this->paginate($totalSalesData, $perpage, $request['page']);
    //     $data['employee_performance'] = isset($totalSalesDatas) ? $totalSalesDatas : null;
    //     //array_multisort($totalSales, $totalAccount);
    //     $exportdata =  $totalSalesData;
    //     if (isset($request->is_export) && ($request->is_export == 1)) {
    //         $file_name = 'manager_office_export_' . date('Y_m_d_H_i_s') . '.csv';
    //         return Excel::download(new ExportReportOfficeStandard($exportdata), $file_name);
    //     }
    //     return response()->json([
    //         'ApiName' => 'manager_report_office_api',
    //         'status' => true,
    //         'message' => 'Successfully.',
    //         'data' => $data,
    //     ], 200);
    // }

    public function officeReport(Request $request)
    {
        // Initialize default values
        $perpage = $request->perpage ?? 10;
        $office_id = Auth()->user()->office_id;
        $companyProfile = CompanyProfile::first();
        $isPestCompany = in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE);

        // Get date range based on filter
        $dateRange = $this->getDateRange($request->filter, $request);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Initialize response data structure
        $data = $this->initializeResponseData();

        // Process office data
        if (isset($request->office_id)) {
            if ($request->office_id != 'all') {
                $data = $this->processSpecificOffice($request->office_id, $startDate, $endDate, $isPestCompany, $data, $request);
            } else {
                $data = $this->processAllOffices($request, $startDate, $endDate, $isPestCompany, $data);
            }
        } else {
            $data = $this->processDefaultOffice($office_id, $startDate, $endDate, $isPestCompany, $data, $request);
        }

        // Process employee performance data (including graph data)
        $processedData = $this->processEmployeePerformance($request, $startDate, $endDate, $isPestCompany, $data['office_id'] ?? $office_id);
        $totalSalesData = $processedData['performance_data'];
        $data['graph'] = $processedData['graph_data'];

        // Sort data if requested
        if ($request->has('sort')) {
            $totalSalesData = $this->sortData($totalSalesData, $request->sort, $request->input('sort_val'));
        }

        // Format final response
        $data = $this->formatFinalResponse($data, $totalSalesData, $perpage, $request->page);

        // Handle export if needed
        if (isset($request->is_export) && $request->is_export == 1) {
            return $this->handleExport($totalSalesData);
        }

        return response()->json([
            'ApiName' => 'manager_report_office_api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    private function getDateRange($filter, $request)
    {
        $now = Carbon::now();

        switch ($filter) {
            case 'this_year':
                return [
                    'start' => $now->copy()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->endOfYear()->format('Y-m-d'),
                ];
            case 'last_year':
                return [
                    'start' => $now->copy()->subYear()->startOfYear()->format('Y-m-d'),
                    'end' => $now->copy()->subYear()->endOfYear()->format('Y-m-d'),
                ];
            case 'this_quarter':
                return [
                    'start' => $now->copy()->startOfQuarter()->format('Y-m-d'),
                    'end' => $now->copy()->endOfQuarter()->format('Y-m-d'),
                ];
            case 'last_quarter':
                $lastQuarter = $now->copy()->subQuarter();

                return [
                    'start' => $lastQuarter->startOfQuarter()->format('Y-m-d'),
                    'end' => $lastQuarter->endOfQuarter()->format('Y-m-d'),
                ];
            case 'this_month':
                return [
                    'start' => $now->copy()->startOfMonth()->format('Y-m-d'),
                    'end' => $now->copy()->endOfMonth()->format('Y-m-d'),
                ];
            case 'last_month':
                $lastMonth = $now->copy()->subMonth();

                return [
                    'start' => $lastMonth->startOfMonth()->format('Y-m-d'),
                    'end' => $lastMonth->endOfMonth()->format('Y-m-d'),
                ];
            case 'this_week':
                return [
                    'start' => $now->copy()->startOfWeek()->format('Y-m-d'),
                    'end' => $now->copy()->endOfWeek()->format('Y-m-d'),
                ];
            case 'last_week':
                $lastWeek = $now->copy()->subWeek();

                return [
                    'start' => $lastWeek->startOfWeek()->format('Y-m-d'),
                    'end' => $lastWeek->endOfWeek()->format('Y-m-d'),
                ];
            case 'last_12_months':
                return [
                    'start' => $now->copy()->subMonths(12)->format('Y-m-d'),
                    'end' => $now->copy()->addDay()->format('Y-m-d'),
                ];
            case 'custom':
                return [
                    'start' => $request->input('start_date'),
                    'end' => $request->input('end_date'),
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth()->format('Y-m-d'),
                    'end' => $now->copy()->endOfMonth()->format('Y-m-d'),
                ];
        }
    }

    private function initializeResponseData()
    {
        return [
            'office_ranking' => '',
            'total_rep' => '',
            'office_name' => null,
            'total_manager' => '',
            'total_closer' => '',
            'total_setter' => '',
            'total_sale' => '',
            'total_junior_setter' => '',
            'total_account' => '',
            'total_revenue' => '',
            'total_kw' => '',
            'best_day' => '',
            'best_week' => null,
            'best_month' => null,
            'best_team' => null,
            'best_manager' => null,
            'best_closer' => null,
            'best_setter' => null,
            'graph' => [],
        ];
    }

    private function processSpecificOffice($officeId, $startDate, $endDate, $isPestCompany, $data, $request)
    {
        $office = Locations::where('type', 'Office')->where('id', $officeId)->first();
        $data['office_name'] = $office->office_name ?? null;
        $data['office_id'] = $officeId;

        // Get all user IDs for this office
        $userIds = User::where('office_id', $officeId);

        if ($request->input('user_id')) {
            $userIds = $userIds->where('id', $request->input('user_id'));
        }

        $userIds = $userIds->pluck('id');

        // Calculate office ranking
        $data['office_ranking'] = $this->calculateOfficeRanking($officeId, $startDate, $endDate, $isPestCompany);

        // Get counts for different positions
        $data['total_manager'] = User::where('office_id', $officeId)->where('is_manager', 1)->count();
        $data['total_closer'] = User::where('office_id', $officeId)->where('is_manager', '!=', 1)->where('position_id', 2)->count();
        $data['total_setter'] = User::where('office_id', $officeId)->where('is_manager', '!=', 1)->where('position_id', 3)->count();
        $data['total_junior_setter'] = User::where('office_id', $officeId)->where('position_id', 4)->count();
        $data['total_rep'] = $data['total_manager'] + $data['total_closer'] + $data['total_setter'] + $data['total_junior_setter'];

        // Get sales data
        $salesPidQuery = SaleMasterProcess::whereIn('closer1_id', $userIds)
            ->orWhereIn('closer2_id', $userIds)
            ->orWhereIn('setter1_id', $userIds)
            ->orWhereIn('setter2_id', $userIds);

        $salesPid = $salesPidQuery->pluck('pid');

        $data['total_sale'] = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->count();

        // Calculate revenue and KW
        $salesData = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->get();

        $data['total_revenue'] = 0;
        $data['total_kw'] = 0;

        foreach ($salesData as $sale) {
            if ($isPestCompany) {
                $data['total_revenue'] += $sale->gross_account_value;
                $data['total_kw'] += 1; // Count as 1 for pest company
            } else {
                $data['total_revenue'] += $sale->epc * $sale->kw * 1000;
                $data['total_kw'] += $sale->kw;
            }
        }

        $data['total_revenue'] = round($data['total_revenue'], 5);
        $data['total_kw'] = round($data['total_kw'], 5);
        $data['total_account'] = $salesData->count();

        // Find best performers
        $data['best_closer'] = $this->findBestCloser($userIds, $startDate, $endDate, $isPestCompany);
        $data['best_setter'] = $this->findBestSetter($userIds, $startDate, $endDate, $isPestCompany);
        $data['best_team'] = $this->findBestTeam($officeId, $startDate, $endDate, $isPestCompany, $request);
        $data['best_manager'] = $this->findBestManager($officeId, $startDate, $endDate, $isPestCompany);
        $data['best_day'] = $this->findBestDay($salesPid, $startDate, $endDate, $isPestCompany);
        $data['best_week'] = $this->findBestWeek($salesPid, $startDate, $endDate, $isPestCompany);
        $data['best_month'] = $this->findBestMonth($salesPid, $startDate, $endDate, $isPestCompany);

        return $data;
    }

    private function processAllOffices($request, $startDate, $endDate, $isPestCompany, $data)
    {
        $data['office_name'] = 'All Offices';

        // Get all user IDs
        $userIds = User::query();

        if ($request->input('user_id')) {
            $userIds = $userIds->where('id', $request->input('user_id'));
        }

        $userIds = $userIds->pluck('id');

        // Get counts for different positions
        $data['total_manager'] = User::where('is_manager', 1)->count();
        $data['total_closer'] = User::where('is_manager', '!=', 1)->where('position_id', 2)->count();
        $data['total_setter'] = User::where('is_manager', '!=', 1)->where('position_id', 3)->count();
        $data['total_junior_setter'] = User::where('position_id', 4)->count();
        $data['total_rep'] = $data['total_manager'] + $data['total_closer'] + $data['total_setter'] + $data['total_junior_setter'];

        // Get sales data
        $salesPid = SaleMasterProcess::whereIn('closer1_id', $userIds)
            ->orWhereIn('closer2_id', $userIds)
            ->orWhereIn('setter1_id', $userIds)
            ->orWhereIn('setter2_id', $userIds)
            ->pluck('pid');

        $data['total_sale'] = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->count();

        // Calculate revenue and KW
        $salesData = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->whereNull('date_cancelled')
            ->get();

        $data['total_revenue'] = 0;
        $data['total_kw'] = 0;

        foreach ($salesData as $sale) {
            if ($isPestCompany) {
                $data['total_revenue'] += $sale->gross_account_value;
                $data['total_kw'] += 1; // Count as 1 for pest company
            } else {
                $data['total_revenue'] += $sale->epc * $sale->kw * 1000;
                $data['total_kw'] += $sale->kw;
            }
        }

        $data['total_revenue'] = round($data['total_revenue'], 5);
        $data['total_kw'] = round($data['total_kw'], 5);
        $data['total_account'] = $salesData->count();

        // Find best performers
        $data['best_closer'] = $this->findBestCloser($userIds, $startDate, $endDate, $isPestCompany);
        $data['best_setter'] = $this->findBestSetter($userIds, $startDate, $endDate, $isPestCompany);
        $data['best_team'] = $this->findBestTeam(null, $startDate, $endDate, $isPestCompany, $request);
        $data['best_manager'] = $this->findBestManager(null, $startDate, $endDate, $isPestCompany);
        $data['best_day'] = $this->findBestDay($salesPid, $startDate, $endDate, $isPestCompany);
        $data['best_week'] = $this->findBestWeek($salesPid, $startDate, $endDate, $isPestCompany);
        $data['best_month'] = $this->findBestMonth($salesPid, $startDate, $endDate, $isPestCompany);

        return $data;
    }

    private function processDefaultOffice($officeId, $startDate, $endDate, $isPestCompany, $data, $request)
    {
        return $this->processSpecificOffice($officeId, $startDate, $endDate, $isPestCompany, $data, $request);
    }

    private function processEmployeePerformance($request, $startDate, $endDate, $isPestCompany, $officeId)
    {
        $query = User::query();

        if ($officeId) {
            $query->where('office_id', $officeId);
        }

        if ($request->input('user_id')) {
            $query->where('id', $request->input('user_id'));
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%");
            });
        }

        $users = $query->with(['teamsDetail', 'teamsDetail.user'])->get();
        $performanceData = [];
        $graphData = [];

        foreach ($users as $user) {
            $salesPid = SaleMasterProcess::where('closer1_id', $user->id)
                ->orWhere('closer2_id', $user->id)
                ->orWhere('setter1_id', $user->id)
                ->orWhere('setter2_id', $user->id)
                ->pluck('pid');

            $salesQuery = SalesMaster::whereIn('pid', $salesPid)
                ->whereBetween('customer_signoff', [$startDate, $endDate]);

            $totalSales = $salesQuery->count();
            $cancelledSales = $salesQuery->clone()->whereNotNull('date_cancelled')->count();

            if ($isPestCompany) {
                $installedSales = $salesQuery->clone()->whereNotNull('m1_date')->whereNull('date_cancelled')->count();
                $pendingSales = $salesQuery->clone()->whereNull('m1_date')->whereNull('date_cancelled')->count();
                $totalKw = $salesQuery->clone()->sum('gross_account_value');
                $serviced = $salesQuery->clone()->whereNotNull('m1_date')->whereNull('date_cancelled')->count();
            } else {
                $installedSales = $salesQuery->clone()->whereNotNull('m2_date')->whereNull('date_cancelled')->count();
                $pendingSales = $salesQuery->clone()->whereNull('m2_date')->whereNull('date_cancelled')->count();
                $totalKw = $salesQuery->clone()->sum('kw');
                $serviced = 0;
            }

            $closingRatio = ($installedSales + $cancelledSales) > 0
                ? round(($installedSales / ($installedSales + $cancelledSales)) * 100, 2)
                : 0;

            $avgSystemSize = $totalSales > 0 ? round($totalKw / $totalSales, 5) : 0;
            $servicedPercentage = $totalSales > 0 ? round(($serviced / $totalSales) * 100, 2) : 0;

            $performanceData[] = [
                'user_id' => $user->id,
                'user_name' => $user->first_name.' '.$user->last_name,
                'position_id' => $user->position_id,
                'sub_position_id' => $user->sub_position_id,
                'is_super_admin' => $user->is_super_admin,
                'is_manager' => $user->is_manager,
                'user_image' => $user->image,
                'team' => $user->teamsDetail->team_name ?? null,
                'account' => $totalSales,
                'pending' => $pendingSales,
                'pending_percentage' => $totalSales > 0 ? round(($pendingSales / $totalSales) * 100, 2) : 0,
                'install' => $installedSales,
                'install_percentage' => $totalSales > 0 ? round(($installedSales / $totalSales) * 100, 2) : 0,
                'cancelled' => $cancelledSales,
                'cancelled_percentage' => $totalSales > 0 ? round(($cancelledSales / $totalSales) * 100, 2) : 0,
                'team_lead' => $user->teamsDetail->user[0]->first_name ?? null,
                'team_leader_image' => $user->teamsDetail->user[0]->image ?? null,
                'closing_ratio' => $closingRatio,
                'avg_system_size' => $avgSystemSize,
                'serviced' => $serviced,
                'serviced_percentage' => $servicedPercentage,
            ];

            // Add to graph data if there are any relevant sales
            if ($pendingSales > 0 || $installedSales > 0 || $cancelledSales > 0) {
                $graphData[] = [
                    'userName' => $user->first_name,
                    'pending' => $pendingSales,
                    'install' => $installedSales,
                    'cancelled' => $cancelledSales,
                ];
            }
        }

        return [
            'performance_data' => $performanceData,
            'graph_data' => $graphData,
        ];
    }

    private function sortData($data, $sortField, $sortDirection)
    {
        $direction = $sortDirection == 'desc' ? SORT_DESC : SORT_ASC;
        array_multisort(array_column($data, $sortField), $direction, $data);

        return $data;
    }

    private function formatFinalResponse($data, $totalSalesData, $perpage, $page)
    {
        // Initialize summary counters
        $totalAccount = 0;
        $totalRevenue = 0;
        $totalServiced = 0;

        // Process each employee's data and accumulate summary metrics
        foreach ($totalSalesData as &$sale) {
            // Add S3 image URLs and status flags
            $sale['user_image_s3'] = $sale['user_image'] ? s3_getTempUrl(config('app.domain_name').'/'.$sale['user_image']) : null;
            $sale['dismiss'] = isUserDismisedOn($sale['user_id'], date('Y-m-d')) ? 1 : 0;
            $sale['terminate'] = isUserTerminatedOn($sale['user_id'], date('Y-m-d')) ? 1 : 0;
            $sale['contract_ended'] = isUserContractEnded($sale['user_id']) ? 1 : 0;
            // Accumulate summary data
            $totalAccount += $sale['account'];
            $totalServiced += $sale['serviced'];
            $totalRevenue += $sale['avg_system_size'] ?? 0;
        }

        // Update summary data in response
        $data['total_account'] = $totalAccount;
        $data['total_revenue'] = (! empty($data['total_revenue']) ? $data['total_revenue'] : round($totalRevenue, 5));
        $data['total_kw'] = $totalServiced;

        // Paginate the results using the class paginate method
        $data['employee_performance'] = $this->paginate($totalSalesData, $perpage, $page);

        return $data;
    }

    private function handleExport($data)
    {
        $file_name = 'manager_office_export_'.date('Y_m_d_H_i_s').'.csv';

        return Excel::download(new ExportReportOfficeStandard($data), $file_name);
    }

    private function calculateOfficeRanking($officeId, $startDate, $endDate, $isPestCompany)
    {
        $offices = Locations::where('type', 'Office')->get();
        $rankings = [];

        foreach ($offices as $office) {
            $userIds = User::where('office_id', $office->id)->pluck('id');

            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userIds)
                ->orWhereIn('closer2_id', $userIds)
                ->orWhereIn('setter1_id', $userIds)
                ->orWhereIn('setter2_id', $userIds)
                ->pluck('pid');

            if ($isPestCompany) {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('gross_account_value');
            } else {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('kw');
            }

            if ($total > 0) {
                $rankings[$office->id] = $total;
            }
        }

        arsort($rankings);
        $ranking = array_search($officeId, array_keys($rankings)) + 1;

        return $ranking;
    }

    private function findBestCloser($userIds, $startDate, $endDate, $isPestCompany)
    {
        $closers = User::whereIn('id', $userIds)
            ->where('position_id', 2)
            ->get();

        $bestCloser = null;
        $maxValue = 0;

        foreach ($closers as $closer) {
            $salesPid = SaleMasterProcess::where('closer1_id', $closer->id)
                ->orWhere('closer2_id', $closer->id)
                ->pluck('pid');

            if ($isPestCompany) {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();
                $salesCount = $total;
            } else {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('kw');
                $salesCount = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();
            }

            if ($total > $maxValue) {
                $maxValue = $total;
                $bestCloser = [
                    'closer_name' => $closer->first_name.' '.$closer->last_name,
                    'closer_image' => $closer->image,
                    'total_kw' => round($total, 5),
                    'total_sale' => $salesCount,
                ];
            }
        }

        return $bestCloser;
    }

    private function findBestSetter($userIds, $startDate, $endDate, $isPestCompany)
    {
        $setters = User::whereIn('id', $userIds)
            ->where('position_id', 3)
            ->get();

        $bestSetter = null;
        $maxValue = 0;

        foreach ($setters as $setter) {
            $salesPid = SaleMasterProcess::where('setter1_id', $setter->id)
                ->orWhere('setter2_id', $setter->id)
                ->pluck('pid');

            if ($isPestCompany) {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();
                $salesCount = $total;
            } else {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('kw');
                $salesCount = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();
            }

            if ($total > $maxValue) {
                $maxValue = $total;
                $bestSetter = [
                    'setter_name' => $setter->first_name.' '.$setter->last_name,
                    'setter_image' => $setter->image,
                    'total_kw' => round($total, 5),
                    'total_sale' => $salesCount,
                ];
            }
        }

        return $bestSetter;
    }

    private function findBestTeam($officeId, $startDate, $endDate, $isPestCompany, $request)
    {
        $query = ManagementTeam::query();

        if ($officeId) {
            $query->where('office_id', $officeId);
        }

        $teams = $query->with('user')->get();
        $bestTeam = null;
        $maxValue = 0;

        foreach ($teams as $team) {
            $userIds = $team->user->pluck('id');

            if ($request->input('user_id')) {
                if (! $userIds->contains($request->input('user_id'))) {
                    continue;
                }
            }

            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userIds)
                ->orWhereIn('closer2_id', $userIds)
                ->orWhereIn('setter1_id', $userIds)
                ->orWhereIn('setter2_id', $userIds)
                ->pluck('pid');

            if ($isPestCompany) {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('gross_account_value');
                $salesCount = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();
            } else {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('kw');
                $salesCount = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();
            }

            if ($total > $maxValue) {
                $maxValue = $total;
                $bestTeam = [
                    'team_name' => $team->team_name,
                    'total_kw' => round($total, 5),
                    'total_sale' => $salesCount,
                ];
            }
        }

        return $bestTeam;
    }

    private function findBestManager($officeId, $startDate, $endDate, $isPestCompany)
    {
        $query = User::where('is_manager', 1);

        if ($officeId) {
            $query->where('office_id', $officeId);
        }

        $managers = $query->get();
        $bestManager = null;
        $maxValue = 0;

        foreach ($managers as $manager) {
            $userIds = User::where('manager_id', $manager->id)->pluck('id');

            $salesPid = SaleMasterProcess::whereIn('closer1_id', $userIds)
                ->orWhereIn('closer2_id', $userIds)
                ->orWhereIn('setter1_id', $userIds)
                ->orWhereIn('setter2_id', $userIds)
                ->pluck('pid');

            if ($isPestCompany) {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->count();
            } else {
                $total = SalesMaster::whereIn('pid', $salesPid)
                    ->whereBetween('customer_signoff', [$startDate, $endDate])
                    ->sum('kw');
            }

            if ($total > $maxValue) {
                $maxValue = $total;
                $bestManager = [
                    'manager_name' => $manager->name,
                    'total_kw' => round($total, 5),
                ];

                if ($isPestCompany) {
                    $bestManager['total_sale'] = $total;
                    unset($bestManager['total_kw']);
                }
            }
        }

        return $bestManager;
    }

    private function findBestDay($salesPid, $startDate, $endDate, $isPestCompany)
    {
        $bestDay = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->selectRaw('customer_signoff as day, '.($isPestCompany ? 'COUNT(*) as day_kw' : 'SUM(kw) as day_kw'))
            ->groupBy('customer_signoff')
            ->orderBy('day_kw', 'DESC')
            ->first();

        if (! $bestDay) {
            return null;
        }

        return [
            'day' => $bestDay->day,
            'dayKw' => round($bestDay->day_kw, 5),
        ];
    }

    private function findBestWeek($salesPid, $startDate, $endDate, $isPestCompany)
    {
        $bestWeek = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->selectRaw('
                customer_signoff as date,
                week(customer_signoff) as week,
                '.($isPestCompany ? 'COUNT(*) as amount' : 'SUM(kw) as amount').',
                STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff)," ",DAYNAME(customer_signoff)), "%X%V %W") as startweek,
                adddate(STR_TO_DATE(concat(year(customer_signoff),week(customer_signoff)," ",DAYNAME(customer_signoff)), "%X%V %W"), INTERVAL 6 DAY) as endweek
            ')
            ->groupBy('week')
            ->orderBy('amount', 'desc')
            ->first();

        if (! $bestWeek) {
            return null;
        }

        return [
            'week' => $bestWeek->startweek.','.$bestWeek->endweek,
            'amount' => round($bestWeek->amount, 5),
        ];
    }

    private function findBestMonth($salesPid, $startDate, $endDate, $isPestCompany)
    {
        $bestMonth = SalesMaster::whereIn('pid', $salesPid)
            ->whereBetween('customer_signoff', [$startDate, $endDate])
            ->selectRaw('
                MONTHNAME(customer_signoff) as month,
                '.($isPestCompany ? 'COUNT(*) as amount' : 'SUM(kw) as amount').'
            ')
            ->groupBy('month')
            ->orderBy('amount', 'desc')
            ->first();

        if (! $bestMonth) {
            return null;
        }

        return [
            'month' => $bestMonth->month,
            'amount' => round($bestMonth->amount, 5),
        ];
    }

    public function paginate($items, $perPage = null, $page = null)
    {
        $total = count($items);
        $paginator = new LengthAwarePaginator($items, $total, $perPage, $page);
        $start = ($paginator->currentPage() - 1) * $perPage;
        $sliced = array_slice($items, $start, $perPage);

        return new LengthAwarePaginator($sliced, $total, $perPage, $page, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function exportManagerReportData(Request $request)
    {
        $file_name = 'employees_'.date('Y_m_d_H_i_s').'.csv';
        $stateId = Auth()->user()->state_id;
        $managerId = Auth()->user()->manager_id;
        $state = State::where('id', $stateId)->first();
        if (isset($request->location) && $request->location != '') {
            $stateCode = $request->location;
        } else {
            $stateCode = $state->state_code;
        }
        if (isset($request->location) && isset($request->time_val)) {
            if ($request->time_val == 'this_year') {
                $now = Carbon::now();
                $monthStart = $now->startOfYear();
                $startDate = date('Y-m-d', strtotime($monthStart));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            } elseif ($request->time_val == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
            } elseif ($request->time_val == 'this_month') {
                $new = Carbon::now(); // returns current day
                $firstDay = $new->firstOfMonth();
                $startDate = date('Y-m-d', strtotime($firstDay));
                $end = Carbon::now();
                $endDate = date('Y-m-d', strtotime($end));
            } elseif ($request->time_val == 'last_month') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
            } elseif ($request->time_val == 'past_three_month') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
            } elseif ($request->filter == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate = date('Y-m-d', strtotime(now()));
            } elseif ($request->filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            } elseif ($request->filter == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
            } elseif ($request->filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
            }

            return Excel::download(new ManagerReportDataExport($startDate, $endDate, $stateCode), $file_name);
        } else {
            return Excel::download(new ManagerReportDataExport($stateCode), $file_name);
        }
    }

    // reconciliation....
    public function reconciliation_shweta(Request $request)
    {
        $positionId = Auth()->user()->position_id;
        if ($positionId == 1) {
            $managerUser = User::where('manager_id', Auth()->user()->manager_id)->pluck('id');
        } else {
            $managerUser = User::where('id', Auth()->user()->id)->pluck('id');
        }

        $j = 0;
        $countArr = [];
        $var = date('Y');
        $startDate = date('Y-m-d', strtotime($request->start_date));
        $endDate = date('Y-m-d', strtotime($request->end_date));

        $startm = Carbon::parse($startDate)->month;
        $endm = Carbon::parse($endDate)->month;
        // Graph ......................
        $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = $startm; $i <= $endm; $i++) {
            // return Carbon::now();
            $finalMonth = $i;
            $startMonth = Carbon::now()->month($finalMonth)->day(1)->format($var.'-m-d');
            $endMonth = Carbon::now()->month($finalMonth)->endOfMonth()->format($var.'-m-d');

            $monthAmount = SalesMaster::whereBetween('customer_signoff', [$startMonth, $endMonth])->sum('gross_account_value');
            $m1Amount = SalesMaster::whereBetween('customer_signoff', [$startMonth, $endMonth])->sum('m1_amount');
            $m2Amount = SalesMaster::whereBetween('customer_signoff', [$startMonth, $endMonth])->sum('m2_amount');

            $countArr[$month[$i - 1]]['total_earnings'] = round($monthAmount, 5);
            $countArr[$month[$i - 1]]['m1'] = round($m1Amount, 5);
            $countArr[$month[$i - 1]]['m2'] = round($m2Amount, 5);
        }

        // $managerUser=User::where('manager_id',Auth()->user()->manager_id)->pluck('id');
        // Total Account ......................
        $totalAccount = SaleMasterProcess::whereIn('closer1_id', $managerUser)->orWhereIn('closer2_id', $managerUser)->orWhereIn('setter1_id', $managerUser)->orWhereIn('setter2_id', $managerUser)->count();

        $totalOverrides = UserOverridesLock::whereIn('sale_user_id', $managerUser)->sum('amount');

        // Total Commission ......................
        $commissionSum = SaleMasterProcess::selectRaw('sum(closer1_commission) as commission1,
                    sum(closer2_commission) as commission2,
                    sum(setter1_commission) as commission3,
                    sum(setter2_commission) as commission4')
            ->whereIn('closer1_id', $managerUser)
            ->orWhereIn('closer2_id', $managerUser)
            ->orWhereIn('setter1_id', $managerUser)
            ->orWhereIn('setter2_id', $managerUser)
            ->firstOrFail();
        $totalCommission = $commissionSum->commission1 + $commissionSum->commission2 + $commissionSum->commission3 + $commissionSum->commission4;
        // Total M1_Paid ......................
        $m1Paid = SaleMasterProcess::selectRaw('sum(closer1_m1) as m1_paid1,
                    sum(closer2_m1) as m1_paid2,
                    sum(setter1_m1) as m1_paid3,
                    sum(setter2_m1) as m1_paid4')
            ->whereIn('closer1_id', $managerUser)
            ->orWhereIn('closer2_id', $managerUser)
            ->orWhereIn('setter1_id', $managerUser)
            ->orWhereIn('setter2_id', $managerUser)
            ->firstOrFail();
        $totalM1Paid = round($m1Paid->m1_paid1 + $m1Paid->m1_paid2 + $m1Paid->m1_paid3 + $m1Paid->m1_paid4, 5);
        // Total M2_Paid ......................
        $m2Paid = SaleMasterProcess::selectRaw('sum(closer1_m2) as m2_paid1,
                    sum(closer2_m2) as m2_paid2,
                    sum(setter1_m2) as m2_paid3,
                    sum(setter2_m2) as m2_paid4')
            ->whereIn('closer1_id', $managerUser)
            ->orWhereIn('closer2_id', $managerUser)
            ->orWhereIn('setter1_id', $managerUser)
            ->orWhereIn('setter2_id', $managerUser)
            ->firstOrFail();

        $totalM2Paid = round($m2Paid->m2_paid1 + $m2Paid->m2_paid2 + $m2Paid->m2_paid3 + $m2Paid->m2_paid4, 5);
        $totalDueCommission = $totalCommission - ($totalM1Paid + $totalM2Paid);

        $totalPid = SaleMasterProcess::whereIn('closer1_id', $managerUser)->orWhereIn('closer2_id', $managerUser)->orWhereIn('setter1_id', $managerUser)->orWhereIn('setter2_id', $managerUser)->pluck('pid');
        // clawback .................
        $clawBackData = SalesMaster::whereIn('pid', $totalPid)->where('date_cancelled', '!=', null)->count();
        $clawBackDataAmount = SalesMaster::whereIn('pid', $totalPid)->where('date_cancelled', '!=', null)->sum('gross_account_value');

        // Other Item...................
        $reimbursements = ApprovalsAndRequestLock::where('adjustment_type_id', 2)->sum('amount');
        $incentive = ApprovalsAndRequestLock::where('adjustment_type_id', 6)->sum('amount');
        $bonus = ApprovalsAndRequestLock::where('adjustment_type_id', 3)->sum('amount');
        $miscellaneous = ApprovalsAndRequestLock::where('cost_tracking_id', 15)->sum('amount');
        $travel = ApprovalsAndRequestLock::where('cost_tracking_id', 1)->sum('amount');
        $rent = ApprovalsAndRequestLock::where('cost_tracking_id', 9)->sum('amount');

        // other item Paid................
        $reimbursementsPaid = ApprovalsAndRequestLock::where('adjustment_type_id', 2)->where('status', 1)->sum('amount');
        $incentivePaid = ApprovalsAndRequestLock::where('adjustment_type_id', 6)->where('status', 1)->sum('amount');
        $bonusPaid = ApprovalsAndRequestLock::where('adjustment_type_id', 3)->where('status', 1)->sum('amount');
        $miscellaneousPaid = ApprovalsAndRequestLock::where('cost_tracking_id', 15)->where('status', 1)->sum('amount');
        $travelPaid = ApprovalsAndRequestLock::where('cost_tracking_id', 1)->where('status', 1)->sum('amount');
        $rentPaid = ApprovalsAndRequestLock::where('cost_tracking_id', 9)->where('status', 1)->sum('amount');

        $otherItem = $reimbursements + $incentive + $miscellaneous + $travel + $rent + $bonus;
        $otherItemPaid = $reimbursementsPaid + $incentivePaid + $miscellaneousPaid + $travelPaid + $rentPaid + $bonusPaid;
        $commissionDue = $otherItem - $otherItemPaid;

        // Deduction
        $rent = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 9)->first();
        $rentType = isset($rent->deduction_type) ? $rent->deduction_type : '';
        $rentAmount = isset($rent->ammount_par_paycheck) ? $rent->ammount_par_paycheck : 0;
        $deductionRent = $rentType.' '.$rentAmount;

        $travelDeduction = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 1)->first();
        $travelType = isset($travelDeduction->deduction_type) ? $travelDeduction->deduction_type : '';
        $travelAmount = isset($travelDeduction->ammount_par_paycheck) ? $travelDeduction->ammount_par_paycheck : 0;
        $deductionTravel = $travelType.' '.$travelAmount;

        $phoneBill = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 20)->first();
        $phoneBillType = isset($phoneBill->deduction_type) ? $phoneBill->deduction_type : '';
        $phoneBillAmount = isset($phoneBill->ammount_par_paycheck) ? $phoneBill->ammount_par_paycheck : 0;
        $deductionPhoneBill = $phoneBillType.' '.$phoneBillAmount;

        // Deduction Paid...........................

        $rentPaid = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 9)->where('deduction_setting_id', 1)->first();
        $rentPaidAmount = isset($rentPaid->ammount_par_paycheck) ? $rentPaid->ammount_par_paycheck : 0;

        $travelDeductionPaid = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 1)->where('deduction_setting_id', 1)->first();
        $travelPaidAmount = isset($travelDeductionPaid->ammount_par_paycheck) ? $travelDeductionPaid->ammount_par_paycheck : 0;

        $phoneBillPaid = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 20)->where('deduction_setting_id', 1)->first();
        $phoneBillAmountPaid = isset($phoneBillPaid->ammount_par_paycheck) ? $phoneBillPaid->ammount_par_paycheck : 0;

        $totalDeduction = $rentPaidAmount + $travelPaidAmount + $phoneBillAmountPaid;

        // total overrides .....................

        $totalEarnedOverrides = UserOverridesLock::whereIn('sale_user_id', $managerUser)->where('status', 1)->sum('amount');
        $totalDirectOverrides = UserOverridesLock::whereIn('sale_user_id', $managerUser)->where('type', 'Direct')->where('status', 0)->sum('amount');
        $totalInDirectOverrides = UserOverridesLock::whereIn('sale_user_id', $managerUser)->where('type', 'Indirect')->where('status', 0)->sum('amount');
        $totalOfficeOverrides = UserOverridesLock::whereIn('sale_user_id', $managerUser)->where('type', 'Office')->where('status', 0)->sum('amount');
        $totalDueOverrides = $totalDirectOverrides + $totalInDirectOverrides + $totalOfficeOverrides;

        // result ......................
        $data['graph'] = $countArr;
        $data['earning_break_down'] = [
            'total_account' => isset($totalAccount) ? $totalAccount : 0,
            'commission' => $totalCommission,
            'overrides' => $totalOverrides,
            'other_item' => 0,
        ];
        $data['commission'] = [
            'total_account' => isset($totalAccount) ? $totalAccount : 0,
            'commission_earnings' => $totalCommission,
            'm1_paid' => $totalM1Paid,
            'm2_paid' => $totalM2Paid,
            'advances' => 0,
            'clawback_account' => $clawBackData.' ($'.$clawBackDataAmount.')',
            'total_due' => round($totalCommission + $totalM1Paid + $totalM2Paid + $clawBackDataAmount, 5),
        ];

        $data['overrides'] = [
            'total_earnings' => $totalEarnedOverrides,
            'direct' => $totalDirectOverrides,
            'indirect' => $totalInDirectOverrides,
            'offece' => $totalOfficeOverrides,
            'total_due' => $totalDueOverrides,
        ];
        $data['other_item'] = [
            'reimbursements' => isset($reimbursements) ? $reimbursements : 0,
            'incentives' => isset($incentive) ? $incentive : 0,
            'miscellaneous' => isset($miscellaneous) ? $miscellaneous : 0,
            'travel' => isset($travel) ? $travel : 0,
            'rent' => isset($rent) ? $rent : 0,
            'bonus' => isset($bonus) ? $bonus : 0,
            'total_due' => round($otherItem, 5),
        ];

        $data['deductions'] = [
            'rent' => $rentAmount,
            'sign_on_bonus' => 0,
            'travel' => $deductionTravel,
            'phone_bill' => $deductionPhoneBill,
            'total_due' => $totalDeduction,
        ];

        $data['payout_summary'] = [
            'commission' => [
                'total_value' => $totalCommission,
                'paid' => $totalM1Paid + $totalM2Paid,
                'held_back' => 0,
                'due_amount' => $totalCommission - ($totalM1Paid + $totalM2Paid),
            ],
            'overrides' => [
                'total_value' => $totalOverrides,
                'paid' => $totalEarnedOverrides,
                'held_back' => 0,
                'due_amount' => $totalDueOverrides,
            ],
            'other_item' => [
                'total_value' => round($otherItem, 5),
                'paid' => round($otherItemPaid, 5),
                'held_back' => 0,
                'due_amount' => round($commissionDue, 5),
            ],
            'deduction' => [
                'total_value' => round($totalDeduction, 5),
                'paid' => 0,
                'held_back' => 0,
                'due_amount' => round($totalDeduction, 5),
            ],
            'total_due' => round(($commissionDue) + $totalDeduction + $totalDueCommission, 5),
        ];

        return response()->json([
            'ApiName' => 'Reconciliation Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function reconciliation(Request $request)
    {

        $positionId = Auth()->user()->position_id;
        $office_id = $request->office_id;

        if (! empty($office_id)) {

            $managerUser = User::where('office_id', $office_id)
                ->whereIn('position_id', [2, 3])->pluck('id');
        } elseif ($positionId == 1) {
            $managerUser = User::where('manager_id', Auth()->user()->manager_id)->pluck('id');
        } else {
            $managerUser = User::where('id', Auth()->user()->id)->pluck('id');
        }

        $j = 0;
        $countArr = [];
        $var = date('Y');
        $startDate = date('Y-m-d', strtotime($request->start_date));
        $endDate = date('Y-m-d', strtotime($request->end_date));

        $startm = Carbon::parse($startDate)->month;
        $endm = Carbon::parse($endDate)->month;
        // Graph ......................
        $m1AmountTotal = 0;
        $m2AmountTotal = 0;
        $monthAmount = 0;
        $pid = DB::table('sale_master_process')->where('closer1_id', auth()->user()->id)->orWhere('closer2_id', auth()->user()->id)->orWhere('setter1_id', auth()->user()->id)->orWhere('setter2_id', auth()->user()->id)->pluck('pid');
        $month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = $startm; $i <= $endm; $i++) {
            // return Carbon::now();
            $finalMonth = $i;
            $startMonth = Carbon::now()->month($finalMonth)->day(1)->format($var.'-m-d');
            $endMonth = Carbon::now()->month($finalMonth)->endOfMonth()->format($var.'-m-d');
            // $monthAmount = SalesMaster::whereBetween('customer_signoff',[$startMonth,$endMonth])->sum('gross_account_value');
            $m1Amount = SalesMaster::select('pid', 'id', 'customer_signoff')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startMonth, $endMonth])->with('getMone')->get();
            $m2Amount = SalesMaster::select('pid', 'id', 'customer_signoff')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startMonth, $endMonth])->with('getMtwo')->get();

            foreach ($m1Amount as $m1Amounts) {
                if ($m1Amounts->getMone->closer1_m1 != 0 || $m1Amounts->getMone->closer2_m1 != 0 || $m1Amounts->getMone->setter1_m1 != 0 || $m1Amounts->getMone->setter2_m1 != 0) {
                    $m1AmountTotal += $m1Amounts->getMone->closer1_m1 + $m1Amounts->getMone->closer2_m1 + $m1Amounts->getMone->setter1_m1 + $m1Amounts->getMone->setter2_m1;
                } else {
                    $m1AmountTotal = 0;
                }
                if ($m1Amounts->getMone->closer1_m2 != 0 || $m1Amounts->getMone->closer2_m2 != 0 || $m1Amounts->getMone->setter1_m2 != 0 || $m1Amounts->getMone->setter2_m2 != 0) {
                    $m2AmountTotal += $m1Amounts->getMtwo->closer1_m2 + $m1Amounts->getMtwo->closer2_m2 + $m1Amounts->getMtwo->setter1_m2 + $m1Amounts->getMtwo->setter2_m2;
                } else {
                    $m2AmountTotal = 0;
                }
            }

            $monthAmount = $m1AmountTotal + $m2AmountTotal;

            $countArr[$month[$i - 1]]['total_earnings'] = round($monthAmount, 5);
            $countArr[$month[$i - 1]]['m1'] = round($m1AmountTotal, 5);
            $countArr[$month[$i - 1]]['m2'] = round($m2AmountTotal, 5);
            $m1AmountTotal = 0;
            $m2AmountTotal = 0;
        }

        $managerUser = User::where('manager_id', Auth()->user()->manager_id)->pluck('id');
        // Total Account ......................

        $pids = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->pluck('pid');

        $totalAccount = SaleMasterProcess::whereIn('pid', $pids)->whereIn('closer1_id', $managerUser)->orWhereIn('closer2_id', $managerUser)->whereIn('pid', $pids)->orWhereIn('setter1_id', $managerUser)->whereIn('pid', $pids)->orWhereIn('setter2_id', $managerUser)->whereIn('pid', $pids)->count();

        $totalOverrides = UserOverridesLock::whereIn('pid', $pids)->whereIn('sale_user_id', $managerUser)->sum('amount');
        // Total Commission ......................
        $commissionSum = SaleMasterProcess::selectRaw('sum(closer1_commission) as commission1,
                    sum(closer2_commission) as commission2,
                    sum(setter1_commission) as commission3,
                    sum(setter2_commission) as commission4')
            ->whereIn('closer1_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('closer2_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('setter1_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('setter2_id', $managerUser)
            ->whereIn('pid', $pids)
            ->firstOrFail();
        $totalCommission = $commissionSum->commission1 + $commissionSum->commission2 + $commissionSum->commission3 + $commissionSum->commission4;
        // Total M1_Paid ......................
        $m1Paid = SaleMasterProcess::selectRaw('sum(closer1_m1) as m1_paid1,
                    sum(closer2_m1) as m1_paid2,
                    sum(setter1_m1) as m1_paid3,
                    sum(setter2_m1) as m1_paid4')
            ->whereIn('closer1_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('closer2_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('setter1_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('setter2_id', $managerUser)
            ->whereIn('pid', $pids)
            ->firstOrFail();
        $totalM1Paid = round($m1Paid->m1_paid1 + $m1Paid->m1_paid2 + $m1Paid->m1_paid3 + $m1Paid->m1_paid4, 5);
        // Total M2_Paid ......................
        $m2Paid = SaleMasterProcess::selectRaw('sum(closer1_m2) as m2_paid1,
                    sum(closer2_m2) as m2_paid2,
                    sum(setter1_m2) as m2_paid3,
                    sum(setter2_m2) as m2_paid4')
            ->whereIn('closer1_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('closer2_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('setter1_id', $managerUser)
            ->whereIn('pid', $pids)
            ->orWhereIn('setter2_id', $managerUser)
            ->whereIn('pid', $pids)
            ->firstOrFail();

        $totalM2Paid = round($m2Paid->m2_paid1 + $m2Paid->m2_paid2 + $m2Paid->m2_paid3 + $m2Paid->m2_paid4, 5);
        $totalDueCommission = $totalCommission - ($totalM1Paid + $totalM2Paid);

        // $totalPid = SaleMasterProcess::whereIn('closer1_id',$managerUser)->whereIn('pid',$pids)->orWhereIn('closer2_id',$managerUser)->whereIn('pid',$pids)->orWhereIn('setter1_id',$managerUser)->whereIn('pid',$pids)->orWhereIn('setter2_id',$managerUser)->whereIn('pid',$pids)->dd();//->pluck('pid');
        $totalPid = SaleMasterProcess::whereIn('pid', $pids)->whereIn('closer1_id', $managerUser)->orWhereIn('closer2_id', $managerUser)->orWhereIn('setter1_id', $managerUser)->orWhereIn('setter2_id', $managerUser)->pluck('pid');

        // clawback .................

        // $clawBackData = SalesMaster::whereIn('pid',$totalPid)->where('date_cancelled','!=',null)->count();
        $clawBackData = ClawbackSettlementLock::whereIn('pid', $pid)->count();
        // $clawBackDataAmount = SalesMaster::whereIn('pid',$totalPid)->where('date_cancelled','!=',null)->sum('gross_account_value');
        $clawBackDataAmount = ClawbackSettlementLock::whereIn('pid', $pid)->sum('clawback_amount');
        // Other Item...................
        $reimbursements = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('adjustment_type_id', 2)->sum('amount');
        $incentive = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('adjustment_type_id', 6)->sum('amount');
        $bonus = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('adjustment_type_id', 3)->sum('amount');
        $miscellaneous = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('cost_tracking_id', 15)->sum('amount');
        $travel = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('cost_tracking_id', 1)->sum('amount');
        $rent = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('cost_tracking_id', 9)->sum('amount');

        // other item Paid................
        $reimbursementsPaid = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('adjustment_type_id', 2)->where('status', 1)->sum('amount');
        $incentivePaid = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('adjustment_type_id', 6)->where('status', 1)->sum('amount');
        $bonusPaid = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('adjustment_type_id', 3)->where('status', 1)->sum('amount');
        $miscellaneousPaid = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('cost_tracking_id', 15)->where('status', 1)->sum('amount');
        $travelPaid = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('cost_tracking_id', 1)->where('status', 1)->sum('amount');
        $rentPaid = ApprovalsAndRequestLock::whereIn('customer_pid', $totalPid)->where('cost_tracking_id', 9)->where('status', 1)->sum('amount');

        $otherItem = $reimbursements + $incentive + $miscellaneous + $travel + $rent + $bonus;
        $otherItemPaid = $reimbursementsPaid + $incentivePaid + $miscellaneousPaid + $travelPaid + $rentPaid + $bonusPaid;
        $commissionDue = $otherItem - $otherItemPaid;

        // Deduction
        $rent = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 9)->first();
        $rentType = isset($rent->deduction_type) ? $rent->deduction_type : '';
        $rentAmount = isset($rent->ammount_par_paycheck) ? $rent->ammount_par_paycheck : 0;
        $deductionRent = $rentType.' '.$rentAmount;

        $travelDeduction = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 1)->first();
        $travelType = isset($travelDeduction->deduction_type) ? $travelDeduction->deduction_type : '';
        $travelAmount = isset($travelDeduction->ammount_par_paycheck) ? $travelDeduction->ammount_par_paycheck : 0;
        $deductionTravel = $travelType.' '.$travelAmount;

        $phoneBill = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 20)->first();
        $phoneBillType = isset($phoneBill->deduction_type) ? $phoneBill->deduction_type : '';
        $phoneBillAmount = isset($phoneBill->ammount_par_paycheck) ? $phoneBill->ammount_par_paycheck : 0;
        $deductionPhoneBill = $phoneBillType.' '.$phoneBillAmount;

        // Deduction Paid...........................

        $rentPaid = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 9)->where('deduction_setting_id', 1)->first();
        $rentPaidAmount = isset($rentPaid->ammount_par_paycheck) ? $rentPaid->ammount_par_paycheck : 0;

        $travelDeductionPaid = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 1)->where('deduction_setting_id', 1)->first();
        $travelPaidAmount = isset($travelDeductionPaid->ammount_par_paycheck) ? $travelDeductionPaid->ammount_par_paycheck : 0;

        $phoneBillPaid = PositionCommissionDeduction::where('position_id', Auth()->user()->position_id)->where('cost_center_id', 20)->where('deduction_setting_id', 1)->first();
        $phoneBillAmountPaid = isset($phoneBillPaid->ammount_par_paycheck) ? $phoneBillPaid->ammount_par_paycheck : 0;

        $totalDeduction = $rentPaidAmount + $travelPaidAmount + $phoneBillAmountPaid;

        // total overrides .....................

        $totalEarnedOverrides = UserOverridesLock::whereIn('pid', $pids)->whereIn('sale_user_id', $managerUser)->where('status', 1)->sum('amount');
        $totalDirectOverrides = UserOverridesLock::whereIn('pid', $pids)->whereIn('sale_user_id', $managerUser)->where('type', 'Direct')->where('status', 0)->sum('amount');
        $totalInDirectOverrides = UserOverridesLock::whereIn('pid', $pids)->whereIn('sale_user_id', $managerUser)->where('type', 'Indirect')->where('status', 0)->sum('amount');
        $totalOfficeOverrides = UserOverridesLock::whereIn('pid', $pids)->whereIn('sale_user_id', $managerUser)->where('type', 'Office')->where('status', 0)->sum('amount');
        $totalDueOverrides = $totalDirectOverrides + $totalInDirectOverrides + $totalOfficeOverrides;
        // result ......................
        $data['graph'] = $countArr;
        $data['earning_break_down'] = [
            'total_account' => isset($totalAccount) ? $totalAccount : 0,
            'commission' => $totalCommission,
            'overrides' => $totalOverrides,
            'other_item' => round($otherItem, 5),
        ];
        $data['commission'] = [
            'total_account' => isset($totalAccount) ? $totalAccount : 0,
            'commission_earnings' => $totalCommission,
            'm1_paid' => $totalM1Paid,
            'm2_paid' => $totalM2Paid,
            'advances' => 0,
            'clawback_account' => $clawBackData.' ($'.$clawBackDataAmount.')',
            'total_due' => round($totalCommission + $totalM1Paid + $totalM2Paid + $clawBackDataAmount, 5),
        ];

        $data['overrides'] = [
            'total_earnings' => $totalEarnedOverrides,
            'direct' => $totalDirectOverrides,
            'indirect' => $totalInDirectOverrides,
            'offece' => $totalOfficeOverrides,
            'total_due' => $totalDueOverrides,
        ];
        $data['other_item'] = [
            'reimbursements' => isset($reimbursements) ? $reimbursements : 0,
            'incentives' => isset($incentive) ? $incentive : 0,
            'miscellaneous' => isset($miscellaneous) ? $miscellaneous : 0,
            'travel' => isset($travel) ? $travel : 0,
            'rent' => isset($rent) ? $rent : 0,
            'bonus' => isset($bonus) ? $bonus : 0,
            'total_due' => round($otherItem, 5),
        ];

        $data['deductions'] = [
            'rent' => $rentAmount,
            'sign_on_bonus' => 0,
            'travel' => $deductionTravel,
            'phone_bill' => $deductionPhoneBill,
            'total_due' => $totalDeduction,
        ];

        $data['payout_summary'] = [
            'commission' => [
                'total_value' => $totalCommission,
                'paid' => $totalM1Paid + $totalM2Paid,
                'held_back' => 0,
                'due_amount' => $totalCommission - ($totalM1Paid + $totalM2Paid),
            ],
            'overrides' => [
                'total_value' => $totalOverrides,
                'paid' => $totalEarnedOverrides,
                'held_back' => 0,
                'due_amount' => $totalDueOverrides,
            ],
            'other_item' => [
                'total_value' => round($otherItem, 5),
                'paid' => round($otherItemPaid, 5),
                'held_back' => 0,
                'due_amount' => round($commissionDue, 5),
            ],
            'deduction' => [
                'total_value' => round($totalDeduction, 5),
                'paid' => 0,
                'held_back' => 0,
                'due_amount' => round($totalDeduction, 5),
            ],
            'total_due' => round(($commissionDue) + $totalDeduction + $totalDueCommission, 5),
        ];

        $exportdata = $data['payout_summary'];

        if (isset($request->is_export) && ($request->is_export == 1)) {
            $file_name = 'manager_reconciliation_export_'.date('Y_m_d_H_i_s').'.csv';

            return Excel::download(new ExportReportReconciliationStandard($exportdata), $file_name);
        }

        return response()->json([
            'ApiName' => 'Reconciliation Api',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function repotSalesList(Request $request)
    {
        // return $request;
        $installer = $request->input('installer_filter');
        $status = $request->input('status_filter');
        $date = $request->input('date_filter');
        $perpages = $request->input('perpage');
        if (! empty($perpages)) {
            $perpage = $request->input('perpage');
        } else {
            $perpage = 10;
        }
        $result = [];
        $position = auth()->user()->position_id;

        $filterDataDateWise = $request->input('filter');
        if ($request->has('filter') && ! empty($request->input('filter')) && $request->input('office_id') == 'all') {
            $filterDataDateWise = $request->input('filter');
            $stateIds = AdditionalLocations::where('user_id', auth()->user()->id)->pluck('state_id')->toArray();
            array_push($stateIds, auth()->user()->state_id);
            $managerState = State::whereIn('id', $stateIds)->pluck('state_code')->toArray();
            // $userId=User::where('office_id',auth()->user()->office_id)->pluck('id');
            if ($request->input('user_id') != '') {
                $userId = User::where('id', $request->input('user_id'))->pluck('id');
            } else {
                $userId = User::pluck('id');
            }
            $pid = DB::table('sale_master_process')->whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid')->toArray();
            if ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate = date('Y-m-d', strtotime(now()));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'this_month') {
                $month = \Carbon\Carbon::now()->daysInMonth;
                $startOfLastWeek = Carbon::now()->subDays($month);
                $endOfLastWeek = Carbon::now();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'this_quarter') {

                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
                $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
                $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('id', 'desc');
            }
        } elseif ($request->has('filter') && ! empty($request->input('filter')) && $request->input('office_id') != 'all') {
            $officeId = $request->office_id;
            // $userId=User::where('office_id',auth()->user()->office_id)->pluck('id');
            if ($request->input('user_id') != '') {
                $userId = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
            }
            $pid = DB::table('sale_master_process')->whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid')->toArray();

            if ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate = date('Y-m-d', strtotime(now()));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'this_month') {
                $month = \Carbon\Carbon::now()->daysInMonth;
                $startOfLastWeek = Carbon::now()->subDays($month);
                $endOfLastWeek = Carbon::now();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'this_quarter') {

                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));

                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_quarter') {

                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                // $startDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(6)->addDays(30)->startOfMonth()));
                // $endDate =  date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(0)->endOfMonth()));
                $date = new \Carbon\Carbon('-3 months'); // for the last quarter requirement
                $startDate = date('Y-m-d', strtotime($date->startOfQuarter())); // the actual start of quarter method
                $endDate = date('Y-m-d', strtotime($date->endOfQuarter()));

                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDay()));
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->orderBy('id', 'desc');
            }
        } else {
            $result = SalesMaster::with('salesMasterProcess', 'userDetail')->orderBy('id', 'desc');
        }

        if ($request->has('order_by') && ! empty($request->input('order_by'))) {
            $orderBy = $request->input('order_by');
        } else {
            $orderBy = 'desc';
        }

        if ($request->has('search') && ! empty($request->input('search'))) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_name', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('date_cancelled', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('pid', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('customer_state', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('net_epc', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('job_status', 'LIKE', '%'.$request->input('search').'%')
                    ->orWhere('kw', 'LIKE', '%'.$request->input('search').'%');
            });
        }
        if ($request->has('closed') && ! empty($request->input('closed'))) {
            $result->where(function ($query) {
                return $query->where('m2_date', '!=', null);
            });
        }

        if ($request->has('m1') && ! empty($request->input('m1'))) {
            $result->where(function ($query) {
                return $query->where('m1_date', '!=', null);
            });
        }

        if ($request->has('m2') && ! empty($request->input('m2'))) {
            $result->where(function ($query) {
                return $query->where('m2_date', '!=', null);
            });
        }

        if ($installer && $installer != '') {
            $result->where(function ($query) use ($installer) {
                return $query->where('install_partner', 'LIKE', '%'.$installer.'%');
            });
        }

        if ($status && $status != '') {
            $result->whereHas(
                'salesMasterProcess',
                function ($query) use ($status) {
                    return $query->where('pid_status', $status);
                }
            );
        }

        if ($date == 'm1_date') {
            $result->where(function ($query) {
                return $query->where('m1_date', '!=', null)
                    ->where('m2_date', '=', null)
                    ->where('date_cancelled', '=', null);
            });
        }

        if ($date == 'm2_date') {
            $result->where(function ($query) {
                return $query->where('m2_date', '!=', null)
                    ->where('m1_date', '=', null)
                    ->where('date_cancelled', '=', null);
            });
        }

        if ($date == 'm1_date_m2_date') {
            $result->where(function ($query) {
                return $query->where('m1_date', '!=', null)
                    ->where('m2_date', '!=', null)
                    ->where('date_cancelled', '=', null);
            });
        }

        if ($date == 'cancel_date') {
            $result->where(function ($query) {
                return $query->where('date_cancelled', '!=', null);
            });
        }
        if ($request->has('filter_product') && ! empty($request->input('filter_product'))) {
            $product = $request->filter_product;
            $result->where(function ($query) use ($product) {
                return $query->where('product', $product);
            });
        }
        if ($request->has('filter_status') && ! empty($request->input('filter_status'))) {
            $status = '%'.$request->input('filter_status').'%';
            $result->where(function ($query) use ($status) {
                return $query->where('job_status', 'LIKE', $status);
            });
        }
        if ($request->has('filter_install') && ! empty($request->input('filter_install'))) {
            $filter_install = '%'.$request->input('filter_install').'%';
            $result->where(function ($query) use ($filter_install) {
                return $query->where('install_partner', 'LIKE', $filter_install);
            });
        }
        if ($request->has('location') && ! empty($request->input('location')) && 1 == 2) {
            $result->where(function ($query) use ($request) {
                return $query->where('customer_state', '=', $request->location);
            });
        }

        if ($date == 'm1_paid') {
            $result->whereHas('commission', function ($q) {
                $q->where(['amount_type' => 'm1', 'status' => '3']);
            });
        }

        if ($date == 'm2_paid') {
            $result->whereHas('commission', function ($q) {
                $q->where(['amount_type' => 'm2', 'status' => '3']);
            });
        }

        // $data = $result->orderBy('id',$orderBy)->paginate(config('app.paginate', 15));
        $data = $result->orderBy('customer_signoff', 'DESC');
        if (isset($request->is_export) && ($request->is_export == 1)) {
            $data = $data->get();
        } else {
            $data = $data->paginate($perpage);
        }
        $data->transform(function ($data) {
            $approveDate = $data->customer_signoff;
            $m1_date = $data->m1_date;
            $m2_date = $data->m2_date;
            $closer1 = isset($data->salesMasterProcess->closer1_id) ? $data->salesMasterProcess->closer1_id : null;
            $setter1 = isset($data->salesMasterProcess->setter1_id) ? $data->salesMasterProcess->setter1_id : null;
            $closer1_m1 = isset($data->salesMasterProcess->closer1_m1) ? $data->salesMasterProcess->closer1_m1 : null;
            $setter1_m1 = isset($data->salesMasterProcess->setter1_m1) ? $data->salesMasterProcess->setter1_m1 : null;
            $closer1_m2 = isset($data->salesMasterProcess->closer1_m2) ? $data->salesMasterProcess->closer1_m2 : null;
            $setter1_m2 = isset($data->salesMasterProcess->setter1_m2) ? $data->salesMasterProcess->setter1_m2 : null;

            $closer2_m1 = isset($data->salesMasterProcess->closer1_m1) ? $data->salesMasterProcess->closer1_m1 : null;
            $setter2_m1 = isset($data->salesMasterProcess->setter1_m1) ? $data->salesMasterProcess->setter1_m1 : null;
            $closer2_m2 = isset($data->salesMasterProcess->closer1_m2) ? $data->salesMasterProcess->closer1_m2 : null;
            $setter2_m2 = isset($data->salesMasterProcess->setter1_m2) ? $data->salesMasterProcess->setter1_m2 : null;
            $pid_status = isset($data->salesMasterProcess->pid_status) ? $data->salesMasterProcess->pid_status : null;

            $total_m2 = (((int) $closer1_m2) + ((int) $setter1_m2) + ((int) $closer2_m2) + ((int) $setter2_m2));
            // echo $total_m2;die;
            $total_m1 = ((int) $closer1_m1 + (int) $setter1_m1 + (int) $closer2_m1 + (int) $setter2_m1);
            $progress_bar = '25%';

            if ($approveDate != null) {
                $progress_bar = '33%';
            }
            if ($closer1 != null) {
                $progress_bar = '42%';
            }
            if ($setter1 != null) {
                $progress_bar = '50%';
            }
            if ($m1_date != null) {
                $progress_bar = '60%';
            }
            if ($closer1_m1 != null || $setter1_m1 != null) {
                $progress_bar = '65%';
            }
            if ($m2_date != null) {
                $progress_bar = '70%';
            }
            if ($closer1_m2 != null || $setter1_m2 != null) {
                $progress_bar = '85%';
            }
            if ($pid_status != null) {
                $progress_bar = '100%';
            }

            $total_amount = $data->total_in_period;
            $amount = isset($data->userDetail->upfront_pay_amount) ? $data->userDetail->upfront_pay_amount : 0;
            $commission = isset($data->userDetail->commission) ? $data->userDetail->commission : 0;

            // $CrmData = Crms::where('id',2)->where('status',1)->first();
            // if($CrmData){
            //     $dealerFeePer = isset($data->dealer_fee_percentage)? ($data->dealer_fee_percentage):null;
            // }else{
            //     $dealerFeePer = isset($data->dealer_fee_percentage)? ($data->dealer_fee_percentage * 100):null;
            // }

            // if (config('app.domain_name') == 'aveyo'  || config('app.domain_name') == 'aveyo2'){
            $dealerFeePer = isset($data->dealer_fee_percentage) ? ($data->dealer_fee_percentage) : null;
            if ($dealerFeePer < 1) {
                $dealerFeePer = (float) $dealerFeePer * 100;
            }
            // }
            // else{
            //     $dealerFeePer = isset($data->dealer_fee_percentage)? ($data->dealer_fee_percentage * 100):null;
            // }

            // $totalkwm1 = null;
            // $totalkwm2 = null;
            // if (!empty($data->kw)) {
            // 	return $totalkwm1 = '$'.($data->kw * $amount);
            //     $totalkwm2 = '$'.($total_amount - ($total_amount * $commission / 100) - ($data->kw * $amount));
            // }
            $location_data = Locations::with('State')->where('general_code', '=', $data->customer_state)->first();
            if ($location_data) {
                $state_code = $location_data->state->state_code;
            } else {
                $state_code = null;
            }
            if (isset($data->salesMasterProcess->closer1Detail->first_name)) {
                $closerName = $data->salesMasterProcess->closer1Detail->first_name.' '.$data->salesMasterProcess->closer1Detail->last_name;
            }
            if (isset($data->salesMasterProcess->setter1Detail->first_name)) {
                $setterName = $data->salesMasterProcess->setter1Detail->first_name.' '.$data->salesMasterProcess->setter1Detail->last_name;
            }
            $all_milestone = [];
            for ($i = 1; $i < 5; $i++) {
                $milestone['name'] = 'Payment '.$i.'(M'.$i.')';
                // $milestone['value'] = $i.'00';
                $milestone['date'] = date('d/m/Y');
                $all_milestone[] = $milestone;
            }

            return [
                'id' => $data->id,
                'pid' => $data->pid,
                'job_status' => $data->job_status,
                'installer' => $data->install_partner,
                'customer_name' => isset($data->customer_name) ? $data->customer_name : null,
                'customer_address' => isset($data->customer_address) ? $data->customer_address : null,
                'customer_city' => isset($data->customer_city) ? $data->customer_city : null,
                'state_id' => $state_code,
                'state' => isset($data->customer_state) ? $data->customer_state : null,
                'customer_zip' => isset($data->customer_zip) ? $data->customer_zip : null,
                'customer_email' => isset($data->customer_email) ? $data->customer_email : null,
                'customer_phone' => isset($data->customer_phone) ? $data->customer_phone : null,
                'closer_id' => isset($data->salesMasterProcess->closer1Detail->id) ? $data->salesMasterProcess->closer1Detail->id : null,
                'closer' => isset($closerName) ? $closerName : null,
                'setter_id' => isset($data->salesMasterProcess->setter1Detail->id) ? $data->salesMasterProcess->setter1Detail->id : null,
                'setter' => isset($setterName) ? $setterName : null,
                'epc' => isset($data->epc) ? $data->epc : null,
                'net_epc' => isset($data->net_epc) ? $data->net_epc : null,
                'kw' => isset($data->kw) ? round($data->kw, 5) : null,
                'status' => isset($data->salesMasterProcess->pid_status) ? $data->salesMasterProcess->pid_status : null,
                'date_cancelled' => isset($data->date_cancelled) ? dateToYMD($data->date_cancelled) : null,
                'm1_date' => isset($data->m1_date) ? dateToYMD($data->m1_date) : null,
                // 'm1_amount' => isset($data->m1_amount)?$data->m1_amount:'',
                'm1_amount' => $total_m1,
                'm2_date' => isset($data->m2_date) ? dateToYMD($data->m2_date) : null,
                // 'm2_amount' => isset($data->m2_amount)?$data->m2_amount:'',
                'm2_amount' => $total_m2,
                'adders' => isset($data->adders) ? $data->adders : '',
                'progress_bar' => isset($progress_bar) ? $progress_bar : 0,
                'dealer_fee' => (isset($data->dealer_fee_amount) && is_int($data->dealer_fee_amount)) ? round($data->dealer_fee_amount, 5) : '',
                'dealer_fee_percentage' => round($dealerFeePer, 5),
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
                'gross_account_value' => $data->gross_account_value,
                'product' => isset($data->productdata) ? $data->productdata->name : '',
                'product_code' => isset($data->productdata) ? $data->productdata->product_id : '',
                'last_milestone' => ['name' => 'M1', 'date' => date('d/m/Y')],
                'all_milestone' => $all_milestone,
            ];
        });

        $exportdata = $data;
        if (isset($request->is_export) && ($request->is_export == 1)) {
            $file_name = 'sales_customer_list_export_'.date('Y_m_d_H_i_s').'.xlsx';
            Excel::store(new ExportReportSales($exportdata), 'exports/sales/'.$file_name, 'public', \Maatwebsite\Excel\Excel::XLSX);

            // Get the URL for the stored file
            // Return the URL in the API response
            $url = getStoragePath('exports/sales/'.$file_name);
            // $url = getExportBaseUrl().'storage/exports/sales/' . $file_name;

            return response()->json(['url' => $url]);

            return Excel::download(new ExportReportSales($exportdata), $file_name);
        }

        if (count($data) > 0) {

            return response()->json([
                'ApiName' => 'sales_customer_list',
                'status' => true,
                'message' => 'Successfully.',
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'sales_customer_list',
                'status' => false,
                'message' => 'data not found',
                'data' => $data,
            ], 200);
        }
    }

    public function mySalesGraph(Request $request): JsonResponse
    {
        $data = [];
        $date = date('Y-m-d');
        $kwType = isset($request->kw_type) ? $request->kw_type : 'sold';
        $mdates = getdates();
        if ($kwType == '' || $kwType == 'sold') {
            $dateColumn = 'customer_signoff';
        } else {
            $dateColumn = 'm2_date';
        }

        $companyProfile = CompanyProfile::first();
        if ($request->has('filter') && ! empty($request->input('filter')) && $request->input('office_id') == 'all') {
            $filterDataDateWise = $request->input('filter');
            $stateIds = AdditionalLocations::where('user_id', auth()->user()->id)->pluck('state_id')->toArray();
            array_push($stateIds, auth()->user()->state_id);
            $officeId = auth()->user()->office_id;

            if ($request->input('user_id') != '') {
                $userId = $request->input('user_id');
                $pid = DB::table('sale_master_process')->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId)->pluck('pid');
                $clawPid = ClawbackSettlement::where('user_id', $userId)->pluck('pid');
            } else {
                $userId = User::pluck('id');
                $pid = DB::table('sale_master_process')->pluck('pid')->toArray();
                $clawPid = ClawbackSettlement::pluck('pid');
            }

            if ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);

                for ($i = 1; $i <= $currentDate->dayOfWeek; $i++) {
                    $now = Carbon::now();
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                    $totalKw = 0;
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => date('m/d/Y', strtotime($startDate.' + '.$i.' days')),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                for ($i = 0; $i < 7; $i++) {
                    $currentDate = \Carbon\Carbon::now();
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                    $totalKw = 0;
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => date('m/d/Y', strtotime($startDate.' + '.$i.' days')),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'this_month') {
                $endOfLastWeek = Carbon::now();
                $day = date('d', strtotime($endOfLastWeek));

                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                for ($i = 0; $i <= $dateDays; $i++) {
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                    $totalKw = 0;
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where('date_cancelled', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where('date_cancelled', null)->where($dateColumn, $weekDate)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => date('m/d/Y', strtotime($startDate.' + '.$i.' days')),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = Carbon::now()->startOfMonth()->subMonth()->toDateString();
                $endDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                for ($i = 0; $i < $month; $i++) {
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));
                    $totalKw = 0;
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => date('m/d/Y', strtotime($startDate.' + '.$i.' days')),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-0 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm/d/y';

                $weekCount = round($dateDays / 7);
                $totalWeekDay = 7 * $weekCount;
                $extraDay = $dateDays - $totalWeekDay;

                if ($extraDay > 0) {
                    $weekCount = $weekCount + 1;
                }
                for ($i = 0; $i < $weekCount; $i++) {
                    $currentDate = \Carbon\Carbon::now();
                    $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));
                    $dayWeek = 7 * $i;
                    if ($i == 0) {
                        $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                        $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                    } else {

                        $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                        $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                    }
                    if ($i == $weekCount - 1) {
                        $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                        $eDate = $endDate;
                    }

                    $date->addDay();
                    $totalKw = 0;
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $time = strtotime($sDate);
                    $total[] = [
                        'date' => date('m/d/Y', strtotime($sDate)).' to '.date('m/d/Y', strtotime($eDate)),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y/m/d', strtotime('-5 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm/d/y';
                $weekCount = round($dateDays / 7);
                $totalWeekDay = 7 * $weekCount;
                $extraDay = $dateDays - $totalWeekDay;

                if ($extraDay > 0) {
                    $weekCount = $weekCount + 1;
                }
                for ($i = 0; $i < $weekCount; $i++) {
                    // loop to end of the week while not crossing the last date of month
                    $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                    $dayWeek = 7 * $i;
                    if ($i == 0) {
                        $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                        $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                    } else {

                        $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                        $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                    }
                    if ($i == $weekCount - 1) {
                        $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                        $eDate = $endDate;
                    }

                    $date->addDay();

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);

                    $total[] = [
                        'date' => date('m/d/Y', strtotime($sDate)).' to '.date('m/d/Y', strtotime($eDate)),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $largestSystem = SalesMaster::select('pid', 'customer_signoff', 'kw')->whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->orderBy('kw', 'DESC')->get();
                $largestSystemSize = $largestSystem->max('kw');

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);

                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-t', strtotime('+'.$i.' months', strtotime($startDate))); // change by divya

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $currentMonth = date('m');

                    if ($i < $currentMonth) {
                        $total[] = [
                            'date' => $month,
                            // 'm1_account' => round($accountM1->account, 5),
                            // 'm2_account' => round($accountM2->account, 5),
                            'claw_back' => round($clawBack->account, 5),
                            'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                            'total_kw' => round($totalKw, 5),
                        ];
                        foreach ($mdates as $date) {
                            $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                        }
                    }
                }
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $total[] = [
                        'date' => $month,
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDateObj = Carbon::now()->subMonths(12)->startOfMonth();
                $startDate = $startDateObj->format('Y-m-d');
                $endDateObj = Carbon::now();
                $endDate = $endDateObj->format('Y-m-d');
                $currentDate = $startDateObj->copy();

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                    } else {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                    }
                    $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                    $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                    $clawBackAccountCount = count($clawBackPid);
                    $dateDays = $dateDays + 1;
                    if ($dateDays <= 15) {
                        for ($i = 0; $i < $dateDays; $i++) {
                            $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where($dateColumn, $weekDate)->where('m1_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where($dateColumn, $weekDate)->where('m2_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', $weekDate)->where($dateColumn, '!=', null)->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where($dateColumn, $weekDate)->where('m1_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where($dateColumn, $weekDate)->where('m2_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', $weekDate)->where($dateColumn, '!=', null)->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            // - -----------------
                            $total[] = [
                                'date' => date('m-d-Y', strtotime($startDate.' + '.$i.' days')),
                                // 'm1_account' => round($accountM1->account, 5),
                                // 'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }
                        }
                    } else {
                        // $weekCount = round($dateDays / 7);
                        // $totalWeekDay = 7 * $weekCount;
                        // $extraDay = $dateDays - $totalWeekDay;

                        // if ($extraDay > 0) {
                        //     $weekCount = $weekCount + 1;
                        // }

                        // for ($i = 0; $i < $weekCount; $i++) {
                        while ($currentDate <= $endDateObj) {

                            $month = $currentDate->format('F');
                            $sDate = $currentDate->copy()->startOfMonth()->format('Y-m-d');
                            $eDate = $currentDate->copy()->endOfMonth()->format('Y-m-d');
                            if ($eDate > $endDate) {
                                $eDate = $endDate;
                            }
                            $currentDate->addMonth();
                            // $endsDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));

                            // $dayWeek = 7 * $i;
                            // if ($i == 0) {
                            //     $sDate = date('Y-m-d', strtotime($startDate . ' - ' . $dayWeek . ' days'));
                            //     $eDate = date('Y-m-d', strtotime($endsDate . ' - ' . 0 . ' days'));
                            // } else {

                            //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                            //     $eDate = date('Y-m-d', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                            // }
                            // if ($i == $weekCount - 1) {
                            //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                            //     $eDate = $endDate;
                            // }

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m1_date', '!=', null)->where('m2_date', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->where('m2_date', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            // - -----------------
                            $time = strtotime($sDate);
                            // $month = date("M", $time);
                            $total[] = [
                                'date' => $month, // date('m/d/Y', strtotime($sDate)) . ' to ' . date('m/d/Y', strtotime($eDate)),
                                // //'m1_account' => round($accountM1->account, 5),
                                // //'m2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }

                            $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->orderBy('m1_date', 'desc')
                                ->sum('m1_amount');

                            $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->orderBy('m1_date', 'desc')
                                ->sum('m2_amount');

                            $amount[] = [
                                'date' => $month, // $sDate . ' to ' . $eDate,
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }

                        // for ($i = 0; $i < $weekCount; $i++) {
                        //     $endsDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));

                        //     $dayWeek = 7 * $i;
                        //     if ($i == 0) {
                        //         $sDate = date('Y-m-d', strtotime($startDate . ' - ' . $dayWeek . ' days'));
                        //         $eDate = date('Y-m-d', strtotime($endsDate . ' - ' . 0 . ' days'));
                        //     } else {

                        //         $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //         $eDate = date('Y-m-d', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                        //     }
                        //     if ($i == $weekCount - 1) {
                        //         $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //         $eDate = $endDate;
                        //     }

                        //     $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $pid)
                        //         ->orderBy('m1_date', 'desc')
                        //         ->sum('m1_amount');

                        //     $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $pid)
                        //         ->orderBy('m1_date', 'desc')
                        //         ->sum('m2_amount');

                        //     $amount[] = [
                        //         'date' => $sDate . ' to ' . $eDate,
                        //         'm1_amount' => $amountM1,
                        //         'm2_amount' => $amountM2
                        //     ];
                        // }
                    }
                }
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                    } else {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                    }
                    $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                    $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                    $clawBackAccountCount = count($clawBackPid);
                    $dateDays = $dateDays + 1;
                    if ($dateDays <= 15) {
                        for ($i = 0; $i < $dateDays; $i++) {
                            $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where($dateColumn, $weekDate)->where('m1_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where($dateColumn, $weekDate)->where('m2_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', $weekDate)->where($dateColumn, '!=', null)->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where($dateColumn, $weekDate)->where('m1_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where($dateColumn, $weekDate)->where('m2_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', $weekDate)->where($dateColumn, '!=', null)->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            // - -----------------
                            $total[] = [
                                'date' => date('m-d-Y', strtotime($startDate.' + '.$i.' days')),
                                // 'm1_account' => round($accountM1->account, 5),
                                // 'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }
                        }
                    } else {
                        $weekCount = round($dateDays / 7);
                        $totalWeekDay = 7 * $weekCount;
                        $extraDay = $dateDays - $totalWeekDay;

                        if ($extraDay > 0) {
                            $weekCount = $weekCount + 1;
                        }

                        for ($i = 0; $i < $weekCount; $i++) {
                            $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                            $dayWeek = 7 * $i;
                            if ($i == 0) {
                                $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                            } else {

                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                            }
                            if ($i == $weekCount - 1) {
                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = $endDate;
                            }

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m1_date', '!=', null)->where('m2_date', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->where('m2_date', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->where('date_cancelled', null)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            // - -----------------
                            $time = strtotime($sDate);
                            $month = date('M', $time);
                            $total[] = [
                                'date' => date('m/d/Y', strtotime($sDate)).' to '.date('m/d/Y', strtotime($eDate)),
                                // 'm1_account' => round($accountM1->account, 5),
                                // 'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }
                        }

                        for ($i = 0; $i < $weekCount; $i++) {
                            $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                            $dayWeek = 7 * $i;
                            if ($i == 0) {
                                $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                            } else {

                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                            }
                            if ($i == $weekCount - 1) {
                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = $endDate;
                            }

                            $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->orderBy('m1_date', 'desc')
                                ->sum('m1_amount');

                            $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->whereIn('pid', $pid)
                                ->orderBy('m1_date', 'desc')
                                ->sum('m2_amount');

                            $amount[] = [
                                'date' => $sDate.' to '.$eDate,
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    }
                }
            }
        } elseif ($request->has('filter') && ! empty($request->input('filter')) && $request->input('office_id') != 'all') {
            $filterDataDateWise = $request->input('filter');
            $officeId = $request->input('office_id');
            if ($request->input('user_id') != '') {
                $userId = $request->input('user_id');
                $pid = DB::table('sale_master_process')->where('closer1_id', $userId)->orWhere('closer2_id', $userId)->orWhere('setter1_id', $userId)->orWhere('setter2_id', $userId)->pluck('pid');
                $clawPid = ClawbackSettlement::where('user_id', $userId)->pluck('pid');
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = DB::table('sale_master_process')->whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIN('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid')->toArray();
                $clawPid = ClawbackSettlement::whereIn('user_id', $userId)->pluck('pid');
            }

            if ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                for ($i = 1; $i <= $currentDate->dayOfWeek; $i++) {
                    $now = Carbon::now();
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')->whereIn('pid', $clawBackPid)
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')->whereIn('pid', $clawBackPid)
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => date('m-d-Y', strtotime($startDate.' + '.$i.' days')),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                for ($i = 0; $i < 7; $i++) {
                    $currentDate = \Carbon\Carbon::now();
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                    $weekDate = date('Y-m-d', strtotime($startOfLastWeek));

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => $weekDate,
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'this_month') {
                $endOfLastWeek = Carbon::now();
                $day = date('d', strtotime($endOfLastWeek));
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                for ($i = 0; $i <= $dateDays; $i++) {
                    $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => date('m-d-Y', strtotime($startDate.' + '.$i.' days')),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = Carbon::now()->startOfMonth()->subMonth()->toDateString();
                $endDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                for ($i = 0; $i < $month; $i++) {
                    $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays($i)));

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->where($dateColumn, $weekDate)->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->where($dateColumn, $weekDate)->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $total[] = [
                        'date' => date('m-d-Y', strtotime(Carbon::now()->subMonths(2)->addDays($i))),
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm-d-y';
                for ($i = 0; $i < $weeks; $i++) {
                    $currentDate = \Carbon\Carbon::now();
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $startDate = $date->copy();
                    // loop to end of the week while not crossing the last date of month
                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    if ($date->format($f) < $eom->format($f)) {
                        $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    } else {
                        $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    }
                    $date->addDay();

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $time = strtotime($sDate);
                    // $month=$sDate.'/'.$eDate;
                    $weekDate = $dates['w'.$i];
                    $total[] = [
                        'date' => $weekDate,
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y/m/d', strtotime('-5 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm-d-y';
                for ($i = 0; $i < $weeks; $i++) {
                    $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6 - $i)->addDays(30)->startOfMonth()));
                    $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5 - $i)->addDays(30)->startOfMonth()));
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $startDate = $date->copy();
                    // loop to end of the week while not crossing the last date of month
                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    if ($date->format($f) < $eom->format($f)) {
                        $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    } else {
                        $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    }
                    $date->addDay();

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $weekDate = $dates['w'.$i];

                    $total[] = [
                        'date' => $weekDate,
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                $currentYearMonth = date('m');
                for ($i = 0; $i < $currentYearMonth; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-t', strtotime('+'.$i.' months', strtotime($startDate))); // change by divya

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $currentMonth = date('m');

                    $total[] = [
                        'date' => $month,
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                } else {
                    $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                    $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                    $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                    $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                    $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                    $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                }
                $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                $clawBackAccountCount = count($clawBackPid);
                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                    } else {
                        $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->where('date_cancelled', null)->whereIn('pid', $pid)
                            ->first();

                        $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                            ->where('date_cancelled', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $clawBackPid)
                            ->first();
                        $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $total[] = [
                        'date' => $month,
                        // 'm1_account' => round($accountM1->account, 5),
                        // 'm2_account' => round($accountM2->account, 5),
                        'claw_back' => round($clawBack->account, 5),
                        'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                        'total_kw' => round($totalKw, 5),
                    ];
                    foreach ($mdates as $date) {
                        $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                    }
                }
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDateObj = Carbon::now()->subMonths(12)->startOfMonth();
                $startDate = $startDateObj->format('Y-m-d');
                $endDateObj = Carbon::now();
                $endDate = $endDateObj->format('Y-m-d');
                $currentDate = $startDateObj->copy();
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                    } else {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                    }
                    $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                    $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                    $clawBackAccountCount = count($clawBackPid);
                    if ($dateDays <= 15) {
                        for ($i = 0; $i < $dateDays; $i++) {
                            $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m1_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m2_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', $weekDate)->whereIn('pid', $clawBackPid)->where($dateColumn, '!=', null)
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', $weekDate)->whereIn('pid', $clawBackPid)->where($dateColumn, '!=', null)
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            $total[] = [
                                'date' => date('m-d-Y', strtotime($startDate.' + '.$i.' days')),
                                // 'm1_account' => round($accountM1->account, 5),
                                // 'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }
                        }
                    } else {
                        // $weekCount = round($dateDays / 7);
                        // $totalWeekDay = 7 * $weekCount;
                        // $extraDay = $dateDays - $totalWeekDay;

                        // if ($extraDay > 0) {
                        //     $weekCount = $weekCount + 1;
                        // }

                        // for ($i = 0; $i < $weekCount; $i++) {
                        while ($currentDate <= $endDateObj) {

                            $month = $currentDate->format('F');
                            $sDate = $currentDate->copy()->startOfMonth()->format('Y-m-d');
                            $eDate = $currentDate->copy()->endOfMonth()->format('Y-m-d');
                            if ($eDate > $endDate) {
                                $eDate = $endDate;
                            }
                            $currentDate->addMonth();
                            // $endsDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));

                            // $dayWeek = 7 * $i;
                            // if ($i == 0) {
                            //     $sDate = date('Y-m-d', strtotime($startDate . ' - ' . $dayWeek . ' days'));
                            //     $eDate = date('Y-m-d', strtotime($endsDate . ' - ' . 0 . ' days'));
                            // } else {

                            //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                            //     $eDate = date('Y-m-d', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                            // }
                            // if ($i == $weekCount - 1) {
                            //     $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                            //     $eDate = $endDate;
                            // }

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', '!=', null)->whereIn('pid', $clawBackPid)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereIn('pid', $clawBackPid)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            $time = strtotime($sDate);
                            // $month = date("M", $time);
                            $total[] = [
                                'date' => $month, // date('Y-m-d', strtotime($sDate)) . ' to ' . date('Y-m-d', strtotime($eDate)),
                                // 'm1_account' => round($accountM1->account, 5),
                                // 'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }

                            $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                ->orderBy('m1_date', 'desc')
                                ->sum('m1_amount');

                            $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                ->orderBy('m1_date', 'desc')
                                ->sum('m2_amount');

                            $amount[] = [
                                'date' => $month, // $sDate . ' to ' . $eDate,
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }

                        // for ($i = 0; $i < $weekCount; $i++) {
                        //     $endsDate = date('Y-m-d', strtotime($startDate . ' + 6 days'));

                        //     $dayWeek = 7 * $i;
                        //     if ($i == 0) {
                        //         $sDate = date('Y-m-d', strtotime($startDate . ' - ' . $dayWeek . ' days'));
                        //         $eDate = date('Y-m-d', strtotime($endsDate . ' - ' . 0 . ' days'));
                        //     } else {

                        //         $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //         $eDate = date('Y-m-d', strtotime($endsDate . ' + ' . $dayWeek . ' days'));
                        //     }
                        //     if ($i == $weekCount - 1) {
                        //         $sDate = date('Y-m-d', strtotime($startDate . ' + ' . $dayWeek . ' days'));
                        //         $eDate = $endDate;
                        //     }

                        //     $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                        //         ->orderBy('m1_date', 'desc')
                        //         ->sum('m1_amount');

                        //     $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                        //         ->orderBy('m1_date', 'desc')
                        //         ->sum('m2_amount');

                        //     $amount[] = [
                        //         'date' => $sDate . ' to ' . $eDate,
                        //         'm1_amount' => $amountM1,
                        //         'm2_amount' => $amountM2
                        //     ];
                        // }
                    }
                }
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(gross_account_value AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('gross_account_value');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->sum('gross_account_value');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->sum('gross_account_value');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m1_date')->count();
                    } else {
                        $largestSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->max(DB::raw('CAST(kw AS DECIMAL(10,2))'));
                        $avgSystemSize = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->avg('kw');
                        $installKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->sum('kw');
                        $installCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNotNull('m2_date')->count();
                        $pendingKw = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->sum('kw');
                        $pendingKwCount = SalesMaster::whereIn('pid', $pid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNull('date_cancelled')->whereNull('m2_date')->count();
                    }
                    $clawBackPid = SalesMaster::whereIn('pid', $clawPid)->whereBetween('customer_signoff', [$startDate, $endDate])->whereNotNull('date_cancelled')->whereIn('pid', $pid)->pluck('pid');
                    $clawBackAccount = ClawbackSettlement::whereIn('pid', $clawBackPid)->sum('clawback_amount');
                    $clawBackAccountCount = count($clawBackPid);
                    if ($dateDays <= 15) {
                        for ($i = 0; $i < $dateDays; $i++) {
                            $weekDate = date('Y-m-d', strtotime($startDate.' + '.$i.' days'));

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m1_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m2_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', $weekDate)->whereIn('pid', $clawBackPid)->where($dateColumn, '!=', null)
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', $weekDate)->where($dateColumn, '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', $weekDate)->whereIn('pid', $clawBackPid)->where($dateColumn, '!=', null)
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            $total[] = [
                                'date' => date('m-d-Y', strtotime($startDate.' + '.$i.' days')),
                                // 'm1_account' => round($accountM1->account, 5),
                                // 'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }
                        }
                    } else {
                        $weekCount = round($dateDays / 7);
                        $totalWeekDay = 7 * $weekCount;
                        $extraDay = $dateDays - $totalWeekDay;

                        if ($extraDay > 0) {
                            $weekCount = $weekCount + 1;
                        }

                        for ($i = 0; $i < $weekCount; $i++) {
                            $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                            $dayWeek = 7 * $i;
                            if ($i == 0) {
                                $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                            } else {

                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                            }
                            if ($i == $weekCount - 1) {
                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = $endDate;
                            }

                            if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`gross_account_value`) AS gross_account_value')
                                    ->where('date_cancelled', '!=', null)->whereIn('pid', $clawBackPid)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();
                                $totalKw = $accountM1->gross_account_value + $accountM2->gross_account_value + $clawBack->gross_account_value;
                            } else {
                                $accountM1 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m1_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $accountM2 = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('m2_date', '!=', null)->whereBetween($dateColumn, [$sDate, $eDate])->whereIn('pid', $pid)
                                    ->first();

                                $clawBack = SalesMaster::selectRaw('count(`pid`) AS account, SUM(`kw`) AS kw')
                                    ->where('date_cancelled', '!=', null)->whereIn('pid', $clawBackPid)->whereBetween($dateColumn, [$sDate, $eDate])
                                    ->first();
                                $totalKw = $accountM1->kw + $accountM2->kw + $clawBack->kw;
                            }

                            $time = strtotime($sDate);
                            $month = date('M', $time);
                            $total[] = [
                                'date' => date('Y-m-d', strtotime($sDate)).' to '.date('Y-m-d', strtotime($eDate)),
                                // 'm1_account' => round($accountM1->account, 5),
                                // 'm2_account' => round($accountM2->account, 5),
                                'claw_back' => round($clawBack->account, 5),
                                'total_account' => round($accountM1->account + $accountM2->account + $clawBack->account, 5),
                                'total_kw' => round($totalKw, 5),
                            ];
                            foreach ($mdates as $date) {
                                $total[count($total) - 1] = array_merge($total[count($total) - 1], [$date => 10]);
                            }
                        }

                        for ($i = 0; $i < $weekCount; $i++) {
                            $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                            $dayWeek = 7 * $i;
                            if ($i == 0) {
                                $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                            } else {

                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                            }
                            if ($i == $weekCount - 1) {
                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = $endDate;
                            }

                            $amountM1 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                ->orderBy('m1_date', 'desc')
                                ->sum('m1_amount');

                            $amountM2 = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])
                                ->orderBy('m1_date', 'desc')
                                ->sum('m2_amount');

                            $amount[] = [
                                'date' => $sDate.' to '.$eDate,
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    }
                }
            }
        }
        $data['heading_count_kw'] = [
            'largest_system_size' => round($largestSystemSize, 2),
            'avg_system_size' => round($avgSystemSize, 2),
            'install_kw' => round($installKw, 2).'('.$installCount.')',
            'pending_kw' => round($pendingKw, 2).'('.$pendingKwCount.')',
            'clawBack_account' => $clawBackAccount.'('.$clawBackAccountCount.')',
        ];

        $data['my_sales'] = $total;
        $data['kw_type'] = $kwType;

        return response()->json([
            'ApiName' => 'My sales graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function account_graph(Request $request): JsonResponse
    {
        $date = date('Y-m-d');
        $companyProfile = CompanyProfile::first();
        if ($request->has('filter') && ! empty($request->input('filter')) && $request->input('office_id') == 'all') {
            $filterDataDateWise = $request->input('filter');
            $stateIds = AdditionalLocations::where('user_id', auth()->user()->id)->pluck('state_id')->toArray();
            array_push($stateIds, auth()->user()->state_id);
            if ($request->input('user_id') != '') {
                $userId = User::where('id', $request->input('user_id'))->pluck('id');
                $pid = DB::table('sale_master_process')->whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
                $clawbackPid = DB::table('clawback_settlements')->whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
            } else {
                $userId = User::pluck('id');
                $pid = DB::table('sale_master_process')->pluck('pid');
                $clawbackPid = DB::table('clawback_settlements')->whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
            }

            if ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));
                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i <= $currentDate->dayOfWeek; $i++) {
                    $newDateTime = Carbon::now()->subDays(6 - $i);
                    $weekDate = date('Y-m-d', strtotime($newDateTime));

                    $pid = SalesMaster::where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $amount[] = [
                        'date' => date('m-d-Y', strtotime($newDateTime)),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i <= 7; $i++) {
                    $currentDate = \Carbon\Carbon::now();
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                    $weekDate = date('Y-m-d', strtotime($startOfLastWeek));
                    $amountM1 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m1_amount');

                    $amountM2 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m2_amount');
                    $amount[] = [
                        'date' => $date('m-d-Y', strtotime($startOfLastWeek)),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'this_month') {
                $endOfLastWeek = Carbon::now();
                $day = date('d', strtotime($endOfLastWeek));

                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                for ($i = 0; $i <= $dateDays; $i++) {
                    $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));

                    $pid = SalesMaster::where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $amount[] = [
                        'date' => date('m-d-Y', strtotime(now()->subDays($i))),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(0)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2)->addDays(30)));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i < $month; $i++) {
                    $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));
                    $amountM1 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m1_amount');

                    $amountM2 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m2_amount');
                    $amount[] = [
                        'date' => date('m-d-Y', strtotime(now()->subDays($i))),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm-d-y';
                for ($i = 0; $i < $weeks; $i++) {
                    $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2 - $i)->addDays(30)));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)
                            ->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }
                    $currentDate = \Carbon\Carbon::now();
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $startDate = $date->copy();
                    // loop to end of the week while not crossing the last date of month
                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    if ($date->format($f) < $eom->format($f)) {
                        $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    } else {
                        $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $weekDate = $dates['w'.$i];
                    $amount[] = [
                        'date' => $weekDate,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '!=', null)->whereIn('pid', $pid)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm-d-y';
                for ($i = 0; $i < $weeks; $i++) {
                    $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6 - $i)->addDays(30)->startOfMonth()));
                    $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5 - $i)->addDays(30)->startOfMonth()));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)
                            ->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }
                    $currentDate = \Carbon\Carbon::now();
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $startDate = $date->copy();
                    // loop to end of the week while not crossing the last date of month
                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    if ($date->format($f) < $eom->format($f)) {
                        $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    } else {
                        $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $weekDate = $dates['w'.$i];
                    $amount[] = [
                        'date' => $weekDate,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)
                            ->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $amount[] = [
                        'date' => $month,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->where('date_cancelled', '!=', null)->whereBetween('customer_signoff', [$startDate, $endDate])->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $amount[] = [
                        'date' => $month,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()));
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                    $data = [];

                    $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                        $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                    } else {
                        $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                        $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                    }
                    $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                    $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                    $cancelled = $cancelled - $clawback;

                    if ($dateDays <= 15) {
                        for ($i = 0; $i < $dateDays; $i++) {
                            $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays($i)));
                            $pid = SalesMaster::where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                            $amountM1 = 0;
                            $amountM2 = 0;
                            if (count($pid) > 0) {
                                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                                    ->whereIn('pid', $pid)->first();
                                $amountM1 = $salesm1m2Amount->m1;
                                $amountM2 = $salesm1m2Amount->m2;
                            }

                            $amount[] = [
                                'date' => date('m-d-Y', strtotime(Carbon::now()->subMonths(12)->addDays($i))),
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    } else {
                        $weekCount = round($dateDays / 7);
                        $totalWeekDay = 7 * $weekCount;
                        $extraDay = $dateDays - $totalWeekDay;

                        if ($extraDay > 0) {
                            $weekCount = $weekCount + 1;
                        }

                        for ($i = 0; $i < $weekCount; $i++) {
                            $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                            $dayWeek = 7 * $i;
                            if ($i == 0) {
                                $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                            } else {

                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                            }
                            if ($i == $weekCount - 1) {
                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = $endDate;
                            }

                            $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                            $amountM1 = 0;
                            $amountM2 = 0;
                            if (count($pid) > 0) {
                                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                                    ->whereIn('pid', $pid)
                                    ->first();
                                $amountM1 = $salesm1m2Amount->m1;
                                $amountM2 = $salesm1m2Amount->m2;
                            }

                            $amount[] = [
                                'date' => date('m-d-Y', strtotime($sDate)).' to '.date('m-d-Y', strtotime($eDate)),
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    }
                }
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                    $data = [];

                    $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                        $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                    } else {
                        $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                        $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                    }
                    $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                    $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                    $cancelled = $cancelled - $clawback;

                    if ($dateDays <= 15) {
                        for ($i = 0; $i < $dateDays; $i++) {
                            $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays($i)));
                            $pid = SalesMaster::where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                            $amountM1 = 0;
                            $amountM2 = 0;
                            if (count($pid) > 0) {
                                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                                    ->whereIn('pid', $pid)->first();
                                $amountM1 = $salesm1m2Amount->m1;
                                $amountM2 = $salesm1m2Amount->m2;
                            }

                            $amount[] = [
                                'date' => date('m-d-Y', strtotime(Carbon::now()->subMonths(12)->addDays($i))),
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    } else {
                        $weekCount = round($dateDays / 7);
                        $totalWeekDay = 7 * $weekCount;
                        $extraDay = $dateDays - $totalWeekDay;

                        if ($extraDay > 0) {
                            $weekCount = $weekCount + 1;
                        }

                        for ($i = 0; $i < $weekCount; $i++) {
                            $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                            $dayWeek = 7 * $i;
                            if ($i == 0) {
                                $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                            } else {

                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                            }
                            if ($i == $weekCount - 1) {
                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = $endDate;
                            }

                            $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                            $amountM1 = 0;
                            $amountM2 = 0;
                            if (count($pid) > 0) {
                                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                                    ->whereIn('pid', $pid)
                                    ->first();
                                $amountM1 = $salesm1m2Amount->m1;
                                $amountM2 = $salesm1m2Amount->m2;
                            }

                            $amount[] = [
                                'date' => date('m-d-Y', strtotime($sDate)).' to '.date('m-d-Y', strtotime($eDate)),
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    }
                }
            }
        } elseif ($request->has('filter') && ! empty($request->input('filter')) && $request->input('office_id') != 'all') {
            $officeId = $request->input('office_id');
            $filterDataDateWise = $request->input('filter');
            if ($request->input('user_id') != '') {
                $userId = User::where('office_id', $officeId)->where('id', $request->input('user_id'))->pluck('id');
                $pid = DB::table('sale_master_process')->whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
                $clawbackPid = DB::table('clawback_settlements')->whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
            } else {
                $userId = User::where('office_id', $officeId)->pluck('id');
                $pid = DB::table('sale_master_process')->whereIn('closer1_id', $userId)->orWhereIn('closer2_id', $userId)->orWhereIn('setter1_id', $userId)->orWhereIn('setter2_id', $userId)->pluck('pid');
                $clawbackPid = DB::table('clawback_settlements')->whereNotNull('pid')->groupBy('pid')->pluck('pid')->toArray();
            }
            if ($filterDataDateWise == 'this_week') {
                $currentDate = \Carbon\Carbon::now();
                $startDate = date('Y-m-d', strtotime(Carbon::now()->startOfWeek()));
                $endDate = date('Y-m-d', strtotime(now()));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i <= $currentDate->dayOfWeek; $i++) {
                    $newDateTime = Carbon::now()->subDays(6 - $i);
                    $weekDate = date('Y-m-d', strtotime($newDateTime));

                    $pid = SalesMaster::where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $amount[] = [
                        'date' => date('m-d-Y', strtotime($newDateTime)),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_week') {
                $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek();
                $endOfLastWeek = Carbon::now()->subDays(7)->endOfWeek();
                $startDate = date('Y-m-d', strtotime($startOfLastWeek));
                $endDate = date('Y-m-d', strtotime($endOfLastWeek));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i <= 7; $i++) {
                    $currentDate = \Carbon\Carbon::now();
                    $startOfLastWeek = Carbon::now()->subDays(7)->startOfWeek()->addDays($i);
                    $weekDate = date('Y-m-d', strtotime($startOfLastWeek));
                    $amountM1 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m1_amount');

                    $amountM2 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m2_amount');
                    $amount[] = [
                        'date' => date('m-d-Y', strtotime($startOfLastWeek)),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'this_month') {
                $endOfLastWeek = Carbon::now();
                $startOfMonth = Carbon::now()->startOfMonth();
                $endOfMonth = Carbon::now()->endOfMonth();
                $startDate = date('Y-m-d', strtotime($startOfMonth));
                $endDate = date('Y-m-d', strtotime($endOfMonth));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                for ($i = 0; $i <= $dateDays; $i++) {
                    $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));

                    $pid = SalesMaster::where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $amount[] = [
                        'date' => date('m-d-Y', strtotime(now()->subDays($i))),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_month') {
                $month = \Carbon\Carbon::now()->subMonths(1)->daysInMonth;
                $startDate = Carbon::now()->startOfMonth()->subMonth()->toDateString();
                $endDate = Carbon::now()->subMonth()->endOfMonth()->toDateString();

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i < $month; $i++) {
                    $weekDate = date('Y-m-d', strtotime(now()->subDays($i)));
                    $amountM1 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m1_amount');

                    $amountM2 = SalesMaster::where('customer_signoff', $weekDate)
                        ->orderBy('m1_date', 'desc')
                        ->sum('m2_amount');
                    $amount[] = [
                        'date' => date('m-d-Y', strtotime(now()->subDays($i))),
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'this_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q1: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q2: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(3)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q3: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(6)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(8)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q4: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(9)->startOfMonth())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->startOfYear()->addMonths(11)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm-d-y';
                for ($i = 0; $i < $weeks; $i++) {
                    $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(2 - $i)->addDays(30)));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)
                            ->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $currentDate = \Carbon\Carbon::now();
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $startDate = $date->copy();
                    // loop to end of the week while not crossing the last date of month
                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    if ($date->format($f) < $eom->format($f)) {
                        $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    } else {
                        $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $weekDate = $dates['w'.$i];
                    $amount[] = [
                        'date' => $weekDate,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_quarter') {
                $currentMonth = date('n');
                if ($currentMonth >= 1 && $currentMonth <= 3) {
                    // Q4 of last year: October 1 - December 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(9))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(11)->endOfMonth())));
                } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                    // Q1 of current year: January 1 - March 31
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear())));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(2)->endOfMonth())));
                } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                    // Q2 of current year: April 1 - June 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(3))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(5)->endOfMonth())));
                } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                    // Q3 of current year: July 1 - September 30
                    $startDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(6))));
                    $endDate = Carbon::parse(date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->startOfYear()->addMonths(8)->endOfMonth())));
                }
                $currentMonthDay = $startDate->diffInDays($endDate);
                $weeks = (int) (($currentMonthDay % 365) / 7);

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));
                $date = Carbon::parse($startDate = date('Y-m-d', strtotime('-5 days', strtotime($startDate))));
                $eom = Carbon::parse($endDate);
                $dates = [];
                $f = 'm-d-y';
                for ($i = 0; $i < $weeks; $i++) {
                    $sDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(6 - $i)->addDays(30)->startOfMonth()));
                    $eDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(5 - $i)->addDays(30)->startOfMonth()));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)
                            ->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $currentDate = \Carbon\Carbon::now();
                    $sDate = date('Y-m-d', strtotime(Carbon::today()->subMonths(3 - $i)->addDays(30)));
                    $eDate = date('Y-m-d', strtotime('+7 days', strtotime($sDate)));
                    $startDate = $date->copy();
                    // loop to end of the week while not crossing the last date of month
                    while ($date->dayOfWeek != Carbon::SUNDAY && $date->lte($eom)) {
                        $date->addDay();
                    }

                    $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    if ($date->format($f) < $eom->format($f)) {
                        $dates['w'.$i] = $startDate->format($f).' to '.$date->format($f);
                    } else {
                        $dates['w'.$i] = $startDate->format($f).' to '.$eom->format($f);
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $weekDate = $dates['w'.$i];
                    $amount[] = [
                        'date' => $weekDate,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'this_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(0)->endOfYear()));
                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->where('date_cancelled', null)->whereIn('pid', $pid)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $amount[] = [
                        'date' => $month,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));

                $data = [];
                $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                } else {
                    $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                    $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                }
                $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                $cancelled = $cancelled - $clawback;

                for ($i = 0; $i < 12; $i++) {
                    $sDate = date('Y-m-d', strtotime('+'.$i.' months', strtotime($startDate)));
                    $eDate = date('Y-m-d', strtotime('+'.$i + 1 .' months', strtotime($startDate)));

                    $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                    $amountM1 = 0;
                    $amountM2 = 0;
                    if (count($pid) > 0) {
                        $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                            ->whereIn('pid', $pid)->first();
                        $amountM1 = $salesm1m2Amount->m1;
                        $amountM2 = $salesm1m2Amount->m2;
                    }

                    $time = strtotime($sDate);
                    $month = date('M', $time);
                    $amount[] = [
                        'date' => $month,
                        'm1_amount' => $amountM1,
                        'm2_amount' => $amountM2,
                    ];
                }
            } elseif ($filterDataDateWise == 'custom') {
                $startDate = $filterDataDateWise = $request->input('start_date');
                $endDate = $filterDataDateWise = $request->input('end_date');
                $now = strtotime($endDate);
                $your_date = strtotime($startDate);
                $dateDiff = $now - $your_date;
                $dateDays = floor($dateDiff / (60 * 60 * 24));

                if (isset($startDate) && $startDate != '' && isset($endDate) && $endDate != '') {
                    $data = [];

                    $totalSales = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->get();
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNotNull('m1_date')->count();
                        $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->whereIn('pid', $pid)->whereNull('date_cancelled')->whereNull('m1_date')->whereNull('m2_date')->whereNotNull('customer_signoff')->count();
                    } else {
                        $m2Complete = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('m2_date', '!=', null)->whereIn('pid', $pid)->where('date_cancelled', null)->count();
                        $m2Pending = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', null)->where('m2_date', '=', null)->whereIn('pid', $pid)->count();
                    }
                    $clawback = SalesMaster::whereIn('pid', $clawbackPid)->whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                    $cancelled = SalesMaster::whereBetween('customer_signoff', [$startDate, $endDate])->where('date_cancelled', '!=', null)->whereIn('pid', $pid)->count();
                    $cancelled = $cancelled - $clawback;

                    if ($dateDays <= 15) {
                        for ($i = 0; $i < $dateDays; $i++) {
                            $weekDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)->addDays($i)));
                            $pid = SalesMaster::where('customer_signoff', $weekDate)->pluck('pid')->toArray();
                            $amountM1 = 0;
                            $amountM2 = 0;
                            if (count($pid) > 0) {
                                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                                    ->whereIn('pid', $pid)->first();
                                $amountM1 = $salesm1m2Amount->m1;
                                $amountM2 = $salesm1m2Amount->m2;
                            }

                            $amount[] = [
                                'date' => date('m-d-Y', strtotime(Carbon::now()->subMonths(12)->addDays($i))),
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    } else {
                        $weekCount = round($dateDays / 7);
                        $totalWeekDay = 7 * $weekCount;
                        $extraDay = $dateDays - $totalWeekDay;

                        if ($extraDay > 0) {
                            $weekCount = $weekCount + 1;
                        }

                        for ($i = 0; $i < $weekCount; $i++) {
                            $endsDate = date('Y-m-d', strtotime($startDate.' + 6 days'));

                            $dayWeek = 7 * $i;
                            if ($i == 0) {
                                $sDate = date('Y-m-d', strtotime($startDate.' - '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' - '. 0 .' days'));
                            } else {

                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = date('Y-m-d', strtotime($endsDate.' + '.$dayWeek.' days'));
                            }
                            if ($i == $weekCount - 1) {
                                $sDate = date('Y-m-d', strtotime($startDate.' + '.$dayWeek.' days'));
                                $eDate = $endDate;
                            }

                            $pid = SalesMaster::whereBetween('customer_signoff', [$sDate, $eDate])->pluck('pid')->toArray();
                            $amountM1 = 0;
                            $amountM2 = 0;
                            if (count($pid) > 0) {
                                $salesm1m2Amount = SaleMasterProcess::selectRaw('SUM(IFNULL(`closer1_m1`, 0) + IFNULL(`closer2_m1`, 0)+ IFNULL(`setter1_m1`, 0)+ IFNULL(`setter2_m1`, 0)) AS m1, SUM(IFNULL(`closer1_m2`, 0) + IFNULL(`closer2_m2`, 0)+ IFNULL(`setter1_m2`, 0)+ IFNULL(`setter2_m2`, 0)) AS m2')
                                    ->whereIn('pid', $pid)
                                    ->first();
                                $amountM1 = $salesm1m2Amount->m1;
                                $amountM2 = $salesm1m2Amount->m2;
                            }
                            $amount[] = [
                                'date' => date('m-d-Y', strtotime($sDate)).' to '.date('m-d-Y', strtotime($eDate)),
                                'm1_amount' => $amountM1,
                                'm2_amount' => $amountM2,
                            ];
                        }
                    }
                }
            }
        }

        $data['accounts'] = [
            'total_sales' => count($totalSales),
            'm2_complete' => $m2Complete,
            'm2_pending' => $m2Pending,
            'cancelled' => $cancelled,
            'clawback' => $clawback,
        ];
        if ($m2Complete > 0 && count($totalSales) > 0) {
            $inatll = round((($m2Complete / count($totalSales)) * 100), 5);
            $data['install_ratio'] = [
                'install' => $inatll.'%',
                'uninstall' => round(100 - $inatll, 5).'%',
            ];
        } else {
            $data['install_ratio'] = [
                'install' => '0%',
                'uninstall' => '100%',
            ];
        }
        $data['graph_m1_m2_amount'] = [
            'graph_amount' => $amount,
        ];

        return response()->json([
            'ApiName' => 'filter_customer_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ]);
    }

    public function pastPayStub(Request $request)
    {
        // Input Validation
        $validator = Validator::make($request->all(), [
            'year' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perpage = $request->input('perpage', 10);
        $year = $request->year;
        $userId = $request->user_id;

        $wokerType = User::where('id', $userId)->value('worker_type');
        // Fetch user data
        // $user_data = User::where('users.id', $userId)
        //     ->leftJoin('position_pay_frequencies', 'users.position_id', '=', 'position_pay_frequencies.position_id')
        //     ->leftJoin('frequency_types', 'position_pay_frequencies.frequency_type_id', '=', 'frequency_types.id')
        //     ->select(
        //         'position_pay_frequencies.frequency_type_id',
        //         'frequency_types.name as frequency_name'
        //     )
        //     ->first();

        // Build the base query
        $payrollHistory_query = PayrollHistory::query()
            ->whereYear('payroll_history.created_at', $year)
            ->where('payroll_history.payroll_id', '!=', 0)
            ->where('payroll_history.is_onetime_payment', '!=', 1)
            ->whereIn('payroll_history.everee_payment_status', [0, 3]);

        // Apply frequency-specific joins and conditions
        // switch ($user_data->frequency_name) {
        //     case 'Weekly':
        //         $payrollHistory_query->leftJoin('weekly_pay_frequencies', function ($join) {
        //             $join->on('weekly_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
        //                 ->on('weekly_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
        //         })->where('weekly_pay_frequencies.closed_status', 1);
        //         break;
        //     case 'Monthly':
        //         $payrollHistory_query->leftJoin('monthly_pay_frequencies', function ($join) {
        //             $join->on('monthly_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
        //                 ->on('monthly_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
        //         })->where('monthly_pay_frequencies.closed_status', 1);
        //         break;
        //     case 'Bi-Weekly':
        //         $payrollHistory_query->leftJoin('additional_pay_frequencies', function ($join) {
        //             $join->on('additional_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
        //                 ->on('additional_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
        //         })
        //         ->where('additional_pay_frequencies.type', AdditionalPayFrequency::BI_WEEKLY_TYPE)
        //         ->where('additional_pay_frequencies.closed_status', 1);
        //         break;
        //     case 'Semi-Monthly':
        //         $payrollHistory_query->leftJoin('additional_pay_frequencies', function ($join) {
        //             $join->on('additional_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
        //                 ->on('additional_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
        //         })
        //         ->where('additional_pay_frequencies.type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)
        //         ->where('additional_pay_frequencies.closed_status', 1);
        //         break;
        //     default:
        //         // No additional joins
        //         break;
        // }

        // Apply user filter
        if (! (auth()->user()->is_super_admin == '1' && $userId == 1)) {
            $payrollHistory_query->where('payroll_history.user_id', $userId);
        }

        // Include payroll_history_count in the query and paginate
        $payrollHistory = $payrollHistory_query
            ->select([
                DB::raw('MAX(payroll_history.id) as id'),
                'payroll_history.user_id',
                'payroll_history.pay_period_from',
                'payroll_history.pay_period_to',
                'payroll_history.status',
                DB::raw("DATE_FORMAT(MAX(payroll_history.created_at), '%Y-%m-%d') as payroll_date"),
                DB::raw('COUNT(payroll_history.id) as payroll_history_count'),
                DB::raw('COALESCE(SUM(payroll_history.commission), 0) as commission'),
                DB::raw('COALESCE(SUM(payroll_history.override), 0) as override'),
                DB::raw('COALESCE(SUM(payroll_history.reconciliation), 0) as reconciliation'),
                DB::raw('COALESCE(SUM(payroll_history.deduction), 0) as deduction'),
                DB::raw('COALESCE(SUM(payroll_history.adjustment), 0) as adjustment'),
                DB::raw('COALESCE(SUM(payroll_history.reimbursement), 0) as reimbursement'),
                DB::raw('COALESCE(SUM(payroll_history.net_pay), 0) as net_pay'),
                DB::raw('COALESCE(SUM(payroll_history.custom_payment), 0) as custom_payment'),
            ])
            ->groupBy([
                'payroll_history.user_id',
                'payroll_history.pay_period_from',
                'payroll_history.pay_period_to',
                'payroll_history.status',
            ])
            ->orderBy('payroll_history.pay_period_from', 'desc')
            ->paginate($perpage);

        // Fetch necessary IDs and group them
        $userCommissionPayrollLocks = UserCommissionLock::whereYear('pay_period_from', $year)
            ->where('is_mark_paid', '1')
            ->where('status', '3')
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id.'-'.$item->pay_period_from.'-'.$item->pay_period_to;
            });

        $clawbackSettlementPayrollLocks = ClawbackSettlementLock::whereYear('pay_period_from', $year)
            ->where('is_mark_paid', '1')
            ->where('status', '3')
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id.'-'.$item->pay_period_from.'-'.$item->pay_period_to;
            });

        $overridePayrollLocks = UserOverridesLock::whereYear('pay_period_from', $year)
            ->where('is_mark_paid', '1')
            ->where('status', '3')
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id.'-'.$item->pay_period_from.'-'.$item->pay_period_to;
            });

        $approvalsAndRequestPayrollLocks = ApprovalsAndRequestLock::whereYear('pay_period_from', $year)
            ->where('is_mark_paid', '1')
            ->where('status', 'Paid')
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id.'-'.$item->pay_period_from.'-'.$item->pay_period_to;
            });

        $payrollAdjustmentDetailPayrollLocks = PayrollAdjustmentDetailLock::whereYear('pay_period_from', $year)
            ->get()
            ->groupBy(function ($item) {
                return $item->user_id.'-'.$item->pay_period_from.'-'.$item->pay_period_to;
            });

        foreach ($payrollHistory as $key => $payroll_history) {
            $pay_frequency = User::with('positionpayfrequencies.frequencyType')->where('id', $payroll_history->user_id)->first();
            $payrollHistory[$key]['pay_frequency'] = $pay_frequency;
        }

        // Process the paginated results
        $result = $payrollHistory->getCollection()->map(function ($data) use (
            $userCommissionPayrollLocks,
            $clawbackSettlementPayrollLocks,
            $overridePayrollLocks,
            $approvalsAndRequestPayrollLocks,
            $payrollAdjustmentDetailPayrollLocks
        ) {
            $key = $data->user_id.'-'.$data->pay_period_from.'-'.$data->pay_period_to;

            if ($data->payroll_history_count > 1) {
                // Exclusion IDs
                $excludePayrollIDs = collect()
                    ->merge($userCommissionPayrollLocks->get($key, collect())->pluck('payroll_id'))
                    ->merge($clawbackSettlementPayrollLocks->get($key, collect())->pluck('payroll_id'))
                    ->merge($overridePayrollLocks->get($key, collect())->pluck('payroll_id'))
                    ->merge($approvalsAndRequestPayrollLocks->get($key, collect())->pluck('payroll_id'))
                    ->merge($payrollAdjustmentDetailPayrollLocks->get($key, collect())->pluck('payroll_id'))
                    ->unique()
                    ->values();

                // Fetch sums excluding the payroll IDs
                $sums = PayrollHistory::where('user_id', $data->user_id)
                    ->where('pay_period_from', $data->pay_period_from)
                    ->where('pay_period_to', $data->pay_period_to)
                    ->where('status', '3')
                    ->whereNotIn('payroll_id', $excludePayrollIDs)
                    ->select([
                        DB::raw('COALESCE(SUM(commission), 0) as commission'),
                        DB::raw('COALESCE(SUM(override), 0) as override'),
                        DB::raw('COALESCE(SUM(reconciliation), 0) as reconciliation'),
                        DB::raw('COALESCE(SUM(adjustment), 0) as adjustment'),
                        DB::raw('COALESCE(SUM(reimbursement), 0) as reimbursement'),
                        DB::raw('COALESCE(SUM(net_pay), 0) as net_pay'),
                        DB::raw('COALESCE(SUM(overtime), 0) as overtime'),
                        DB::raw('COALESCE(SUM(hourly_salary), 0) as hourly_salary'),
                        DB::raw('COALESCE(SUM(custom_payment), 0) as custom_payment'),
                    ])
                    ->first();

                $gross_total = $sums->commission + $sums->override + $sums->reconciliation + $sums->hourly_salary + $sums->overtime;
                $miscellaneous = $sums->adjustment + $sums->reimbursement;
                $net_pay = $sums->net_pay;

                // added to show taxes on payroll as in everee and to match the net pay amount as everee

                $w2taxDetails = W2PayrollTaxDeduction::select(DB::raw('(SUM(state_income_tax) + SUM(federal_income_tax) + SUM(medicare_tax) + SUM(social_security_tax) + SUM(additional_medicare_tax)) as total_taxes'))->where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->first();

                if (! empty($w2taxDetails->total_taxes)) {
                    $net_pay = $net_pay - $w2taxDetails->total_taxes;
                }

                $custom_payment = $sums->custom_payment;
            } else {

                // add hourly salary and overtime
                $hourlysalary1 = PayrollHistory::where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'user_id' => $data->user_id, 'status' => '3'])->sum('hourly_salary');
                $overtime1 = PayrollHistory::where(['pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to, 'user_id' => $data->user_id, 'status' => '3'])->sum('overtime');

                $gross_total = $data->commission + $data->override + $data->reconciliation + $hourlysalary1 + $overtime1;
                $miscellaneous = $data->adjustment + $data->reimbursement;
                $net_pay = $data->net_pay;

                // added to show taxes on payroll as in everee and to match the net pay amount as everee

                $w2taxDetails = W2PayrollTaxDeduction::select(DB::raw('(SUM(state_income_tax) + SUM(federal_income_tax) + SUM(medicare_tax) + SUM(social_security_tax) + SUM(additional_medicare_tax)) as total_taxes'))->where(['user_id' => $data->user_id, 'pay_period_from' => $data->pay_period_from, 'pay_period_to' => $data->pay_period_to])->first();

                if (! empty($w2taxDetails->total_taxes)) {
                    $net_pay = $net_pay - $w2taxDetails->total_taxes;
                }

                $custom_payment = $data->custom_payment;
            }

            return [
                'id' => $data->id,
                'user_id' => $data->user_id,
                'pay_period_from' => $data->pay_period_from,
                'pay_period_to' => $data->pay_period_to,
                'payroll_date' => $data->payroll_date,
                'gross_total' => $gross_total,
                'deduction' => $data->deduction,
                'net_pay' => $net_pay,
                'taxes' => $w2taxDetails->total_taxes ?? 0,
                'employer_taxes' => 0,
                'miscellaneous' => $miscellaneous,
                'pay_frequency' => isset($data->pay_frequency->positionpayfrequencies->frequencyType->name) ? $data->pay_frequency->positionpayfrequencies->frequencyType->name : null,
                'type' => 'paystub',
                'custom_payment' => $custom_payment,
            ];
        });

        // Replace the collection in the paginator
        $payrollHistory->setCollection($result);

        // Return the paginated response
        return response()->json([
            'ApiName' => 'past_pay_stub_list',
            'status' => true,
            'message' => 'Successfully.',
            'worker_type' => $wokerType,
            'data' => $payrollHistory,
        ], 200);
    }

    public function pastPayStub_optimz(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required',
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $perpage = $request->perpage ?? 10;
        $year = $request->year;
        $userId = $request->user_id;

        // Base payroll history query
        $payrollHistoryQuery = PayrollHistory::whereYear('payroll_history.created_at', $year)
            ->where('payroll_history.payroll_id', '!=', 0)
            ->whereIn('payroll_history.everee_payment_status', [0, 3]);

        // Filtering for non-admin users
        if (! (auth()->user()->is_super_admin == '1' && $userId == 1)) {
            $payrollHistoryQuery->where('payroll_history.user_id', $userId);
        }

        // Get lock IDs for filtering
        $lockedCommissionIDs = UserCommissionLock::where(['user_id' => $userId, 'is_mark_paid' => 1, 'status' => '3'])
            ->pluck('payroll_id');
        $lockedClawbackIDs = ClawbackSettlementLock::where(['user_id' => $userId, 'is_mark_paid' => 1, 'status' => '3'])
            ->pluck('payroll_id');
        $lockedOverrideIDs = UserOverridesLock::where(['user_id' => $userId, 'is_mark_paid' => 1, 'status' => '3'])
            ->pluck('payroll_id');
        $lockedAdjustmentIDs = PayrollAdjustmentDetailLock::where(['user_id' => $userId])
            ->whereIn('payroll_id', $lockedOverrideIDs)
            ->orWhereIn('payroll_id', $lockedCommissionIDs)
            ->pluck('payroll_id');
        $lockedApprovalIDs = ApprovalsAndRequestLock::where(['user_id' => $userId, 'is_mark_paid' => 1, 'status' => 'Paid'])
            ->pluck('payroll_id');

        // Apply locked IDs to query
        $payrollHistoryQuery->whereNotIn('payroll_id', $lockedCommissionIDs)
            ->whereNotIn('payroll_id', $lockedClawbackIDs)
            ->whereNotIn('payroll_id', $lockedOverrideIDs)
            ->whereNotIn('payroll_id', $lockedAdjustmentIDs)
            ->whereNotIn('payroll_id', $lockedApprovalIDs);

        // Execute query with aggregation
        $payrollHistory = $payrollHistoryQuery
            ->selectRaw('
                payroll_history.user_id,
                payroll_history.pay_period_from,
                payroll_history.pay_period_to,
                MAX(payroll_history.created_at) as payroll_date,
                SUM(payroll_history.commission) as commission,
                SUM(payroll_history.override) as override,
                SUM(payroll_history.reimbursement) as reimbursement,
                SUM(payroll_history.clawback) as clawback,
                SUM(payroll_history.deduction) as deduction,
                SUM(payroll_history.adjustment) as adjustment,
                SUM(payroll_history.reconciliation) as reconciliation,
                SUM(payroll_history.net_pay) as net_pay
            ')
            ->groupBy(['payroll_history.user_id', 'payroll_history.pay_period_from', 'payroll_history.pay_period_to'])
            ->orderBy('payroll_history.pay_period_from', 'desc')
            ->paginate($perpage);

        // Format result for response
        $result = $payrollHistory->map(function ($data) {
            $gross_total = $data->commission + $data->override + $data->reconciliation;
            $miscellaneous = $data->adjustment + $data->reimbursement;

            return [
                'id' => $data->id,
                'user_id' => $data->user_id,
                'pay_period_from' => $data->pay_period_from,
                'pay_period_to' => $data->pay_period_to,
                'payroll_date' => $data->payroll_date,
                'gross_total' => $gross_total,
                'deduction' => $data->deduction,
                'net_pay' => $data->net_pay,
                'miscellaneous' => $miscellaneous,
                'type' => 'paystub',
            ];
        });

        return response()->json([
            'ApiName' => 'past_pay_stub_list',
            'status' => true,
            'message' => 'Successfully retrieved past pay stubs.',
            'data' => $result,
        ], 200);
    }

    public function pastPayStubGraph(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'year' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $year = $request->year;
        $userId = $request->user_id;
        $currentYear = date('Y');

        if ($year < $currentYear) {
            $month = 12;
        } elseif ($currentYear) {
            $month = date('n');
        }

        $monthName = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = 1; $i <= $month; $i++) {
            // $paydata = PayrollHistory::selectRaw('SUM(`deduction`) AS deduction, SUM(`net_pay`) AS net_pay')->whereMonth('pay_period_to', $i)->whereYear('pay_period_to',$year)->first();
            if (auth()->user()->is_super_admin == '1' && $userId == 1) {
                $paydata = PayrollHistory::selectRaw('SUM(`deduction`) AS deduction, SUM(`net_pay`) AS net_pay')->whereMonth('created_at', $i)->whereYear('created_at', $year)->where('payroll_id', '!=', 0)->orderBy('id', 'desc')->first();
            } else {
                $paydata = PayrollHistory::selectRaw('SUM(`deduction`) AS deduction, SUM(`net_pay`) AS net_pay')->where('user_id', $userId)->whereMonth('created_at', $i)->whereYear('created_at', $year)->where('payroll_id', '!=', 0)->orderBy('id', 'desc')->first();
            }

            $currentMonth = $monthName[$i];
            $data[] = [
                'month' => $currentMonth,
                'amount' => isset($paydata->net_pay) ? $paydata->net_pay : 0,
            ];
        }

        return response()->json([
            'ApiName' => 'past_pay_stub_graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function pastPayStubDetail(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'start_date' => 'required',
                'end_date' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $userId = $request->user_id;
        $data = [];
        $payrollHistory = PayrollHistory::with('usersdata')->where(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
        // return $payrollHistory;
        $userCommission = UserCommissionLock::selectRaw('pid, COUNT(DISTINCT(pid)) AS total')->where(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->first();
        if (! empty($payrollHistory)) {

            $data['pay_stub'] = [
                'pay_date' => $payrollHistory->pay_frequency_date,
                'pay_period_from' => $startDate,
                'pay_period_to' => $endDate,
                'account_this_payperiod' => isset($userCommission->total) ? $userCommission->total : 0,
                'ytd' => null,
            ];

            $data['employee_information'] = [
                'employee_name' => $payrollHistory->usersdata->first_name.' '.$payrollHistory->usersdata->last_name,
                'employee_id' => $payrollHistory->usersdata->employee_id,
                'ssn' => null,
                'bank_account' => isset($payrollHistory->usersdata->account_no) ? $payrollHistory->usersdata->account_no : null,
                'address' => isset($payrollHistory->usersdata->home_address) ? $payrollHistory->usersdata->home_address : null,
                'ytd' => null,
            ];

            $data['earning'] = [
                [
                    'description' => 'Commission',
                    'total' => $payrollHistory->commission,
                    'ytd' => $payrollHistory->commission,
                ],
                [
                    'description' => 'Override',
                    'total' => $payrollHistory->override,
                    'ytd' => $payrollHistory->override,
                ],
                [
                    'description' => 'Reconciliation',
                    'total' => isset($payrollHistory->reconciliation) ? $payrollHistory->reconciliation : 0,
                    'ytd' => isset($payrollHistory->reconciliation) ? $payrollHistory->reconciliation : 0,
                ],
            ];
            $data['earning_gross_pay'] = [
                'total' => ($payrollHistory->commission + $payrollHistory->override + $payrollHistory->reconciliation),
                'ytd_total' => ($payrollHistory->commission + $payrollHistory->override + $payrollHistory->reconciliation),
            ];

            $data['deductions'] = [
                'description' => 'Deductions',
                'total' => isset($payrollHistory->deduction) ? $payrollHistory->deduction : 0,
                'ytd' => isset($payrollHistory->deduction) ? $payrollHistory->deduction : 0,
            ];

            $data['miscellaneous'] = [
                [
                    'description' => 'Adjustments',
                    'total' => isset($payrollHistory->adjustment) ? $payrollHistory->adjustment : 0,
                    'ytd' => isset($payrollHistory->adjustment) ? $payrollHistory->adjustment : 0,
                ],
                [
                    'description' => 'Reimbursements',
                    'total' => isset($payrollHistory->reimbursement) ? $payrollHistory->reimbursement : 0,
                    'ytd' => isset($payrollHistory->reimbursement) ? $payrollHistory->reimbursement : 0,
                ],
                [
                    'description' => 'Total Deductions',
                    'total' => ($payrollHistory->adjustment + $payrollHistory->reimbursement),
                    'ytd' => ($payrollHistory->adjustment + $payrollHistory->reimbursement),
                ],
            ];

            $data['miscellaneous_total'] = [
                'description' => 'Total Deductions',
                'total' => ($payrollHistory->adjustment + $payrollHistory->reimbursement),
                'ytd' => ($payrollHistory->adjustment + $payrollHistory->reimbursement),
            ];

            $data['net_pay'] = [
                'total' => isset($payrollHistory->net_pay) ? $payrollHistory->net_pay : 0,
            ];
        }

        return response()->json([
            'ApiName' => 'past_pay_stub_graph',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function pastPayStubDetailList(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'start_date' => 'required',
                'end_date' => 'required',
                'user_id' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }
        $data = [];
        // $data['CompanyProfile'] = CompanyProfile::first();

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $userId = $request->user_id;
        $search = $request->search;

        $paystubQuery = paystubEmployee::where('user_id', $userId)
            ->where('pay_period_from', '=', $startDate)
            ->where('pay_period_to', '=', $endDate);
        if ($paystubQuery->count() <= 0) {
            $paystubQuery = paystubEmployee::where('user_id', $userId)
                ->whereNull('pay_period_from')
                ->whereNull('pay_period_to');
        }
        $data['CompanyProfile'] = $paystubQuery->select(

            'company_name as name',
            'company_address as address',
            'company_website as company_website',
            'company_phone_number as phone_number',
            'company_type as company_type',
            'company_email as company_email',
            'company_business_name as business_name',
            'company_mailing_address as mailing_address',
            'company_business_ein as business_ein',
            'company_business_phone as business_phone',
            'company_business_address as business_address',
            'company_business_city as business_city',
            'company_business_state as business_state',
            'company_business_zip as business_zip',
            'company_mailing_state as mailing_state',
            'company_mailing_city as mailing_city',
            'company_mailing_zip as mailing_zip',
            'company_time_zone as time_zone',
            'company_business_address_1 as business_address_1',
            'company_business_address_2 as business_address_2',
            'company_business_lat as business_lat',
            'company_business_long as business_long',
            'company_mailing_address_1 as mailing_address_1',
            'company_mailing_address_2 as mailing_address_2',
            'company_mailing_lat as mailing_lat',
            'company_mailing_long as mailing_long',
            'company_business_address_time_zone as business_address_time_zone',
            'company_mailing_address_time_zone as mailing_address_time_zone',
            'company_margin as company_margin',
            'company_country as country',
            'company_logo as logo',
            'company_lat as lat',
            'company_lng as lng'
        )->first();
        if (isset($data['CompanyProfile']) && $data['CompanyProfile'] != null) {
            $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
            $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
            if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                $file_link = $image_file_path.'/'.$data['CompanyProfile']->logo;
                $data['CompanyProfile']['company_logo_s3'] = $file_link;
            }
        }
        $data['payroll_id'] = PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->value('payroll_id');

        $data['pay_stub']['pay_date'] = date('Y-m-d', strtotime(PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->value('created_at')));
        // Commented by Gorakh
        // $data['pay_stub']['net_pay'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->where('payroll_id','!=',0)->sum('net_pay');

        $data['pay_stub']['pay_period_from'] = $startDate;
        $data['pay_stub']['pay_period_to'] = $endDate;

        $data['pay_stub']['period_sale_count'] = UserCommissionLock::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
        $data['pay_stub']['ytd_sale_count'] = UserCommissionLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->selectRaw('COUNT(DISTINCT(pid)) AS count')->pluck('count')[0];
        $data['pay_stub']['net_ytd'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->where('payroll_id', '!=', 0)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('net_pay') ?? 0;

        // $user = User::with('positionDetailTeam')->where('id',$userId)->select('first_name','middle_name','last_name','employee_id','social_sequrity_no','name_of_bank','routing_no','account_no','type_of_account','home_address','zip_code','email','work_email','position_id')->first();
        $user = $paystubQuery->with('positionDetailTeam')
            ->select(
                'user_first_name as first_name',
                'user_middle_name as middle_name',
                'user_last_name as last_name',
                'user_employee_id as employee_id',
                'user_social_sequrity_no as social_sequrity_no',
                'user_name_of_bank as name_of_bank',
                'user_routing_no as routing_no',
                'user_account_no as account_no',
                'user_type_of_account as type_of_account',
                'user_home_address as home_address',
                'user_zip_code as zip_code',
                'user_email as email',
                'user_work_email as work_email',
                'user_position_id as position_id',
                'user_entity_type as entity_type',
                'user_business_name as business_name',
                'user_business_type as business_type',
                'user_business_ein as business_ein',
            )
            ->first();

        $markPaidPayrollIDs = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '1', 'status' => '3'])->pluck('payroll_id');

        $data['employee'] = $user;
        $data['employee']['is_reconciliation'] = PositionReconciliations::where('position_id', $user->position_id)->value('status');

        $UserCommissionPayrollADS = UserCommissionLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '1', 'status' => '3'])->pluck('payroll_id');
        $ClawbackSettlementPayRollIDS = ClawbackSettlementLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '1', 'status' => '3'])->pluck('payroll_id');
        $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::whereIn('payroll_id', $UserCommissionPayrollADS)->where(['user_id' => $userId])->pluck('payroll_id');

        // $data['earnings']['commission']['period_total'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->whereNotIn('payroll_id',$UserCommissionPayrollADS)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->sum('commission');
        $data['earnings']['commission']['period_total'] = UserCommissionLock::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3', 'is_mark_paid' => '0'])->sum('amount');
        $data['earnings']['commission']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('commission');

        $UserOverridesPayRollIDs = UserOverridesLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '1', 'status' => '3'])->pluck('payroll_id');
        // dd($UserOverridesPayRollIDs);
        // \DB::enableQueryLog();
        // $data['earnings']['overrides']['period_total'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->whereNotIn('payroll_id',$UserOverridesPayRollIDs)->sum('override');
        $data['earnings']['overrides']['period_total'] = UserOverridesLock::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3', 'is_mark_paid' => '0'])->sum('amount');
        // dd(\DB::getQueryLog());

        $data['earnings']['overrides']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('override');

        $data['earnings']['reconciliation']['period_total'] = PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => 'paid'])->sum('reimbursement');
        $data['earnings']['reconciliation']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => 'paid'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('reimbursement');

        // $data['earnings']['commission']['period_total'] = UserCommissionLock::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->sum('amount');
        // $data['earnings']['commission']['ytd_total'] = UserCommissionLock::where(['user_id'=>$userId,'status'=>'3'])->where('pay_period_to','<=',$endDate)->whereYear('pay_period_from',date('Y', strtotime($startDate)))->sum('amount');

        // $data['earnings']['overrides']['period_total'] = UserOverridesLock::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->sum('amount');
        // $data['earnings']['overrides']['ytd_total'] = UserOverridesLock::where(['user_id'=>$userId,'status'=>'3'])->where('pay_period_to','<=',$endDate)->whereYear('pay_period_from',date('Y', strtotime($startDate)))->sum('amount');

        // $data['earnings']['reconciliation']['period_total'] = UserReconciliationCommissionLock::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'paid'])->sum('total_due');
        // $data['earnings']['reconciliation']['ytd_total'] = UserReconciliationCommissionLock::where(['user_id'=>$userId,'status'=>'paid'])->where('pay_period_to','<=',$endDate)->whereYear('pay_period_from',date('Y', strtotime($startDate)))->sum('total_due');

        $data['deduction']['standard_deduction']['period_total'] = PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->sum('deduction');
        $data['deduction']['standard_deduction']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->where('payroll_id', '!=', 0)->sum('deduction');

        // Gorakh

        $approvalsAndRequestPayrollIDs = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '1', 'status' => 'Paid'])->pluck('payroll_id');
        $PayrollAdjustmentDetailPayRollIDS = PayrollAdjustmentDetailLock::whereIn('payroll_id', $UserOverridesPayRollIDs)->orWhereIn('payroll_id', $UserCommissionPayrollADS)->where(['user_id' => $userId])->pluck('payroll_id');
        // dd($PayrollAdjustmentDetailPayRollIDS );
        // End  Gorakh
        // echo"DASD";die;
        // $data['miscellaneous']['adjustment']['period_total'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->where('payroll_id','!=',0)->whereNotIn('payroll_id',$approvalsAndRequestPayrollIDs)->whereNotIn('payroll_id',$PayrollAdjustmentDetailPayRollIDS)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->sum('adjustment');

        $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $data['payroll_id'], 'is_mark_paid' => '0'])->sum(\DB::raw('commission_amount + overrides_amount + deductions_amount'));
        $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $data['payroll_id'], 'is_mark_paid' => '0'])->sum(\DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));
        $adjustmentToAdd = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
        $adjustmentToNigative = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->where('adjustment_type_id', 5)->sum('amount');
        $data['miscellaneous']['adjustment']['period_total'] = ($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment);
        // Added by Gorakh
        // $data['pay_stub']['net_pay'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->where('payroll_id','!=',0)->whereNotIn('payroll_id',$UserCommissionPayrollADS)->whereNotIn('payroll_id',$UserOverridesPayRollIDs)->whereNotIn('payroll_id',$approvalsAndRequestPayrollIDs)->whereNotIn('payroll_id',$ClawbackSettlementPayRollIDS)->sum('net_pay');
        $data['pay_stub']['net_pay'] = $this->getTotalnetPayAmount($userId, $startDate, $endDate);
        // End  Gorakh
        // $data['miscellaneous']['adjustment']['period_total'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->where('payroll_id','!=',0)->sum('adjustment');
        $data['miscellaneous']['adjustment']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->where('payroll_id', '!=', 0)->sum('adjustment');

        // $data['miscellaneous']['reimbursement']['period_total'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->where('payroll_id','!=',0)->sum('reimbursement');
        // $data['miscellaneous']['reimbursement']['period_total'] = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->where('payroll_id','!=',0)->whereNotIn('payroll_id',$approvalsAndRequestPayrollIDs)->sum('reimbursement');
        $data['miscellaneous']['reimbursement']['period_total'] = ApprovalsAndRequestLock::where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->where('adjustment_type_id', 2)->sum('amount');
        $data['miscellaneous']['reimbursement']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->where('payroll_id', '!=', 0)->sum('reimbursement');

        $data['type'] = 'paystub';

        // return $commissiondata;

        return response()->json([
            'ApiName' => 'past_pay_stub_detail_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function getTotalnetPayAmount($userId, $startDate, $endDate)
    {
        // $payroll_id = PayrollHistory::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'3'])->where('payroll_id','!=',0)->value('payroll_id');
        $payrollHistory = PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->first();
        $payroll_id = $payrollHistory->payroll_id;

        $userCommissionSum = UserCommissionLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('amount');
        $userOverrideSum = UserOverridesLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('amount');
        $clawbackSettlementSum = ClawbackSettlementLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => '3'])->sum('clawback_amount');

        // $PayrollAdjustmentDetail = PayrollAdjustmentDetailLock::where('payroll_id', $payroll_id)->where(['user_id'=> $userId])->where(['pay_period_from'=> $startDate, 'pay_period_to'=> $endDate, 'is_mark_paid'=> '0','status'=>'3'])->sum('amount');
        // $approvalsAndRequestSum = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id'=> $userId])->where(['pay_period_from'=> $startDate, 'pay_period_to'=> $endDate, 'is_mark_paid'=> '0','status'=>'Paid'])->sum('amount');

        $comm_over_dedu_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(\DB::raw('commission_amount + overrides_amount + deductions_amount'));
        $reim_claw_recon_aadjustment = PayrollAdjustmentLock::where(['payroll_id' => $payroll_id, 'is_mark_paid' => '0'])->sum(\DB::raw('adjustments_amount + reimbursements_amount + clawbacks_amount + reconciliations_amount'));

        $adjustmentToAdd = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->sum('amount');
        $adjustmentToNigative = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [5])->sum('amount');
        $reimbursement = ApprovalsAndRequestLock::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'is_mark_paid' => '0', 'status' => 'Paid'])->whereIn('adjustment_type_id', [2])->sum('amount');
        $deductionSum = PayrollDeductions::where('payroll_id', $payroll_id)->where(['user_id' => $userId])->sum('total');

        $adjustment = ($adjustmentToAdd - $adjustmentToNigative) + ($comm_over_dedu_aadjustment + $reim_claw_recon_aadjustment);

        $net_pay = ($userCommissionSum + $userOverrideSum + $adjustment + $reimbursement - $clawbackSettlementSum - $deductionSum);

        return $net_pay;
    }

    public function pastpaystubcustomerinfo(Request $request)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'start_date' => 'required',
                'end_date' => 'required',
                'user_id' => 'required',
            ]
        );
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $userId = $request->user_id;
        $search = $request->search;

        $commissiondata = UserCommissionLock::where(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->groupBy('pid')->paginate(config('app.paginate', 15)); // ->get();
        // return $commissiondata;
        if (count($commissiondata) > 0) {

            $commissiondata->transform(function ($data) use ($startDate, $endDate) {
                $m1_amount = 0;
                $m2_amount = 0;
                $amount = UserCommissionLock::where(['user_id' => $data->user_id, 'pid' => $data->pid, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->get();
                foreach ($amount as $amt) {
                    if ($amt->amount_type == 'm1') {
                        $m1_amount += $amt->amount;
                    }
                    if ($amt->amount_type == 'm2') {
                        $m2_amount += $amt->amount;
                    }
                }

                $result = SalesMaster::with('salesMasterProcess', 'userDetail')->where(['pid' => $data->pid])->first();
                $setter = isset($result->salesMasterProcess->setter1Detail) ? $result->salesMasterProcess->setter1Detail->first_name.' '.$result->salesMasterProcess->setter1Detail->last_name : null;

                return [
                    'id' => $result->id,
                    'pid' => $result->pid,
                    'customer_name' => $result->customer_name,
                    'customer_state' => $result->customer_state,
                    'setter' => $setter,
                    'kw' => $result->kw,
                    'net_epc' => $result->net_epc,
                    'm1_date' => ($m1_amount > 0) ? $result->m1_date : '',
                    'm1_amount' => $m1_amount,
                    'm2_date' => ($m2_amount > 0) ? $result->m2_date : '',
                    'm2_amount' => $m2_amount,
                    'date_cancelled' => $result->date_cancelled,
                ];
            });
        }

        return response()->json([
            'ApiName' => 'past_pay_stub_customer_info',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $commissiondata,
        ], 200);
    }

    public function currentPayStubDetailList(Request $request)
    {

        $data = [];

        $userId = Auth()->user()->id; // $request->user_id; //
        // $pay_periods = PayrollHistory::where(['user_id'=>$userId,'status'=>'3'])->select('pay_period_from','pay_period_to')->orderBy('pay_period_from','desc')->first();

        $pay_periods_query = Payroll::where(['user_id' => $userId]);

        $user_data = User::where('users.id', '=', $userId)
            ->leftJoin('position_pay_frequencies', 'users.position_id', '=', 'position_pay_frequencies.position_id')
            ->leftJoin('frequency_types', 'position_pay_frequencies.frequency_type_id', '=', 'frequency_types.id')
            ->select(
                'position_pay_frequencies.frequency_type_id',
                'frequency_types.name as frequency_name'
            )->first();

        if ($user_data->frequency_name == 'Weekly') {
            $pay_periods_query = $pay_periods_query->leftJoin('weekly_pay_frequencies', function ($join) {
                $join->on('weekly_pay_frequencies.pay_period_from', '=', 'payrolls.pay_period_from')
                    ->on('weekly_pay_frequencies.pay_period_to', '=', 'payrolls.pay_period_to');
            });
        } elseif ($user_data->frequency_name == 'Monthly') {
            $pay_periods_query = $pay_periods_query->leftJoin('monthly_pay_frequencies', function ($join) {
                $join->on('monthly_pay_frequencies.pay_period_from', '=', 'payrolls.pay_period_from')
                    ->on('monthly_pay_frequencies.pay_period_to', '=', 'payrolls.pay_period_to');
            });
        } elseif ($user_data->frequency_name == 'Bi-Weekly') {
            $pay_periods_query = $pay_periods_query->leftJoin('additional_pay_frequencies', function ($join) {
                $join->on('additional_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
                    ->on('additional_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
            })->where('additional_pay_frequencies.type', AdditionalPayFrequency::BI_WEEKLY_TYPE)->where('additional_pay_frequencies.closed_status', 1);
        } elseif ($user_data->frequency_name == 'Semi-Monthly') {
            $pay_periods_query = $pay_periods_query->leftJoin('additional_pay_frequencies', function ($join) {
                $join->on('additional_pay_frequencies.pay_period_from', '=', 'payroll_history.pay_period_from')
                    ->on('additional_pay_frequencies.pay_period_to', '=', 'payroll_history.pay_period_to');
            })->where('additional_pay_frequencies.type', AdditionalPayFrequency::SEMI_MONTHLY_TYPE)->where('additional_pay_frequencies.closed_status', 1);
        } else {
            // daily ,  biweekly, semimontholy, etc.....
        }

        $pay_periods = $pay_periods_query->select('payrolls.pay_period_from', 'payrolls.pay_period_to')->orderBy('payrolls.pay_period_from', 'desc')
            ->where('payrolls.pay_period_from', '<=', now()->toDateString())
            ->where('payrolls.pay_period_to', '>=', now()->toDateString())
            ->first();

        if (isset($pay_periods->pay_period_from) && isset($pay_periods->pay_period_to)) {

            $startDate = $pay_periods->pay_period_from;
            $endDate = $pay_periods->pay_period_to;

            $data['CompanyProfile'] = CompanyProfile::first();
            $S3_BUCKET_PUBLIC_URL = Settings::where('key', 'S3_BUCKET_PUBLIC_URL')->first();
            $s3_bucket_public_url = $S3_BUCKET_PUBLIC_URL->value;
            if (! empty($s3_bucket_public_url) && $s3_bucket_public_url != null) {
                $image_file_path = $s3_bucket_public_url.config('app.domain_name');
                $file_link = $image_file_path.'/'.$data['CompanyProfile']->logo;
                $data['CompanyProfile']['company_logo_s3'] = $file_link;
            }

            $data['payroll_id'] = Payroll::where([
                'pay_period_from' => $startDate,
                'pay_period_to' => $endDate,
                'user_id' => $userId,
                'status' => '1',
            ])->value('id');

            $data['pay_stub']['pay_date'] = date('Y-m-d', strtotime(Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '1'])->value('created_at')));
            $data['pay_stub']['net_pay'] = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '1'])->value('net_pay');

            $data['pay_stub']['pay_period_from'] = $startDate;
            $data['pay_stub']['pay_period_to'] = $endDate;

            $data['pay_stub']['period_sale_count'] = PayrollHistory::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->count('user_id');
            $data['pay_stub']['ytd_sale_count'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->where('payroll_id', '!=', 0)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->count('user_id');

            $user = User::with('positionDetailTeam')->where('id', $userId)->select('first_name', 'middle_name', 'last_name', 'employee_id', 'social_sequrity_no', 'name_of_bank', 'routing_no', 'account_no', 'type_of_account', 'home_address', 'zip_code', 'email', 'work_email', 'position_id')->first();
            $data['employee'] = $user;
            $data['employee']['is_reconciliation'] = PositionReconciliations::where('position_id', $user->position_id)->value('status');

            $data['earnings']['commission']['period_total'] = UserCommissionLock::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId])->sum('amount');
            $data['earnings']['commission']['ytd_total'] = UserCommissionLock::where(['user_id' => $userId, 'status' => '3'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('amount') + $data['earnings']['commission']['period_total'];

            // $data['earnings']['overrides']['period_total'] = UserOverridesLock::where(['pay_period_from'=>$startDate,'pay_period_to'=>$endDate,'user_id'=>$userId,'status'=>'1'])->sum('amount');
            // $data['earnings']['overrides']['ytd_total'] = UserOverridesLock::where(['user_id'=>$userId,'status'=>'3'])->where('pay_period_to','<=',$endDate)->whereYear('pay_period_from',date('Y', strtotime($startDate)))->sum('amount');

            $data['earnings']['overrides']['period_total'] = UserOverridesLock::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '1'])->sum('amount');
            $data['earnings']['overrides']['ytd_total'] = UserOverridesLock::where(['user_id' => $userId])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('amount');

            $data['earnings']['reconciliation']['period_total'] = UserReconciliationCommissionLock::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => 'paid'])->sum('total_due');
            $data['earnings']['reconciliation']['ytd_total'] = UserReconciliationCommissionLock::where(['user_id' => $userId, 'status' => 'paid'])->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('total_due');

            $data['deduction']['standard_deduction']['period_total'] = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '1'])->sum('deduction');
            $data['deduction']['standard_deduction']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('deduction') + $data['deduction']['standard_deduction']['period_total'];

            $data['miscellaneous']['adjustment']['period_total'] = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '1'])->sum('adjustment');
            $data['miscellaneous']['adjustment']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('adjustment') + $data['miscellaneous']['adjustment']['period_total'];

            $data['miscellaneous']['reimbursement']['period_total'] = Payroll::where(['pay_period_from' => $startDate, 'pay_period_to' => $endDate, 'user_id' => $userId, 'status' => '1'])->sum('reimbursement');
            $data['miscellaneous']['reimbursement']['ytd_total'] = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->where('payroll_id', '!=', 0)->where('pay_period_to', '<=', $endDate)->whereYear('pay_period_from', date('Y', strtotime($startDate)))->sum('reimbursement') + $data['miscellaneous']['reimbursement']['period_total'];
        }
        // return $commissiondata;

        return response()->json([
            'ApiName' => 'past_pay_stub_detail_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);
    }

    public function currentpaystubcustomerinfo(Request $request)
    {

        $userId = Auth()->user()->id; // $request->user_id;
        $pay_periods = PayrollHistory::where(['user_id' => $userId, 'status' => '3'])->select('pay_period_from', 'pay_period_to')->orderBy('pay_period_from', 'desc')->first();

        if (isset($pay_periods->pay_period_from) && isset($pay_periods->pay_period_to)) {

            $startDate = $pay_periods->pay_period_from;
            $endDate = $pay_periods->pay_period_to;

            $commissiondata = UserCommissionLock::where(['user_id' => $userId, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->groupBy('pid')->paginate(config('app.paginate', 15)); // ->get();
            // return $commissiondata;
            if (count($commissiondata) > 0) {

                $commissiondata->transform(function ($data) use ($startDate, $endDate) {
                    $m1_amount = 0;
                    $m2_amount = 0;
                    $amount = UserCommissionLock::where(['user_id' => $data->user_id, 'pid' => $data->pid, 'pay_period_from' => $startDate, 'pay_period_to' => $endDate])->get();
                    foreach ($amount as $amt) {
                        if ($amt->amount_type == 'm1') {
                            $m1_amount += $amt->amount;
                        }
                        if ($amt->amount_type == 'm2') {
                            $m2_amount += $amt->amount;
                        }
                    }
                    $result = SalesMaster::with('salesMasterProcess', 'userDetail')->where(['pid' => $data->pid])->first();
                    $setter = isset($result->salesMasterProcess->setter1Detail) ? $result->salesMasterProcess->setter1Detail->first_name.' '.$result->salesMasterProcess->setter1Detail->last_name : null;

                    return [
                        'id' => $result->id,
                        'pid' => $result->pid,
                        'customer_name' => $result->customer_name,
                        'customer_state' => $result->customer_state,
                        'setter' => $setter,
                        'kw' => $result->kw,
                        'net_epc' => $result->net_epc,
                        'm1_date' => ($m1_amount > 0) ? $result->m1_date : '',
                        'm1_amount' => $m1_amount,
                        'm2_date' => ($m2_amount > 0) ? $result->m2_date : '',
                        'm2_amount' => $m2_amount,
                        'date_cancelled' => $result->date_cancelled,
                    ];
                });
            }
        }

        return response()->json([
            'ApiName' => 'past_pay_stub_customer_info',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $commissiondata,
        ], 200);
    }

    public function payStubCommissionDetails(Request $request)
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        // $payroll = PayrollHistory::where(['user_id' => $user_id,'pay_period_from' => $pay_period_from,'pay_period_to' => $pay_period_to])->first();
        // dd($Payroll);
        if (! empty($Payroll)) {

            // $usercommission = UserCommissionLock::with('userdata', 'saledata')->where('status',$Payroll->status)->where(['user_id' =>  $Payroll->user_id, 'pay_period_from' =>  $Payroll->pay_period_from,'pay_period_to' =>  $Payroll->pay_period_to])->get();

            if ($Payroll->status == 3) {
                $usercommission = UserCommissionLock::with('userdata', 'saledata')
                    ->whereIn('status', [$Payroll->status, 6])/* where('status',$Payroll->status) */
                    ->where([
                        'user_id' => $Payroll->user_id,
                        'pay_period_from' => $Payroll->pay_period_from,
                        'pay_period_to' => $Payroll->pay_period_to,
                    ])
                    ->where(function ($query) {
                        $query->where('is_onetime_payment', '!=', 1)
                            ->whereNull('one_time_payment_id');
                    })
                    ->get();
            } else {
                $usercommission = UserCommissionLock::with('userdata', 'saledata')
                    ->where('status', '<', '3')
                    ->where([
                        'user_id' => $Payroll->user_id,
                        'pay_period_from' => $Payroll->pay_period_from,
                        'pay_period_to' => $Payroll->pay_period_to,
                    ])
                    ->where(function ($query) {
                        $query->where('is_onetime_payment', '!=', 1)
                            ->whereNull('one_time_payment_id');
                    })
                    ->get();
            }
            /* $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['type' =>  'commission', 'user_id' =>  $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get(); */
            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')
                ->where(function ($query) use ($Payroll) {
                    $query->where('type', 'commission')
                        ->where('user_id', $Payroll->user_id)
                        ->where('clawback_type', 'next payroll')
                        ->where('pay_period_from', $Payroll->pay_period_from)
                        ->where('pay_period_to', $Payroll->pay_period_to);
                })
                ->orWhere(function ($query) use ($Payroll) {
                    $query->where('type', 'commission')
                        ->where('user_id', $Payroll->user_id)
                        ->where('clawback_type', 'next payroll')
                        ->where('pay_period_from', $Payroll->pay_period_from)
                        ->where('pay_period_to', $Payroll->pay_period_to)
                        ->where(function ($subQuery) {
                            $subQuery->where('status', 6)
                                ->orWhere('is_move_to_recon', 1);
                        });
                })
                ->get();
            $companyProfile = CompanyProfile::first();
            // return $clawbackSettlement;
            $subtotal = 0;
            if (count($usercommission) > 0) {
                foreach ($usercommission as $key => $value) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'commission', 'type' => $value->amount_type])->first();

                    $saleProduct = SaleProductMaster::where(['pid' => $value->pid, 'type' => $value->schema_type])->first();
                    $type = $value->schema_type.' Payment';
                    if ($value->amount_type == 'm2 update') {
                        $type = $value->schema_type.' Payment Update';
                    }
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sale_data = SalesMaster::with(['salesMasterProcessInfo'])
                            ->where('pid', $value->pid)
                            ->select('id', 'customer_signoff', 'product_id')
                            ->first();

                        $approvedDate = $sale_data?->customer_signoff;

                        $commissionHistory = UserCommissionHistory::where('user_id', $value->user_id)
                            ->where('product_id', $sale_data?->product_id)
                            ->where('commission_effective_date', '<=', $approvedDate)
                            ->orderByDesc('commission_effective_date')
                            ->orderByDesc('id')
                            ->select('commission', 'commission_type')
                            ->first();
                        $service_schedule = ($commissionHistory)
                            ? '$ '.$commissionHistory->commission.' '.preg_replace('/(\d)(?=[a-zA-Z])/', '$1 ', $commissionHistory->commission_type)
                            : null;
                    } else {
                        $service_schedule = isset($value->salesDetail->service_schedule) ? $value->salesDetail->service_schedule : null;
                    }

                    $repRedline = null;
                    if ($value->redline_type) {
                        if (in_array($value->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                            $repRedline = $value->redline.' Per Watt';
                        } else {
                            $repRedline = $value->redline.' '.ucwords($value->redline_type);
                        }
                    }
                    $compRate = 0;
                    if ($companyProfile->company_type == CompanyProfile::MORTGAGE_COMPANY_TYPE && $value->commission_type !== 'per sale') {
                        $compRate = $value->comp_rate ? $value->comp_rate : ($value->net_epc - $value->redline);
                    }
                    $data['data'][] = [
                        'id' => $value->id,
                        'pid' => $value->pid,
                        'is_mark_paid' => $value->is_mark_paid,
                        'customer_name' => isset($value->saledata->customer_name) ? $value->saledata->customer_name : null,
                        'customer_state' => isset($value->saledata->customer_state) ? strtoupper($value->saledata->customer_state) : null,
                        // 'rep_redline' => isset($value->userdata->redline) ? $value->userdata->redline : null,
                        'rep_redline' => $repRedline,
                        'comp_rate' => number_format($compRate, 4, '.', ''),
                        'kw' => isset($value->kw) ? $value->kw : null,
                        'net_epc' => isset($value->net_epc) ? $value->net_epc : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        // 'date' => isset($value->date) ? $value->date : null,
                        'date' => isset($saleProduct->milestone_date) ? $saleProduct->milestone_date : null,
                        'pay_period_from' => isset($value->pay_period_from) ? $value->pay_period_from : null,
                        'pay_period_to' => isset($value->pay_period_to) ? $value->pay_period_to : null,
                        'amount_type' => $type,
                        'adders' => isset($value->saledata->adders) ? $value->saledata->adders : null,
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'product' => isset($value->saledata->product_code) ? $value->saledata->product_code : null,
                        'gross_value' => isset($value->saledata->gross_account_value) ? $value->saledata->gross_account_value : null,
                        // 'service_schedule' => isset($value->saledata->service_schedule) ? $value->saledata->service_schedule : null,
                        'service_schedule' => isset($service_schedule) ? $service_schedule : null,
                        'is_move_to_recon' => $value->is_move_to_recon,
                        'commission_amount' => @$value->commission_amount ?? null,
                        'commission_type' => @$value->commission_type ?? null,

                    ];
                    $subtotal = ($subtotal + $value->amount);
                }
                $data['subtotal'] = $subtotal;
            }

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $key1 => $val) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $val->pid, 'payroll_type' => 'commission', 'type' => 'clawback'])->first();
                    if (in_array($companyProfile->company_type, CompanyProfile::PEST_COMPANY_TYPE)) {
                        $sale_data = SalesMaster::with(['salesMasterProcessInfo'])
                            ->where('pid', $val->pid)
                            ->select('id', 'customer_signoff', 'product_id')
                            ->first();

                        $approvedDate = $sale_data?->customer_signoff;

                        $commissionHistory = UserCommissionHistory::where('user_id', $val->user_id)
                            ->where('product_id', $sale_data?->product_id)
                            ->where('commission_effective_date', '<=', $approvedDate)
                            ->orderByDesc('commission_effective_date')
                            ->orderByDesc('id')
                            ->select('commission', 'commission_type')
                            ->first();
                        $service_schedule = ($commissionHistory)
                            ? '$ '.$commissionHistory->commission.' '.preg_replace('/(\d)(?=[a-zA-Z])/', '$1 ', $commissionHistory->commission_type)
                            : null;
                    } else {
                        $service_schedule = isset($val->salesDetail->service_schedule) ? $val->salesDetail->service_schedule : null;
                    }

                    $repRedline = null;
                    if ($val->redline_type) {
                        if (in_array($val->redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                            $repRedline = $val->redline.' Per Watt';
                        } else {
                            $repRedline = $val->redline.' '.ucwords($val->redline_type);
                        }
                    }

                    $data['data'][] = [
                        'id' => $val->id,
                        'pid' => $val->pid,
                        'is_mark_paid' => $val->is_mark_paid,
                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'customer_state' => isset($val->salesDetail->customer_state) ? strtoupper($val->salesDetail->customer_state) : null,
                        'rep_redline' => $repRedline,
                        'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                        'amount' => isset($val->clawback_amount) ? (0 - $val->clawback_amount) : null,
                        'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                        'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                        'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                        'amount_type' => 'clawback',
                        'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'product' => isset($val->salesDetail->product_code) ? $val->salesDetail->product_code : null,
                        'gross_value' => isset($val->salesDetail->gross_account_value) ? $val->salesDetail->gross_account_value : null,
                        // 'service_schedule' => isset($value->salesDetail->service_schedule) ? $value->salesDetail->service_schedule : null,
                        'service_schedule' => isset($service_schedule) ? $service_schedule : null,
                        'is_move_to_recon' => $val->is_move_to_recon,
                        'commission_amount' => @$val->clawback_cal_amount ?? null,
                        'commission_type' => @$val->clawback_cal_type ?? null,
                    ];
                    $subtotal = ($subtotal - $val->clawback_amount);
                }
                $data['subtotal'] = $subtotal;
            }

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'commission_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    public function payStubOverrideDetails(Request $request): JsonResponse
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $Payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        // $payroll = PayrollHistory::where(['user_id' => $user_id,'pay_period_from' => $pay_period_from,'pay_period_to' => $pay_period_to])->first();

        $sub_total = 0;

        if (! empty($Payroll)) {
            if ($Payroll->status == 3) {
                $userdata = UserOverridesLock::with('salesDetail')->whereIn('status', [$Payroll->status, 6])->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            } else {
                $userdata = UserOverridesLock::with('salesDetail')->where('status', '<', '3')->where(['user_id' => $Payroll->user_id, 'overrides_settlement_type' => 'during_m2', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get();
            }

            /* $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')->where(['type' =>  'overrides', 'user_id' =>  $Payroll->user_id, 'clawback_type' => 'next payroll', 'pay_period_from' => $Payroll->pay_period_from, 'pay_period_to' => $Payroll->pay_period_to])->get(); */
            $clawbackSettlement = ClawbackSettlementLock::with('users', 'salesDetail')
                ->where(function ($query) use ($Payroll) {
                    $query->where('type', 'overrides')
                        ->where('user_id', $Payroll->user_id)
                        ->where('clawback_type', 'next payroll')
                        ->where('pay_period_from', $Payroll->pay_period_from)
                        ->where('pay_period_to', $Payroll->pay_period_to);
                })
                ->orWhere(function ($query) use ($Payroll) {
                    $query->where('type', 'overrides')
                        ->where('user_id', $Payroll->user_id)
                        ->where('clawback_type', 'next payroll')
                        ->where('pay_period_from', $Payroll->pay_period_from)
                        ->where('pay_period_to', $Payroll->pay_period_to)
                        ->where(function ($subQuery) {
                            $subQuery->where('status', 6)
                                ->orWhere('is_move_to_recon', 1);
                        });
                })
                ->get();
            if (count($userdata) > 0) {

                foreach ($userdata as $key => $value) {

                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $value->pid, 'payroll_type' => 'overrides', 'type' => $value->type])->first();

                    $user = User::with('state')->where(['id' => $value->sale_user_id])->first();
                    $sale = SalesMaster::where(['pid' => $value->pid])->first();
                    $sub_total = ($sub_total + $value->amount);

                    $redLineType = $value->calculated_redline_type;
                    if (in_array($value->calculated_redline_type, config('global_vars.REDLINE_TYPE_ARRAY'))) {
                        $redLineType = 'percent';
                    }

                    $data['data'][] = [
                        'id' => $value->sale_user_id,
                        'is_mark_paid' => $value->is_mark_paid,
                        // 'is_move_to_recon' => $value?->is_move_to_recon,
                        'pid' => $value->pid,
                        'first_name' => isset($user->first_name) ? $user->first_name : null,
                        'last_name' => isset($user->last_name) ? $user->last_name : null,
                        'position_id' => isset($user->position_id) ? $user->position_id : null,
                        'sub_position_id' => isset($user->sub_position_id) ? $user->sub_position_id : null,
                        'is_super_admin' => isset($user->is_super_admin) ? $user->is_super_admin : null,
                        'is_manager' => isset($user->is_manager) ? $user->is_manager : null,
                        'image' => isset($user->image) ? $user->image : null,
                        'type' => isset($value->type) ? $value->type : null,
                        'accounts' => 1,
                        'kw_installed' => $value->kw,
                        'total_amount' => $value->amount,
                        'override_type' => $value->overrides_type,
                        'override_amount' => $value->overrides_amount,
                        'calculated_redline' => $value->calculated_redline,
                        'state' => isset($user->state) ? $user->state->state_code : null,
                        'm2_date' => isset($sale->m2_date) ? $sale->m2_date : null,
                        'customer_name' => isset($sale->customer_name) ? $sale->customer_name : null,
                        'amount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'is_move_to_recon' => $value->is_move_to_recon,
                        'product' => isset($value->salesDetail->product_code) ? $value->salesDetail->product_code : null,
                        'calculated_redline_type' => $redLineType,
                        'dismiss' => isset($user->id) && isUserDismisedOn($user->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($user->id) && isUserTerminatedOn($user->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($user->id) && isUserContractEnded($user->id) ? 1 : 0,
                    ];
                }
                $data['sub_total'] = $sub_total;
            }

            if (count($clawbackSettlement) > 0) {
                foreach ($clawbackSettlement as $key1 => $val) {
                    $adjustmentAmount = PayrollAdjustmentDetailLock::where(['payroll_id' => $id, 'user_id' => $Payroll->user_id, 'pid' => $val->pid, 'payroll_type' => 'overrides', 'type' => 'clawback'])->first();
                    $data['data'][] = [
                        'id' => $val->id,
                        'pid' => $val->pid,
                        'is_mark_paid' => $val->is_mark_paid,

                        'first_name' => isset($val->users->first_name) ? $val->users->first_name : null,
                        'last_name' => isset($val->users->last_name) ? $val->users->last_name : null,
                        'position_id' => isset($val->users->position_id) ? $val->users->position_id : null,
                        'sub_position_id' => isset($val->users->sub_position_id) ? $val->users->sub_position_id : null,
                        'is_super_admin' => isset($val->users->is_super_admin) ? $val->users->is_super_admin : null,
                        'is_manager' => isset($val->users->is_manager) ? $val->users->is_manager : null,
                        'image' => isset($val->users->image) ? $val->users->image : null,
                        // 'type' => isset($val->type) ? $val->type : null,
                        'type' => 'Clawback',

                        'customer_name' => isset($val->salesDetail->customer_name) ? $val->salesDetail->customer_name : null,
                        'customer_state' => isset($val->salesDetail->customer_state) ? $val->salesDetail->customer_state : null,
                        'rep_redline' => isset($val->users->redline) ? $val->users->redline : null,
                        'kw' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'kw_installed' => isset($val->salesDetail->kw) ? $val->salesDetail->kw : null,
                        'net_epc' => isset($val->salesDetail->net_epc) ? $val->salesDetail->net_epc : null,
                        'total_amount' => isset($val->clawback_amount) ? (-1 * $val->clawback_amount) : 0,
                        'amount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'date' => isset($val->salesDetail->date_cancelled) ? $val->salesDetail->date_cancelled : null,
                        'pay_period_from' => isset($val->pay_period_from) ? $val->pay_period_from : null,
                        'pay_period_to' => isset($val->pay_period_to) ? $val->pay_period_to : null,
                        'amount_type' => 'clawback',
                        'adders' => isset($val->salesDetail->adders) ? $val->salesDetail->adders : null,
                        'adjustAmount' => isset($adjustmentAmount->amount) ? $adjustmentAmount->amount : 0,
                        'product' => isset($value->salesDetail->product_code) ? $value->salesDetail->product_code : null,
                        'gross_value' => isset($value->salesDetail->gross_account_value) ? $value->salesDetail->gross_account_value : null,
                        'service_schedule' => isset($value->salesDetail->service_schedule) ? $value->salesDetail->service_schedule : null,
                        'dismiss' => isset($val->users->id) && isUserDismisedOn($val->users->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($val->users->id) && isUserTerminatedOn($val->users->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($val->users->id) && isUserContractEnded($val->users->id) ? 1 : 0,
                    ];
                    $subtotal = ($sub_total - $val->clawback_amount);
                }
                $data['sub_total'] = $subtotal;
            }

            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll->status,
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'override_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    public function payStubPayrollDeductionsByEmployeeId(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $payroll = GetPayrollData::where(['id' => $request->payroll_id, 'user_id' => $request->user_id, 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to])->first();

        // if (!empty($payroll)) {
        // $Payroll_status = $payroll->status;
        // }else{
        //      $payroll = PayrollHistory::where(['payroll_id' => $request->payroll_id])->first();
        //      $Payroll_status = $payroll->status;
        // }
        $paydata = [];
        $Payroll_status = '';
        if (! empty($payroll)) {
            $Payroll_status = $payroll->status;
            $paydata = PayrollDeductions::with('costcenter')->where('user_id', $payroll->user_id)->where('payroll_id', $request->payroll_id)->get();
        }

        $response_arr = [];
        $subtotal = 0;
        foreach ($paydata as $d) {
            $subtotal = $d->subtotal;
            $response_arr[] = [
                'Type' => $d->costcenter->name,
                'Amount' => $d->amount,
                'Limit' => $d->limit,
                'Total' => $d->total,
                'Outstanding' => $d->outstanding,
                'cost_center_id' => $d->cost_center_id,
            ];
        }

        $response = ['list' => $response_arr, 'subtotal' => $subtotal];

        return response()->json([
            'ApiName' => 'payroll_Deductions_By_EmployeeId',
            'status' => true,
            'message' => 'Successfully.',
            'payroll_status' => $Payroll_status,
            'data' => $response,
        ], 200);
    }

    public function payStubAdjustmentDetails(Request $request): JsonResponse
    {
        // echo"asd";die;
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();
        // $payroll = PayrollHistory::where(['user_id' => $user_id,'pay_period_from' => $pay_period_from,'pay_period_to' => $pay_period_to])->first();
        // dd($payroll);
        if (! empty($payroll)) {
            $adjustment = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [1, 3, 4, 6, 13])->get();
            $adjustmentNegative = ApprovalsAndRequestLock::with('user', 'approvedBy', 'adjustment')->where('status', 'Paid')->where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->whereIn('adjustment_type_id', [5])->get();
            // dd($adjustmentNegative);

            if (count($adjustment) > 0) {
                foreach ($adjustment as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'dismiss' => isset($value->approvedBy->id) && isUserDismisedOn($value->approvedBy->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($value->approvedBy->id) && isUserTerminatedOn($value->approvedBy->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($value->approvedBy->id) && isUserContractEnded($value->approvedBy->id) ? 1 : 0,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            if (count($adjustmentNegative) > 0) {
                foreach ($adjustmentNegative as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'dismiss' => isset($value->approvedBy->id) && isUserDismisedOn($value->approvedBy->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($value->approvedBy->id) && isUserTerminatedOn($value->approvedBy->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($value->approvedBy->id) && isUserContractEnded($value->approvedBy->id) ? 1 : 0,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? (0 - $value->amount) : null,
                        'type' => isset($value->adjustment) ? $value->adjustment->name : null,
                        'description' => isset($value->description) ? $value->description : null,
                        'is_mark_paid' => $value->is_mark_paid,
                    ];
                }
            }

            //  /// Added by Gorakh
            $PayrollHistoryPayrollIDs = PayrollHistory::where(['user_id' => $payroll->user_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->pluck('payroll_id');
            $PayrollAdjustmentDetail = PayrollAdjustmentDetailLock::whereIn('payroll_id', $PayrollHistoryPayrollIDs)->where(['user_id' => $payroll->user_id])->get();
            // dd($PayrollAdjustmentDetail);
            if (count($PayrollAdjustmentDetail) > 0) {
                foreach ($PayrollAdjustmentDetail as $key => $value) {
                    if ($value->pid) {
                        $customer = SalesMaster::where('pid', $value->pid)->first();
                        $customer_name = $customer->customer_name;
                    } else {
                        $customer_name = '';
                    }
                    $checkUserCommission = UserCommissionLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $checkUserOverrides = UserOverridesLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    $ClawbackSettlements = ClawbackSettlementLock::where(['user_id' => $value->user_id, 'payroll_id' => $value->payroll_id])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to, 'is_mark_paid' => '1', 'status' => '3'])->first();
                    if ($checkUserCommission || $checkUserOverrides || $ClawbackSettlements) {
                        $is_mark_paid = 1;
                    } else {
                        $is_mark_paid = 0;
                    }

                    // Approved user
                    $approvUser = $value->commented_by;

                    $data[] = [
                        'id' => $value->user_id,
                        'first_name' => $approvUser?->first_name,
                        'last_name' => $approvUser?->last_name,
                        'image' => null,
                        'dismiss' => isset($approvUser->id) && isUserDismisedOn($approvUser->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($approvUser->id) && isUserTerminatedOn($approvUser->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($approvUser->id) && isUserContractEnded($approvUser->id) ? 1 : 0,
                        // 'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'date' => isset($value->created_at) ? date('Y-m-d', strtotime($value->created_at)) : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'type' => $value->payroll_type,
                        'description' => $value->comment,
                        'is_mark_paid' => $is_mark_paid,
                        'customer_name' => $customer_name,

                    ];
                }
            }

            // End Gorakh

            // code  start by nikhil

            // commentted by gorakh this isssue fixed just top

            // $dataAdjustment = PayrollAdjustmentLock::with('detail')->where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->get();

            // $totalAmount = DB::table('payroll_adjustments')->where(['payroll_id' => $payroll->id])->where('user_id', $payroll->user_id)
            // ->sum(\DB::raw('commission_amount + overrides_amount + adjustments_amount + reimbursements_amount + deductions_amount + reconciliations_amount + clawbacks_amount'));

            // if (count( $dataAdjustment) > 0) {

            //     foreach ( $dataAdjustment as $key => $val) {

            //         if($val->commission_amount>0 || $val->commission_amount<0){
            //             $comment = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
            //             $data[] = [
            //                 'id' => $val->user_id,
            //                 'first_name' => 'Super',
            //                 'last_name' => 'Admin',
            //                 'image' => null,
            //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //                 'amount' => $val->commission_amount,
            //                 'type' => 'Commission',
            //                 'description' => isset($comment['comment']) ? $comment['comment'] : null,
            //             ];

            //         }
            //         if($val->overrides_amount>0 || $val->overrides_amount<0){
            //             $comment = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
            //             $data[] = [
            //                 'id' => $val->user_id,
            //                 'first_name' => 'Super',
            //                 'last_name' => 'Admin',
            //                 'image' => null,
            //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //                 'amount' =>  $val->overrides_amount,
            //                 'type' => 'Overrides',
            //                 'description' => isset($comment['comment']) ? $comment['comment'] : null,
            //             ];

            //         }
            //         if($val->adjustments_amount>0 || $val->adjustments_amount<0){
            //             $comment = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
            //             $data[] = [
            //                 'id' => $val->user_id,
            //                 'first_name' => 'Super',
            //                 'last_name' => 'Admin',
            //                 'image' => null,
            //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //                 'amount' => $val->adjustments_amount,
            //                 'type' => 'Adjustments',
            //                 'description' => isset($comment['comment']) ? $comment['comment'] : null,
            //             ];

            //         }
            //         if($val->reimbursements_amount>0 || $val->reimbursements_amount<0){
            //             $comment = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
            //             $data[] = [
            //                 'id' => $val->user_id,
            //                 'first_name' => 'Super',
            //                 'last_name' => 'Admin',
            //                 'image' => null,
            //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //                 'amount' => $val->reimbursements_amount,
            //                 'type' => 'Reimbursements',
            //                 'description' => isset($comment['comment']) ? $comment['comment'] : null,
            //             ];

            //         }
            //         if($val->deductions_amount>0 || $val->deductions_amount<0){
            //             $comment = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
            //             $data[] = [
            //                 'id' => $val->user_id,
            //                 'first_name' => 'Super',
            //                 'last_name' => 'Admin',
            //                 'image' => null,
            //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //                 'amount' => $val->deductions_amount,
            //                 'type' => 'Deductions',
            //                 'description' => isset($comment['comment']) ? $comment['comment'] : null,
            //             ];

            //         }
            //         if($val->reconciliations_amount>0 || $val->reconciliations_amount<0){
            //             $comment = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
            //             $data[] = [
            //                 'id' => $val->user_id,
            //                 'first_name' => 'Super',
            //                 'last_name' => 'Admin',
            //                 'image' => null,
            //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //                 'amount' => $val->reconciliations_amount,
            //                 'type' => 'Reconciliations',
            //                 'description' => isset($comment['comment']) ? $comment['comment'] : null,
            //             ];

            //         }
            //         if($val->clawbacks_amount>0 ||$val->clawbacks_amount<0){
            //             $comment = PayrollAdjustmentDetailLock::where(['payroll_id' => $payroll->id])->where(['user_id' => $payroll->user_id])->first();
            //             $data[] = [
            //                 'id' => $val->user_id,
            //                 'first_name' => 'Super',
            //                 'last_name' => 'Admin',
            //                 'image' => null,
            //                 'date' => isset($val->created_at) ? date('Y-m-d',strtotime($val->created_at)): null,
            //                 'amount' =>$val->clawbacks_amount,
            //                 'type' => 'Clawback',
            //                 'description' => isset($comment['comment']) ? $comment['comment'] : null,
            //             ];

            //         }

            //     }
            // }
            // code end by Gorakh

            // code end by nikhil

            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll->status,
                'data' => $data,

            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'adjustment_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    public function payStubReimbursementDetails(Request $request)
    {
        $data = [];
        $Validator = Validator::make(
            $request->all(),
            [
                'id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }

        $id = $request->id;
        $user_id = $request->user_id;
        $pay_period_from = $request->pay_period_from;
        $pay_period_to = $request->pay_period_to;

        $payroll = GetPayrollData::where(['id' => $id, 'user_id' => $user_id, 'pay_period_from' => $pay_period_from, 'pay_period_to' => $pay_period_to])->first();

        // $payroll = PayrollHistory::where(['user_id' => $user_id,'pay_period_from' => $pay_period_from,'pay_period_to' => $pay_period_to])->first();

        $payroll_status = '';
        if (! empty($payroll)) {

            $reimbursement = ApprovalsAndRequestLock::with('user', 'approvedBy')->where('status', 'Paid')->where(['user_id' => $payroll->user_id, 'adjustment_type_id' => '2'])->where(['pay_period_from' => $payroll->pay_period_from, 'pay_period_to' => $payroll->pay_period_to])->get();
            // return $reimbursement;
            if (count($reimbursement) > 0) {
                foreach ($reimbursement as $key => $value) {
                    $data[] = [
                        'id' => $value->user_id,
                        'is_mark_paid' => $value->is_mark_paid,
                        'first_name' => isset($value->approvedBy->first_name) ? $value->approvedBy->first_name : null,
                        'last_name' => isset($value->approvedBy->last_name) ? $value->approvedBy->last_name : null,
                        'image' => isset($value->approvedBy->image) ? $value->approvedBy->image : null,
                        'dismiss' => isset($value->approvedBy->id) && isUserDismisedOn($value->approvedBy->id, date('Y-m-d')) ? 1 : 0,
                        'terminate' => isset($value->approvedBy->id) && isUserTerminatedOn($value->approvedBy->id, date('Y-m-d')) ? 1 : 0,
                        'contract_ended' => isset($value->approvedBy->id) && isUserContractEnded($value->approvedBy->id) ? 1 : 0,
                        'date' => isset($value->cost_date) ? $value->cost_date : null,
                        'amount' => isset($value->amount) ? $value->amount : null,
                        'description' => isset($value->description) ? $value->description : null,
                    ];
                }
            }

            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $payroll_status,
                'data' => $data,
            ], 200);
        } else {
            return response()->json([
                'ApiName' => 'reimbursement_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    public function payStubDeductionsDetails(Request $request): JsonResponse
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $payroll = GetPayrollData::where(['id' => $request->payroll_id, 'user_id' => $request->user_id, 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to])->first();

        $paydata = [];
        $Payroll_status = '';
        if (! empty($payroll)) {
            $Payroll_status = $payroll->status;
            $paydata = PayrollDeductionLock::with('costcenter:id,name,status')
                ->leftjoin('payroll_adjustment_details_lock', function ($join) {
                    $join->on('payroll_adjustment_details_lock.payroll_id', '=', 'payroll_deduction_locks.payroll_id')
                        ->on('payroll_adjustment_details_lock.cost_center_id', '=', 'payroll_deduction_locks.cost_center_id');
                })
                ->where('payroll_deduction_locks.user_id', $payroll->user_id)
                ->where('payroll_deduction_locks.payroll_id', $request->payroll_id)
                ->where('payroll_deduction_locks.is_next_payroll', 0)
                ->where(function ($query) {
                    $query->where('payroll_deduction_locks.outstanding', '!=', 0)
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('payroll_deduction_locks.outstanding', '=', 0)
                                ->where(function ($q) {
                                    $q->whereNull('payroll_deduction_locks.cost_center_id')
                                        ->orWhereHas('costcenter', function ($q2) {
                                            $q2->where('status', 1);
                                        });
                                });
                        });
                })
                ->select('payroll_deduction_locks.*', 'payroll_adjustment_details_lock.amount as adjustment_amount')
                ->get();

            $response_arr = [];
            $subtotal = 0;
            foreach ($paydata as $d) {
                if ($d->is_mark_paid == 0 && $d->is_next_payroll == 0 && $d->is_move_to_recon == 0) {
                    $subtotal += $d->total;
                }
                // $subtotal = $d->subtotal;
                $response_arr[] = [
                    'id' => $d->id,
                    'payroll_id' => $d->payroll_id,
                    'is_mark_paid' => $d->is_mark_paid,
                    'is_next_payroll' => $d->is_next_payroll,
                    'is_move_to_recon' => $d->is_move_to_recon,
                    'Type' => $d->costcenter->name,
                    'Amount' => $d->amount,
                    'Limit' => $d->limit,
                    'Total' => $d->total,
                    'Outstanding' => $d->outstanding,
                    'cost_center_id' => $d->cost_center_id,
                    'adjustment_amount' => isset($d->adjustment_amount) ? $d->adjustment_amount : 0,
                ];
            }

            $response = ['list' => $response_arr, 'subtotal' => $subtotal];

            return response()->json([
                'ApiName' => 'payroll_Deductions_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll_status,
                'data' => $response,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'payroll_Deductions_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    public function payStubWagesDetails(Request $request)
    {
        $Validator = Validator::make(
            $request->all(),
            [
                'payroll_id' => 'required', // 15
                'user_id' => 'required',
                'pay_period_from' => 'required',
                'pay_period_to' => 'required',
                // 'user_id'    => 'required', // 11
            ]
        );
        if ($Validator->fails()) {
            return response()->json(['error' => $Validator->errors()], 400);
        }
        $payroll = PayrollHistory::where(['payroll_id' => $request->payroll_id, 'user_id' => $request->user_id, 'pay_period_from' => $request->pay_period_from, 'pay_period_to' => $request->pay_period_to])->first();
        $paydata = [];
        $Payroll_status = '';
        if (! empty($payroll)) {
            $Payroll_status = $payroll->status;
            $paydata = PayrollHourlySalaryLock::with('userdata')
                ->leftjoin('payroll_overtimes_lock', function ($join) {
                    $join->on('payroll_overtimes_lock.payroll_id', '=', 'payroll_hourly_salary_lock.payroll_id')
                        ->on('payroll_overtimes_lock.user_id', '=', 'payroll_hourly_salary_lock.user_id')
                        ->on('payroll_overtimes_lock.date', '=', 'payroll_hourly_salary_lock.date');
                })
                // ->leftjoin("payroll_adjustment_details",function($join){
                //     $join->on("payroll_adjustment_details.payroll_id","=","payroll_hourly_salary_lock.payroll_id")
                //         ->on("payroll_adjustment_details.user_id","=","payroll_hourly_salary_lock.user_id");
                // })
                ->where('payroll_hourly_salary_lock.user_id', $payroll->user_id)
                ->where('payroll_hourly_salary_lock.payroll_id', $request->payroll_id)
                ->where('payroll_hourly_salary_lock.is_next_payroll', 0)
                ->select('payroll_hourly_salary_lock.*', 'payroll_overtimes_lock.overtime', 'payroll_overtimes_lock.total as overtime_total')
                ->get();

            // return $paydata;

            $response_arr = [];
            $total = 0;
            $subtotal = 0;
            $totalSeconds = 0;
            $totalHours = 0;
            $totalOvertime = 0;
            foreach ($paydata as $d) {
                // if ($d->is_mark_paid == 0 && $d->is_next_payroll == 0) {
                //     $subtotal += $d->total;
                // }
                $total = ($d->total + $d->overtime_total);
                $subtotal += $total;

                if (! empty($d->regular_hours)) {
                    $timeA = Carbon::createFromFormat('H:i', $d->regular_hours);
                    $secondsA = $timeA->hour * 3600 + $timeA->minute * 60;
                    $totalSeconds = $totalSeconds + $secondsA;
                    // $totalHours = $this->hoursformat($totalSeconds);
                    // $totalHours = Carbon::parse($totalHours)->format('H:i');
                }

                if (! empty($d->overtime)) {
                    $totalOvertime = $totalOvertime + $d->overtime;
                }

                $response_arr[] = [
                    'id' => $d->id,
                    'payroll_id' => $d->payroll_id,
                    'is_mark_paid' => $d->is_mark_paid,
                    'is_next_payroll' => $d->is_next_payroll,
                    'date' => $d->date,
                    'hourly_rate' => $d->hourly_rate,
                    'overtime_rate' => $d->overtime_rate,
                    'salary' => $d->salary,
                    'regular_hours' => $d->regular_hours,
                    'overtime' => $d->overtime,
                    'total' => $total,
                    'adjustment_amount' => 0,
                ];
            }

            $totalHours = ($totalSeconds > 0) ? ($totalSeconds / 3600) : 0;

            $totalData = [
                'total_amount' => $subtotal,
                'total_regular_hour' => number_format($totalHours, 2),
                'total_overtime' => number_format($totalOvertime, 2),
            ];

            $response = ['list' => $response_arr, 'subtotal' => $totalData];

            return response()->json([
                'ApiName' => 'payroll_wages_details',
                'status' => true,
                'message' => 'Successfully.',
                'payroll_status' => $Payroll_status,
                'data' => $response,
            ], 200);
        } else {

            return response()->json([
                'ApiName' => 'payroll_wages_details',
                'status' => true,
                'message' => 'No Records.',
                'data' => [],
            ], 200);
        }
    }

    protected function hoursformat($seconds)
    {
        $thours = intdiv($seconds, 3600); // Get the hours part
        $tminutes = intdiv($seconds % 3600, 60); // Get the minutes part
        $tseconds = $seconds % 60; // Get the remaining seconds part

        return sprintf('%02d:%02d:%02d', $thours, $tminutes, $tseconds);
    }
}
