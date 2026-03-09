<?php

namespace App\Http\Controllers\API\V2\Payroll;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PayrollMoveToNextController extends Controller
{
    public function userMoveToNext(Request $request)
    {
        return updatePayrollStatus($request, 'is_mark_paid', ['is_mark_paid' => 0, 'is_next_payroll' => 0, 'is_onetime_payment' => 0], 0, 1);
    }

    public function userMoveToNextUndo(Request $request)
    {
        return updatePayrollStatus($request, 'is_mark_paid', ['is_mark_paid' => 0, 'is_next_payroll' => 1, 'is_onetime_payment' => 0]);
    }

    public function singleMoveToNext(Request $request)
    {
        return updateSinglePayrollStatus($request, 0, 1);
    }

    public function singleMoveToNextUndo(Request $request)
    {
        return updateSinglePayrollStatus($request);
    }
}
