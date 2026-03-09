<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CloserIdentifyAlert;
use App\Models\CompanyProfile;
use App\Models\LegacyApiNullData;
use App\Models\SaleMasterProcess;
use App\Models\SalesMaster;
use App\Models\User;
use App\Models\UserReconciliationWithholding;
use App\Models\UsersAdditionalEmail;
use App\Services\SalesCalculationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CloserIdentifyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function closerData(): JsonResponse
    {
        $user = auth('api')->user();
        $email = $user->email;
        $closer = CloserIdentifyAlert::get();

        return response()->json([
            'ApiName' => 'Closer missing list ',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $closer,
        ], 200);
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

    public function getCloserDropdown(): JsonResponse
    {
        $data = User::with('office', 'additionalRedline', 'reconciliations', 'upfront', 'positionpayfrequencies')->where('dismiss', 0)->where('position_id', 2)->orWhere('self_gen_type', 2)->where('dismiss', 0)->get();

        return response()->json([
            'ApiName' => 'closer_list',
            'status' => true,
            'message' => 'Successfully.',
            'data' => $data,
        ], 200);

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

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     */
    public function updateSalesProcessInCloser(Request $request): JsonResponse
    {
        $pid = $request->pid;
        $closer = $request->closer;

        $UpdateData = SaleMasterProcess::where('pid', $pid)->first();
        if (count($closer) > 1) {
            $UpdateData->closer1_id = $closer[0];
            $UpdateData->closer2_id = $closer[1];
        } else {
            $UpdateData->closer1_id = $closer[0];
        }
        $UpdateData->save();

        if ($closer) {
            $closerData = User::where('id', $closer)->first();
            $legacySetterId = LegacyApiNullData::where('pid', $pid)->where('sales_rep_email', null)->where('sales_rep_name', null)->Update(['sales_rep_email' => $closerData->email, 'sales_rep_name' => $closerData->first_name.$closerData->last_name]);
        }

        // subroutine  process
        // $data = $this->subroutine_process($pid);

        return response()->json([
            'ApiName' => 'Update Closer Id for sales',
            'status' => true,
            'message' => 'Successfully.',
            // 'data' => $UpdateData,
        ], 200);
    }

    public function subroutine_process($pid)
    {
        $checked = SalesMaster::with('salesMasterProcess')->where('pid', $pid)->first();

        if (!$checked) {
            return;
        }

        // Set context for custom field conversion (Trick Subroutine approach)
        // This enables auto-conversion of 'custom field' to 'per sale' in model events
        $companyProfile = SalesCalculationContext::getCachedCompanyProfile() ?? CompanyProfile::first();

        // Check if Custom Sales Fields feature is enabled for this company
        $isCustomFieldsEnabled = \App\Helpers\CustomSalesFieldHelper::isFeatureEnabled($companyProfile);

        try {
            // Only set context when Custom Sales Fields feature is enabled
            // This ensures zero impact on companies without the feature
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::set($checked, $companyProfile);
            }

            $dateCancelled = $checked->date_cancelled;
            $m1_date = $checked->m1_date;
            $m2_date = $checked->m2_date;
            $epc = $checked->epc;
            $netEpc = $checked->net_epc;
            $customerState = $checked->customer_state;
            $kw = $checked->kw;

            $m1_this_week = $checked->m1_amount;
            $m2_this_week = isset($checked->m2_amount) ? $checked->m2_amount : '';
            $approvedDate = $checked->customer_signoff;

            $setter1_id = $checked->salesMasterProcess->setter1_id;
            $setter2_id = $checked->salesMasterProcess->setter2_id;

            // Is there a clawback date = dateCancelled ?
            if ($dateCancelled) {
            // run subroutine 5
            $subroutineFive = $this->subroutineFive($checked);

            $yesStep = 'Have any payments already been issued? ';

        } else {
            // check Is there an M1 Date?
            if ($m1_date) {

                // check  Has M1 already been paid?
                if ($m1_this_week != null) {

                    // check  Is there an M2 Date?
                    if ($m2_date != null) {
                        // Run Subroutine 6
                        $subroutineSix = $this->SubroutineSix($checked);

                    } else {
                        // No Further Action Required
                    }
                } else {
                    // Run Subroutine 1
                    $subRoutine = $this->subroutineOne($checked);

                    if ($m2_date != null) {
                        // Run Subroutine 6
                        $subroutineSix = $this->SubroutineSix($checked);

                        // Redline Value
                        // Run Subroutine #8 (Total Commission)
                        $subroutineEight = $this->SubroutineEight($checked);

                        // Has M2 already been paid?
                        if (isset($m2_this_week) && $m2_this_week) {
                            // Does total paid match total from Subroutine #8?
                            $pullTotalCommission = ($subroutineEight['closer_commission'] + $subroutineEight['setter_commission']);
                            $saleMasterProcess = SaleMasterProcess::where('pid', $checked->pid)->first();
                            $totalPaid = ($saleMasterProcess->closer1_commission + $saleMasterProcess->setter1_commission);
                            if ($totalPaid != $pullTotalCommission) {
                                // Run Subroutine #12 (Sale Adjustments)
                                $subroutineTwelve = $this->SubroutineTwelve($checked);
                            }
                        } else {

                            $closer = User::where('email', $checked->sales_rep_email)->first();
                            if (empty($closer)) {
                                $additional_user_id = UsersAdditionalEmail::where('email', $checked->sales_rep_email)->value('user_id');
                                if (! empty($additional_user_id)) {
                                    $closer = User::where('id', $additional_user_id)->first();
                                }
                            }
                            if (isset($closer) && $closer != '') {
                                $closerReconciliationWithholding = UserReconciliationWithholding::where('closer_id', $closer->id)->sum('withhold_amount');

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

                        $subroutineThree = $this->SubroutineThree($checked);

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
        } finally {
            // Only clear the context if it was set (feature is enabled)
            if ($isCustomFieldsEnabled) {
                SalesCalculationContext::clear();
            }
        }
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
}
