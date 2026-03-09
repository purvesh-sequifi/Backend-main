<?php

namespace App\Http\Controllers\API\Excel;

use App\Core\Traits\SetterSubroutineListTrait;
use App\Exports\ClawbackDataExport;
use App\Http\Controllers\Controller;
use App\Imports\LegacyImport;
use App\Imports\LegacyImportRowApi;
use App\Imports\OnboardUserImport;
use App\Imports\UserImport;
use App\Models\CloserIdentifyAlert;
use App\Models\CompanyProfile;
use App\Models\CrmSetting;
use App\Models\ExcelImportHistory;
use App\Models\ImportExpord;
use App\Models\LegacyApiNullData;
use App\Models\LegacyApiRowData;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\SetterIdentifyAlert;
use App\Models\User;
use App\Models\UserExcelImportHistory;
use App\Models\UserReconciliationWithholding;
use App\Models\UsersAdditionalEmail;
use App\Traits\EmailNotificationTrait;
// use App\Core\Traits\SubroutineListTrait;
use Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ExcelImportExportController extends Controller
{
    use EmailNotificationTrait;

    // use SubroutineListTrait;
    use SetterSubroutineListTrait;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        // Excel::import(new LegacyImport, $request->file('file')->store('files'));
        $upload = $request->file('file');
        $filepath = $upload->getRealpath();
        Excel::import(new LegacyImport, $filepath);
        // Excel::import(new LegacyImportRowApi, $filepath);
        $newData = \DB::table('legacy_api_raw_data as lad')->select('lad.pid', 'lad.weekly_sheet_id', 'lad.install_partner', 'lad.homeowner_id', 'lad.proposal_id', 'lad.install_partner_id', 'lad.kw', 'lad.setter_id', 'lad.proposal_id', 'lad.customer_name', 'lad.customer_address', 'lad.customer_address_2', 'lad.customer_city', 'lad.customer_state', 'lad.customer_zip', 'lad.customer_email', 'lad.customer_phone', 'lad.employee_id', 'lad.sales_rep_name', 'lad.sales_rep_email', 'lad.customer_signoff', 'lad.m1_date', 'lad.scheduled_install', 'lad.install_complete_date', 'lad.m2_date', 'lad.date_cancelled', 'lad.return_sales_date', 'lad.gross_account_value', 'lad.cash_amount', 'lad.loan_amount', 'lad.dealer_fee_percentage', 'lad.adders', 'lad.adders_description', 'lad.funding_source', 'lad.financing_rate', 'lad.financing_term', 'lad.product')
            ->get();

        // Update data by previous comparison in Sales_Master
        foreach ($newData as $checked) {

            $excelData = \DB::table('legacy_excel_raw_data as ld')->select('ld.id', 'ld.epc', 'ld.net_epc', 'ld.m1_date', 'ld.m2_date', 'ld.cancel_date', 'ld.redline', 'ld.m1_this_week', 'ld.install_m2_this_week', 'ld.total_in_period', 'ld.last_date_pd', 'ld.prev_paid', 'ld.total_for_acct', 'ld.approved_date', 'ld.cancel_fee', 'ld.cancel_deduction', 'ld.lead_cost', 'ld.adv_pay_back_amount', 'ld.dealer_fee_dollar', 'ld.prev_deducted')
                ->where('ld.pid', $checked->pid)
                ->latest()->first();

            $dateCancelled = $checked->date_cancelled;
            $m1_date = $checked->m1_date;

            if ($checked->m2_date != null) {
                $m2_date = $checked->m2_date;
            } else {
                $m2_date = isset($excelData->m2_date) ? $excelData->m2_date : null;
            }

            $m1_this_week = isset($excelData->m1_this_week) ? $excelData->m1_this_week : '0';
            $m2_this_week = isset($excelData->install_m2_this_week) ? $excelData->install_m2_this_week : '0';

            // check return sales date
            $val = [
                'pid' => $checked->pid,
                'kw' => $checked->kw,
                'weekly_sheet_id' => $checked->weekly_sheet_id,
                'install_partner' => $checked->install_partner,
                'install_partner_id' => $checked->install_partner_id,
                'customer_name' => $checked->customer_name,
                'customer_address' => $checked->customer_address,
                'customer_address_2' => $checked->customer_address_2,
                'customer_city' => $checked->customer_city,
                'customer_state' => $checked->customer_state,
                'customer_zip' => $checked->customer_zip,
                'customer_email' => $checked->customer_email,
                'customer_phone' => $checked->customer_phone,
                'homeowner_id' => $checked->homeowner_id,
                'proposal_id' => $checked->proposal_id,
                'sales_rep_name' => $checked->sales_rep_name,
                'employee_id' => $checked->employee_id,
                'sales_rep_email' => $checked->sales_rep_email,
                'date_cancelled' => $dateCancelled,
                'customer_signoff' => $checked->customer_signoff,
                'm1_date' => $m1_date,
                'm2_date' => $m2_date,
                'product' => $checked->product,
                'gross_account_value' => $checked->gross_account_value,
                'dealer_fee_percentage' => $checked->dealer_fee_percentage,
                'adders' => $checked->adders,
                'adders_description' => $checked->adders_description,
                'funding_source' => $checked->funding_source,
                'financing_rate' => $checked->financing_rate,
                'financing_term' => $checked->financing_term,
                'scheduled_install' => $checked->scheduled_install,
                'install_complete_date' => $checked->install_complete_date,
                'return_sales_date' => $checked->return_sales_date,
                'cash_amount' => $checked->cash_amount,
                'loan_amount' => $checked->loan_amount,
                'epc' => isset($excelData->epc) ? $excelData->epc : null,
                'net_epc' => isset($excelData->net_epc) ? $excelData->net_epc : null,
                'dealer_fee_amount' => isset($excelData->dealer_fee_dollar) ? $excelData->dealer_fee_dollar : null,
                'redline' => isset($excelData->redline) ? $excelData->redline : null,
                'total_amount_for_acct' => isset($excelData->total_for_acct) ? $excelData->total_for_acct : null,
                'prev_amount_paid' => isset($excelData->prev_paid) ? $excelData->prev_paid : null,
                'last_date_pd' => isset($excelData->last_date_pd) ? $excelData->last_date_pd : null,
                'm1_amount' => $m1_this_week,
                'm2_amount' => $m2_this_week,
                'prev_deducted_amount' => isset($excelData->prev_deducted) ? $excelData->prev_deducted : null,
                'cancel_fee' => isset($excelData->cancel_fee) ? $excelData->cancel_fee : null,
                'cancel_deduction' => isset($excelData->cancel_deduction) ? $excelData->cancel_deduction : null,
                'lead_cost_amount' => isset($excelData->lead_cost) ? $excelData->lead_cost : null,
                'adv_pay_back_amount' => isset($excelData->adv_pay_back_amount) ? $excelData->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($excelData->total_in_period) ? $excelData->total_in_period : null,
            ];

            $calculate = SalesMaster::where('pid', $checked->pid)->first();
            // dd($calculate);
            if (! isset($calculate) && $calculate == '' || $calculate == null) {
                $insertData = SalesMaster::create($val);

                $data = [
                    'sale_master_id' => $insertData->id,
                    'weekly_sheet_id' => $insertData->weekly_sheet_id,
                    'pid' => $checked->pid,
                ];
                SaleMasterProcess::create($data);
            } else {
                $updateData = SalesMaster::where('pid', $checked->pid)->update($val);
            }

        }

        return response()->json([
            'ApiName' => 'import_excel_api',
            'status' => true,
            'message' => 'Upload Sheet Successfully',
            // 'data'    => $data,
        ], 200);
    }

    public function excelImport(Request $request)
    {

        // $upload=$request->file('file');
        // $filepath=$upload->getRealpath();
        // Excel::import(new LegacyImport, $filepath);

        $path1 = $request->file('file')->store('temp');
        $path = storage_path('app').'/'.$path1;
        Excel::import(new LegacyImport, $path);

        $excelData = ImportExpord::get();

        // Update data by previous comparison in Sales_Master
        foreach ($excelData as $checked) {
            // echo $checked->sales_rep_email;die;
            // $newData = \DB::table('legacy_api_raw_data as lad')->select('lad.pid','lad.weekly_sheet_id','lad.install_partner','lad.homeowner_id','lad.proposal_id','lad.install_partner_id', 'lad.kw', 'lad.setter_id', 'lad.proposal_id', 'lad.customer_name', 'lad.customer_address', 'lad.customer_address_2', 'lad.customer_city', 'lad.customer_state','lad.customer_zip','lad.customer_email','lad.customer_phone','lad.employee_id', 'lad.sales_rep_name', 'lad.sales_rep_email','lad.customer_signoff', 'lad.m1_date', 'lad.scheduled_install', 'lad.install_complete_date', 'lad.m2_date', 'lad.date_cancelled', 'lad.return_sales_date', 'lad.gross_account_value', 'lad.cash_amount', 'lad.loan_amount', 'lad.dealer_fee_percentage', 'lad.adders', 'lad.adders_description', 'lad.funding_source', 'lad.financing_rate', 'lad.financing_term','lad.product')->where('pid', $checked->pid)->first();

            $checkApiDada = LegacyApiRowData::where('pid', $checked->pid)->first();

            $data['legacy_data_id'] = isset($checked->id) ? $checked->id : null;
            $data['weekly_sheet_id'] = isset($checked->weekly_sheet_id) ? $checked->weekly_sheet_id : null;
            $data['page'] = isset($checked->pageid) ? $checked->pageid : null;
            $data['pid'] = isset($checked->pid) ? $checked->pid : null;
            $data['homeowner_id'] = isset($checked->homeowner_id) ? $checked->homeowner_id : null;
            $data['proposal_id'] = isset($checked->proposal_id) ? $checked->proposal_id : null;
            $data['customer_name'] = isset($checked->customer_name) ? $checked->customer_name : null;
            $data['customer_address'] = isset($checked->customer_address) ? $checked->customer_address : null;
            $data['customer_address_2'] = isset($checked->customer_address_2) ? $checked->customer_address_2 : null;
            $data['customer_city'] = isset($checked->customer_city) ? $checked->customer_city : null;
            $data['customer_state'] = isset($checked->state) ? $checked->state : null;
            $data['customer_zip'] = isset($checked->customer_zip) ? $checked->customer_zip : null;
            $data['customer_email'] = isset($checked->customer_email) ? $checked->customer_email : null;
            $data['customer_phone'] = isset($checked->customer_phone) ? $checked->customer_phone : null;
            $data['setter_id'] = isset($checked->setter_id) ? $checked->setter_id : null;
            $data['employee_id'] = isset($checked->employee_id) ? $checked->employee_id : null;
            $data['sales_rep_name'] = isset($checked->sales_rep_name) ? $checked->sales_rep_name : null;
            $data['sales_rep_email'] = isset($checked->sales_rep_email) ? $checked->sales_rep_email : null;
            // $data['sales_setter_email'] = isset($checked->sales_setter_email) ? $checked->sales_setter_email : null;
            $data['install_partner'] = isset($checked->install_partner) ? $checked->install_partner : null;
            $data['install_partner_id'] = isset($checked->install_partner_id) ? $checked->install_partner_id : null;
            $data['customer_signoff'] = isset($checked->approved_date) && $checked->approved_date != null ? date('Y-m-d H:i:s', strtotime($checked->approved_date)) : null;
            $data['m1_date'] = isset($checked->m1_date) ? date('Y-m-d H:i:s', strtotime($checked->m1_date)) : null;
            $data['scheduled_install'] = isset($checked->scheduled_install) ? date('Y-m-d H:i:s', strtotime($checked->scheduled_install)) : null;
            $data['m2_date'] = isset($checked->m2_date) ? date('Y-m-d H:i:s', strtotime($checked->m2_date)) : null;
            $data['date_cancelled'] = isset($checked->cancel_date) ? date('Y-m-d H:i:s', strtotime($checked->cancel_date)) : null;
            $data['return_sales_date'] = isset($checked->return_sales_date) ? date('Y-m-d H:i:s', strtotime($checked->return_sales_date)) : null;
            $data['gross_account_value'] = isset($checked->gross_account_value) ? $checked->gross_account_value : null;
            $data['cash_amount'] = isset($checked->cash_amount) ? $checked->cash_amount : null;
            $data['loan_amount'] = isset($checked->loan_amount) ? $checked->loan_amount : null;
            $data['kw'] = isset($checked->kw) ? $checked->kw : null;
            $data['epc'] = isset($checked->epc) ? $checked->epc : null;
            $data['net_epc'] = isset($checked->net_epc) ? $checked->net_epc : null;
            $data['dealer_fee_percentage'] = isset($checked->dealer_fee_percentage) ? $checked->dealer_fee_percentage : null;
            $data['adders'] = isset($checked->adders) ? $checked->adders : null;
            $data['adders_description'] = isset($checked->adders_description) ? $checked->adders_description : null;
            $data['funding_source'] = isset($checked->funding_source) ? $checked->funding_source : null;
            $data['financing_rate'] = isset($checked->financing_rate) ? $checked->financing_rate : 0.00;
            $data['financing_term'] = isset($checked->financing_term) ? $checked->financing_term : null;
            $data['product'] = isset($checked->product) ? $checked->product : null;
            if ($checkApiDada) {
                $updateData = LegacyApiRowData::where('pid', $checked->pid)->update($data);
            } else {
                $inserted = LegacyApiRowData::create($data);
            }

            $newApiDada = LegacyApiRowData::where('pid', $checked->pid)->first();
            $dateCancelled = null;
            if ($checked->cancel_date != null) {
                $dateCancelled = $checked->cancel_date;
            } else {
                $dateCancelled = $checked->inactive_date;
            }

            $m1_date = $checked->m1_date;
            $m2_date = $checked->m2_date;
            // check return sales date
            $val = [
                'pid' => $checked->pid,
                'kw' => $checked->kw,
                'weekly_sheet_id' => $checked->weekly_sheet_id,
                'install_partner' => $newApiDada->install_partner,
                'install_partner_id' => $newApiDada->install_partner_id,
                'customer_name' => $newApiDada->customer_name,
                'customer_address' => $newApiDada->customer_address,
                'customer_address_2' => $newApiDada->customer_address_2,
                'customer_city' => $newApiDada->customer_city,
                'customer_state' => $newApiDada->customer_state,
                'customer_zip' => $newApiDada->customer_zip,
                'customer_email' => $newApiDada->customer_email,
                'customer_phone' => $newApiDada->customer_phone,
                'homeowner_id' => $newApiDada->homeowner_id,
                'proposal_id' => $newApiDada->proposal_id,
                'sales_rep_name' => $newApiDada->sales_rep_name,
                'employee_id' => $newApiDada->employee_id,
                'sales_rep_email' => $newApiDada->sales_rep_email,
                // 'sales_setter_email'  => $newApiDada->sales_setter_email,
                'date_cancelled' => $dateCancelled,
                'customer_signoff' => $newApiDada->customer_signoff,
                'm1_date' => $m1_date,
                'm2_date' => $m2_date,
                'product' => $newApiDada->product,
                'gross_account_value' => $newApiDada->gross_account_value,
                'dealer_fee_percentage' => $newApiDada->dealer_fee_percentage,
                'adders' => $newApiDada->adders,
                'adders_description' => $newApiDada->adders_description,
                'funding_source' => $newApiDada->funding_source,
                'financing_rate' => $newApiDada->financing_rate,
                'financing_term' => $newApiDada->financing_term,
                'scheduled_install' => $newApiDada->scheduled_install,
                'install_complete_date' => $newApiDada->install_complete_date,
                'return_sales_date' => $newApiDada->return_sales_date,
                'cash_amount' => $newApiDada->cash_amount,
                'loan_amount' => $newApiDada->loan_amount,
                'epc' => isset($checked->epc) ? $checked->epc : null,
                'net_epc' => isset($checked->net_epc) ? $checked->net_epc : null,
                'dealer_fee_amount' => isset($checked->dealer_fee_dollar) ? $checked->dealer_fee_dollar : null,
                'redline' => isset($checked->redline) ? $checked->redline : null,
                'total_amount_for_acct' => isset($checked->total_for_acct) ? $checked->total_for_acct : null,
                'prev_amount_paid' => isset($checked->prev_paid) ? $checked->prev_paid : null,
                'last_date_pd' => isset($checked->last_date_pd) ? $checked->last_date_pd : null,
                'm1_amount' => $checked->m1_this_week,
                'm2_amount' => $checked->install_m2_this_week,
                'prev_deducted_amount' => isset($checked->prev_deducted) ? $checked->prev_deducted : null,
                'cancel_fee' => isset($checked->cancel_fee) ? $checked->cancel_fee : null,
                'cancel_deduction' => isset($checked->cancel_deduction) ? $checked->cancel_deduction : null,
                'lead_cost_amount' => isset($checked->lead_cost) ? $checked->lead_cost : null,
                'adv_pay_back_amount' => isset($checked->adv_pay_back_amount) ? $checked->adv_pay_back_amount : null,
                'total_amount_in_period' => isset($checked->total_in_period) ? $checked->total_in_period : null,
            ];

            if ($checked->sales_setter_email != null) {
                $setter = User::where('email', $checked->sales_setter_email)->where('position_id', 3)->first();
                if (empty($setter)) {
                    $additional_user_id = UsersAdditionalEmail::where('email', $checked->sales_setter_email)->value('user_id');
                    if (! empty($additional_user_id)) {
                        $setter = User::where('id', $additional_user_id)->where('position_id', 3)->first();
                    }
                }
                // $setter = User::where('email', $checked->sales_setter_email)->first();
                $setterId = isset($setter->id) ? $setter->id : null;
            } else {
                $setterId = null;
            }

            if ($checked->sales_rep_email != null) {
                $closer = User::where('email', $checked->sales_rep_email)->first();
                if (empty($closer)) {
                    $additional_user_id = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->value('user_id');
                    if (! empty($additional_user_id)) {
                        $closer = User::where('id', $additional_user_id)->first();
                    }
                }
                $closerId = isset($closer->id) ? $closer->id : null;
            } else {
                $closerId = null;
            }

            $calculate = SalesMaster::where('pid', $checked->pid)->first();
            // dd($calculate);
            if (! isset($calculate) && $calculate == '' || $calculate == null) {
                $insertData = SalesMaster::create($val);

                $data1 = [
                    'sale_master_id' => $insertData->id,
                    'weekly_sheet_id' => $insertData->weekly_sheet_id,
                    'pid' => $checked->pid,
                    'closer1_id' => $closerId,
                    'setter1_id' => $setterId,
                ];
                SaleMasterProcess::create($data1);
            } else {
                $updateData = SalesMaster::where('pid', $checked->pid)->update($val);
            }
            $pidDeleteInNullTable = LegacyApiNullData::where('pid', $checked->pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
            if ($pidDeleteInNullTable != '') {
                $UpdateData = LegacyApiNullData::where('pid', $checked->pid)->whereNotNull('data_source_type')->orderBy('id', 'desc')->first();
                $UpdateData->status = 'Resolved';
                $UpdateData->action_status = 1;
                $UpdateData->save();
            }

        }
        // die('true');
        $newData = SalesMaster::with('salesMasterProcess')->orderBy('id', 'asc')->get();
        // Is there a clawback date = dateCancelled ?

        foreach ($newData as $checked) {
            $dateCancelled = $checked->date_cancelled;
            $m1_date = $checked->m1_date;
            $m2_date = $checked->m2_date;
            $epc = $checked->epc;
            $netEpc = $checked->net_epc;
            $approvedDate = $checked->customer_signoff;
            $customerState = $checked->customer_state;
            $kw = $checked->kw;

            $m1_paid_status = isset($checked->salesMasterProcess->setter1_m1_paid_status) ? $checked->salesMasterProcess->setter1_m1_paid_status : null;
            $m2_paid_status = isset($checked->salesMasterProcess->setter1_m2_paid_status) ? $checked->salesMasterProcess->setter1_m2_paid_status : null;

            $closer1_id = isset($checked->salesMasterProcess->closer1_id) ? $checked->salesMasterProcess->closer1_id : null;
            $closer2_id = isset($checked->salesMasterProcess->closer2_id) ? $checked->salesMasterProcess->closer2_id : null;
            $setter1_id = isset($checked->salesMasterProcess->setter1_id) ? $checked->salesMasterProcess->setter1_id : null;
            $setter2_id = isset($checked->salesMasterProcess->setter2_id) ? $checked->salesMasterProcess->setter2_id : null;

            // check return sales date
            if ($dateCancelled) {
                if ($checked->salesMasterProcess->mark_account_status_id == 1 || $checked->salesMasterProcess->mark_account_status_id == 6) {
                    // 'No clawback calculations required ';
                } else {
                    // 'Have any payments already been issued? ';
                    if ($m1_paid_status == 4 || $m2_paid_status == 8) {
                        // run subroutine 5
                        $subroutineFive = $this->subroutineFive($checked);
                    } else {
                        // All pending payments or due payments are set to zero.
                        $reconciliationWithholding = UserReconciliationWithholding::where(['pid' => $checked->pid])
                            ->update(['withhold_amount' => '0', 'status' => 'canceled']);

                        $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                        $saleMasterProcess->closer1_m1 = 0;
                        $saleMasterProcess->closer2_m1 = 0;
                        $saleMasterProcess->setter1_m1 = 0;
                        $saleMasterProcess->setter2_m1 = 0;
                        $saleMasterProcess->closer1_m2 = 0;
                        $saleMasterProcess->closer2_m2 = 0;
                        $saleMasterProcess->setter1_m2 = 0;
                        $saleMasterProcess->setter2_m2 = 0;
                        $saleMasterProcess->mark_account_status_id = 6;
                        $saleMasterProcess->save();

                    }
                }

            } else {
                $subRoutine = $this->subroutineOne($checked);
                if ($subRoutine == false) {
                    continue;
                }
                // check Is there an M1 Date?
                if ($m1_date) {

                    // check Has M1 already been paid?
                    if ($m1_paid_status == 4) {

                        // check  Is there an M2 Date?
                        if ($m2_date != null) {
                            // Run Subroutine 6 (Redline)
                            $subroutineSix = $this->subroutineSix($checked);

                            // Run Subroutine #8 (Total Commission)
                            $subroutineEight = $this->SubroutineEight($checked);

                            // Has M2 already been paid?
                            if ($m2_paid_status == 5) {
                                // Does total paid match total from Subroutine #8?
                                $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                                $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                                if (round($totalPaid) != round($pullTotalCommission)) {
                                    // Run Subroutine #12 (Sale Adjustments)
                                    $subroutineTwelve = $this->SubroutineTwelve($checked);
                                }
                            } else {

                                if (isset($setter1_id) && $setter1_id != '') {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where('closer_id', $closer->id)->sum('withhold_amount');
                                    // echo $closer->id;die;

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($setter2_id) && $setter2_id != '') {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['setter_id' => $setter2_id, 'pid' => $checked->pid])->sum('withhold_amount');

                                    if ($closerReconciliationWithholding > 0) {
                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps
                                    }
                                }
                            }
                        } else {
                            // No Further Action Required
                        }
                    } else {
                        // Run Subroutine 1
                        $subRoutine = $this->subroutineOne($checked);
                        if ($subRoutine == false) {
                            continue;
                        }

                        if ($m2_date != null) {

                            // Run Subroutine 6 (Redline)
                            $subroutineSix = $this->subroutineSix($checked);

                            // Run Subroutine #8 (Total Commission)
                            $subroutineEight = $this->subroutineEight($checked);

                            // Has M2 already been paid?
                            if ($m2_paid_status == 5) {
                                // Does total paid match total from Subroutine #8?
                                $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                                $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                                $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->closer2_commission + $saleMasterProcess->setter1_commission + $saleMasterProcess->setter2_commission);
                                if (round($totalPaid) != round($pullTotalCommission)) {
                                    // Run Subroutine #12 (Sale Adjustments)
                                    $subroutineTwelve = $this->SubroutineTwelve($checked);
                                }

                            } else {

                                if (isset($closer1_id) && $closer1_id != '') {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer1_id, 'pid' => $checked->pid])->sum('withhold_amount');
                                    // echo $closer->id;die;

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps

                                    }
                                }

                                if (isset($closer2_id) && $closer2_id != '') {
                                    $closerReconciliationWithholding = UserReconciliationWithholding::where(['closer_id' => $closer2_id, 'pid' => $checked->pid])->sum('withhold_amount');
                                    // echo $closer->id;die;

                                    if ($closerReconciliationWithholding > 0) {

                                        $subroutineTen = $this->subroutineTen($checked);
                                    } else {

                                        $subroutineNine = $this->subroutineNine($checked);
                                        // Admin is alerted that M2 value was negative for this user System proceeds onto following steps
                                    }
                                }
                            }

                        } else {

                            // Run Subroutine #3 (M1 Payment)
                            $subroutineThree = $this->subroutineThree($checked);

                            // No Further Action Required

                        }
                    }

                } else {
                    $UpdateData = SaleMasterProcess::where('pid', $checked->pid)->first();
                    if (isset($UpdateData) && $UpdateData != '') {
                        $UpdateData->mark_account_status_id = 2;
                        $UpdateData->save();
                    }
                }
            }
        }

        // send email admin and closer for identity update
        $closerAlert = CloserIdentifyAlert::get();
        $setterAlert = SetterIdentifyAlert::groupBy('sales_rep_email')->get();

        // delete data
        foreach ($closerAlert as $val) {
            $dataDelete = CloserIdentifyAlert::find($val->id);
            // $dataDelete->delete();
        }
        foreach ($setterAlert as $vals) {
            $dataDelete = SetterIdentifyAlert::find($vals->id);
            // $dataDelete->delete();
        }

        return response()->json([
            'ApiName' => 'import_excel_api',
            'status' => true,
            'message' => 'Upload Sheet Successfully',
            // 'data'    => $data,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function LegacyData(Request $request): JsonResponse
    {

        $data = [
            'username' => $request['username'],
            'password' => $request['password'],
        ];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://lgcy-analytics.com/api/api-token-auth', // your preferred url
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                // Set here requred headers
                'accept: */*',
                'accept-language: en-US,en;q=0.8',
                'content-type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $res = json_decode($response);
        $token = isset($res->token) ? $res->token : '';
        if ($token) {
            $value = [];
            $value['username'] = isset($request['username']) ? $request['username'] : '';
            $value['password'] = isset($request['password']) ? $request['password'] : '';
            $value['data_fetch_frequency'] = isset($request['data_fetch_frequency']) ? $request['data_fetch_frequency'] : '';
            $value['time'] = isset($request['time']) ? $request['time'] : '';
            $value['timezone'] = isset($request['timezone']) ? $request['timezone'] : '';
            $data['value'] = json_encode($value);
            $data['company_id'] = isset($request['company_id']) ? $request['company_id'] : '';
            $data['crm_id'] = 1;
            $company = CrmSetting::where('company_id', $request['company_id'])->first();
            if (empty($company)) {
                $inserted = CrmSetting::create($data);

                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => true,
                    'message' => 'Successfully',
                    'data' => $inserted,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'CRM Setting API',
                    'status' => false,
                    'message' => 'Company id already exit',
                    // 'data'    => $inserted,
                ], 400);
            }
        } else {
            return response()->json([
                'ApiName' => 'CRM Setting API',
                'status' => false,
                'message' => 'These credentials do not match .',
                // 'data'    => $inserted,
            ], 400);
        }
        curl_close($curl);

    }

    public function LegacyDataUpdate(Request $request): JsonResponse
    {

        $data = [
            'username' => $request['username'],
            'password' => $request['password'],
        ];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://lgcy-analytics.com/api/api-token-auth', // your preferred url
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30000,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                // Set here requred headers
                'accept: */*',
                'accept-language: en-US,en;q=0.8',
                'content-type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $res = json_decode($response);
        $token = isset($res->token) ? $res->token : '';

        if ($token) {
            $value = [];
            $value['username'] = isset($request['username']) ? $request['username'] : '';
            $value['password'] = isset($request['password']) ? $request['password'] : '';
            $value['data_fetch_frequency'] = isset($request['data_fetch_frequency']) ? $request['data_fetch_frequency'] : '';
            $value['time'] = isset($request['time']) ? $request['time'] : '';
            $value['timezone'] = isset($request['timezone']) ? $request['timezone'] : '';
            $data['value'] = json_encode($value);
            $data['company_id'] = isset($request['company_id']) ? $request['company_id'] : '';
            $data['crm_id'] = 1;
            $data = CrmSetting::where('company_id', $request['company_id'])->first();
            if (isset($data)) {
                $data->value = json_encode($value);
                $data->save();

                return response()->json([
                    'ApiName' => 'update legacy setting',
                    'status' => true,
                    'message' => 'Upload Sheet Successfully',
                    'data' => $value,
                ], 200);
            } else {
                return response()->json([
                    'ApiName' => 'update legacy setting',
                    'status' => false,
                    'message' => 'Company Id not find in table',
                ], 404);
            }
        } else {
            return response()->json([
                'ApiName' => 'CRM Setting API',
                'status' => false,
                'message' => 'These credentials do not match.',
                // 'data'    => $inserted,
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        //
    }

    public function UserImport(Request $request): JsonResponse
    {
        $upload = $request->file('file');
        $filepath = $upload->getRealpath();
        Excel::import(new UserImport, $filepath);

        return response()->json([
            'ApiName' => 'import_excel_api',
            'status' => true,
            'message' => 'Upload Sheet Successfully',
            // 'data'    => $data,
        ], 200);
    }

    public function onboardingUserImport(Request $request): JsonResponse
    {
        $user_id = Auth::user()->id;

        DB::beginTransaction();
        $ImportUsers = new OnboardUserImport;
        $ImportUsers->companyProfile = CompanyProfile::first();
        $ImportUsers->total_records = 0;
        $ImportUsers->import_id = time();
        Excel::import($ImportUsers, $request->file('file'));

        if ($ImportUsers->status) {
            // STORE FILE ON S3 PRIVATE BUCKET
            $original_file_name = str_replace(' ', '_', $request->file('file')->getClientOriginalName());
            $file_name = config('app.domain_name').'/'.'excel_uploads/'.$ImportUsers->import_id.'_'.$original_file_name;
            s3_upload($file_name, $request->file('file'), true);

            UserExcelImportHistory::create([
                'user_id' => $user_id,
                'uploaded_file' => $file_name,
                'total_records' => $ImportUsers->total_records,
            ]);

            $status_code = 200;
            $status = $ImportUsers->status;
            $msg = $ImportUsers->message;
            DB::commit();
        } else {
            DB::rollback();
            $status_code = 400;
            $status = $ImportUsers->status;
            $msg = $ImportUsers->message;
        }

        return response()->json([
            'ApiName' => 'onboard_user_import_excel_api',
            'status' => $status,
            'message' => $msg,
            'error' => $ImportUsers->errors,
        ], $status_code);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        //
    }

    public function exportClawbackData(Request $request)
    {
        $file_name = 'clawback_'.date('Y_m_d_H_i_s').'.xlsx';
        if (isset($request->office_id) && isset($request->filter)) {
            $officeId = $request->office_id;
            $filterDataDateWise = $request->input('filter');
            $filterDate = getFilterDate($filterDataDateWise);
            if (! empty($filterDate['startDate']) && ! empty($filterDate['endDate'])) {
                $startDate = $filterDate['startDate'];
                $endDate = $filterDate['endDate'];
            } elseif ($filterDataDateWise == 'custom' && $request->input('start_date') && $request->input('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
            } else {
                return response()->json([
                    'ApiName' => 'Get Clawback Reports API',
                    'status' => false,
                    'message' => 'Failed',
                ], 400);
            }
            /* if ($request->filter == 'this_year') {
                $now = Carbon::now();
                $monthStart = $now->startOfYear();
                $startDate = date('Y-m-d', strtotime($monthStart));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $officeId = $request->office_id;

            } else
            if ($request->filter == 'last_year') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->startOfYear()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subYears(1)->endOfYear()));
                $officeId = $request->office_id;

            } else
            if ($request->filter == 'this_month') {
                $new = Carbon::now(); //returns current day
                $firstDay = $new->firstOfMonth();
                $startDate = date('Y-m-d', strtotime($firstDay));
                $end = Carbon::now();
                $endDate = date('Y-m-d', strtotime($end));
                $officeId = $request->office_id;

            }else
            if($request->filter=='this_week')
            {
                $currentDate = \Carbon\Carbon::now();
                $startDate =  date('Y-m-d', strtotime(now()->subDays($currentDate->dayOfWeek)));
                $endDate =  date('Y-m-d', strtotime(now()));
                $officeId = $request->office_id;
            }
            else
            if ($request->filter == 'last_month') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->startOfMonth()));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->subMonth()->endOfMonth()));
                $officeId = $request->office_id;
            }
            else if ($request->filter == 'last_12_months') {
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(12)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()));
                $officeId = $request->office_id;
            } else
            if ($request->filter == 'this_quarter') {
                $currentMonthDay = Carbon::now()->daysInMonth + Carbon::now()->month(01)->daysInMonth + Carbon::now(03)->month()->daysInMonth;
                $month = \Carbon\Carbon::now()->subMonths()->daysInMonth;
                $startDate = date('Y-m-d', strtotime(Carbon::now()->subMonths(3)->addDays(30)));
                $endDate = date('Y-m-d', strtotime(Carbon::now()->addDays(0)));
                $officeId = $request->office_id;
            } else if ($request->filter == 'custom') {
                $sDate = $request->input('start_date');
                $eDate = $request->input('end_date');
                $startDate = date('Y-m-d', strtotime($sDate));
                $endDate   = date('Y-m-d', strtotime($eDate));
                $officeId = $request->office_id;
            } */
            $search = $request->search;
            $requestData = [
                'officeId' => $officeId,
                'search' => $search,
                'startDates' => $startDate,
                'endDates' => $endDate,
            ];
            Excel::store(new \App\Exports\ExportReports\Clawback\ClawbackReportExport($requestData),
                'exports/reports/clawback/'.$file_name,
                'public',
                \Maatwebsite\Excel\Excel::XLSX
            );
            // return Excel::download(new ClawbackDataExport($officeId, $search,$startDate, $endDate), $file_name);
        } else {
            $search = $request->search;
            $officeId = $request->office_id;
            $requestData = [
                'officeId' => $officeId,
                'search' => $search,
            ];
            Excel::store(new \App\Exports\ExportReports\Clawback\ClawbackReportExport($requestData),
                'exports/reports/clawback/'.$file_name,
                'public',
                \Maatwebsite\Excel\Excel::XLSX);
            // return Excel::download(new ClawbackDataExport($officeId,$search), $file_name);
        }

        $url = getStoragePath('exports/reports/clawback/'.$file_name);

        // $url = getExportBaseUrl().'storage/exports/reports/clawback/' . $file_name;
        // Return the URL in the API response
        return response()->json(['url' => $url]);
    }

    public function getexcelImportList(Request $request)
    {
        if (! empty($request->perpage)) {
            $perpage = $request->perpage;
        } else {
            $perpage = 10;
        }

        $importList = ExcelImportHistory::with('users')->withCount(['legacyHistory' => function ($q) {
            $q->where('import_to_sales', '0');
        }])
        /* ** Calculate the progress percentage based on record counts ** */
            ->addSelect([
                \DB::raw('ROUND(GREATEST(0, LEAST(100, ((new_records + updated_records + error_records) / NULLIF(total_records, 0)) * 100 )), 2) AS progress_percentage'),
            ])
            ->orderBy('id', 'DESC')->paginate($perpage);

        // Performance Optimization: Bulk S3 URL generation to avoid N+1 queries
        $aws = getAwsS3Client();
        $s3Client = $aws['client'];
        $bucket = $aws['bucket'];

        // Extract and deduplicate all uploaded files to avoid N+1 queries
        $uniqueFiles = collect($importList->items())
            ->pluck('uploaded_file')
            ->filter()
            ->unique();

        // Generate all presigned URLs in bulk instead of individual S3 API calls
        $presignedUrls = $uniqueFiles->mapWithKeys(function ($file) use ($s3Client, $bucket) {
            $url = optional(check_s3_getTempPresignedUrl($file, $s3Client, $bucket))['presignedUrl'] ?? null;

            return [$file => $url];
        });

        $response = [];
        $processing = false;
        $stuckImportThresholdHours = 24;

        foreach ($importList as $data) {
            $file = $data['uploaded_file'] ?? null;

            if (! $processing && $data['legacy_history_count'] != 0) {
                $processing = true;
            }

            // Use Carbon for more efficient date formatting
            $dataCreated = \Carbon\Carbon::parse($data['created_at'])->format('Y-m-d\TH:i:s.000000\Z');

            // AUTO-FIX: Detect and fix stuck imports (status=1, progress=0, older than threshold)
            if ($data->status == 1 &&
                (float) $data->progress_percentage == 0 &&
                now()->diffInHours($data->created_at) >= $stuckImportThresholdHours &&
                empty($data->errors)) {

                $linkedCount = \App\Models\LegacyApiRawDataHistory::where('excel_import_id', $data->id)
                    ->where('data_source_type', 'excel')
                    ->count();

                $errorType = $linkedCount === 0 ? 'no_records_found' : 'processing_stalled';
                $errorMessage = $linkedCount === 0
                    ? 'Import failed: No records were found to process. Please re-upload your file.'
                    : "Import processing stalled. Found {$linkedCount} records but processing never completed.";

                $errorDetails = [
                    'error_type' => $errorType,
                    'message' => $errorMessage,
                    'technical_details' => "Import was stuck in processing state for " . now()->diffInHours($data->created_at) . " hours. Auto-detected and marked as failed.",
                    'linked_records_count' => $linkedCount,
                    'auto_fixed' => true,
                    'fixed_at' => now()->toIso8601String(),
                    'timestamp' => $data->created_at,
                ];

                try {
                    ExcelImportHistory::where('id', $data->id)->update([
                        'status' => 2,
                        'errors' => json_encode($errorDetails),
                    ]);

                    $data->status = 2;
                    $data->errors = json_encode($errorDetails);

                    \Log::info('Auto-fixed stuck Excel import (legacy endpoint)', [
                        'excel_id' => $data->id,
                        'age_hours' => now()->diffInHours($data->created_at),
                        'linked_count' => $linkedCount,
                    ]);
                } catch (\Throwable $e) {
                    \Log::error('Failed to auto-fix stuck import (legacy endpoint)', [
                        'excel_id' => $data->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Decode errors JSON field if it exists
            $errors = null;
            if (!empty($data->errors)) {
                try {
                    $errors = is_string($data->errors) ? json_decode($data->errors, true) : $data->errors;
                } catch (\Throwable $e) {
                    $errors = null;
                }
            }

            $response[] = [
                'id' => $data->id,
                'user_id' => $data->user_id,
                'uploaded_file' => $data->uploaded_file,
                'new_records' => $data->new_records,
                'updated_records' => $data->updated_records,
                'error_records' => $data->error_records,
                'total_records' => $data->total_records,
                'created_at' => $dataCreated,
                'legacy_history_count' => $data->legacy_history_count,
                'uploaded_file_s3' => $file ? ($presignedUrls[$file] ?? null) : null,
                'progress_percentage' => $data->progress_percentage,
                'errors' => $errors, // Include error details for frontend display
                'users' => $data->users,
                // PIDs tracking for debugging
                'updated_pids' => $data->updated_pids ?? [],
                'new_pids' => $data->new_pids ?? [],
                'error_pids' => $data->error_pids ?? [],
            ];
        }

        $importList = $importList->toArray();
        $importList['data'] = $response;

        return response()->json([
            'ApiName' => 'get_excel_import_list',
            'status' => true,
            'processing' => $processing,
            'data' => $importList,
        ]);
    }

    public function getUserexcelImportList(): JsonResponse
    {
        $import_list = UserExcelImportHistory::with('users')->get()->toArray();
        if (! empty($import_list)) {
            foreach ($import_list as $key => $row) {
                if (! empty($row['uploaded_file'])) {
                    $import_list[$key]['uploaded_file_s3'] = s3_getTempUrl($row['uploaded_file']);
                }
            }

            return response()->json([
                'ApiName' => 'get_user_excel_import_list',
                'status' => true,
                'data' => $import_list,
            ], 200);
        }

        return response()->json([
            'ApiName' => 'get_user_excel_import_list',
            'status' => true,
            'data' => [],
        ], 200);
    }
}
