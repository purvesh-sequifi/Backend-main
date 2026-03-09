<?php

namespace App\Http\Controllers\API\V2\Payroll;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PayrollMarkPaidController extends Controller
{
    public function userMarkAsPaid(Request $request)
    {
        return updatePayrollStatus($request, 'is_next_payroll', ['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0], 1);
    }

    public function userMarkAsUnpaid(Request $request)
    {
        return updatePayrollStatus($request, 'is_next_payroll', ['is_mark_paid' => 1, 'is_next_payroll' => 0, 'is_onetime_payment' => 0]);
    }

    public function singleMarkAsPaid(Request $request)
    {
        return updateSinglePayrollStatus($request, 1);
    }

    public function singleMarkAsUnpaid(Request $request)
    {
        return updateSinglePayrollStatus($request);
    }

    public function undoAllStatus(Request $request)
    {
        return undoAllStatus($request);
    }
}
