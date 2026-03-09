<?php

namespace App\Http\Controllers;

use App\Models\MarkAccountStatus;
use App\Models\SaleMasterProcess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionCalculaterController extends Controller
{
    public function index(): View
    {
        return view('auth.final');
    }

    public function index1(): View
    {
        return view('flex-power.index');
        // return view('auth.final');
    }

    public function store(Request $request)
    {

        // dd($request);
        $gross = ($request->PPW == null) ? 0 : $request->PPW;
        $dealer = ($request->dealer == null) ? 0 : $request->dealer;
        $redline = ($request->redline == null) ? 0 : $request->redline;
        $addres = ($request->adders == null) ? 0 : $request->adders;
        $Percentage = ($request->Percentage == null) ? 0 : $request->Percentage;
        $system_size = ($request->system_size == null) ? 1 : $request->system_size;
        // dd($request->gross);
        if ($request->gross == 'gross') {
            // dd($request->all());
            $data2 = (($gross * (1 - ($dealer / 100))) - ($redline + ($addres / (1000 * $system_size)))) * $system_size * ($Percentage / 100) * 1000;
            $data1 = number_format($data2, 2);
            // dd($data1);

        } elseif ($request->gross == 'net') {
            // dd($system_size);
            $data2 = ($gross - ($redline + ($addres / (1000 * $system_size)))) * $system_size * ($Percentage / 100) * 1000;
            $data1 = number_format($data2, 2);

        }
        $data3 = $data2 / $system_size;
        // dd($data3);
        $data4 = number_format($data3, 2);

        return response()->json(['data1' => $data1, 'data2' => $data4]);
        // return view('auth.index1', compact('data1','data2'));
    }

    public function saledata($id)
    {
        // return
        $data = SaleMasterProcess::with('setter1Detail', 'setter2Detail', 'closer1Detail', 'closer2Detail', 'status')->where('pid', $id)->first();
        $data['data1'] = MarkAccountStatus::where('id', $data->closer1_m1_paid_status)->first();
        $data['data2'] = MarkAccountStatus::where('id', $data->closer2_m1_paid_status)->first();
        $data['data3'] = MarkAccountStatus::where('id', $data->setter1_m1_paid_status)->first();
        $data['data4'] = MarkAccountStatus::where('id', $data->setter2_m1_paid_status)->first();
        $data['data5'] = MarkAccountStatus::where('id', $data->closer1_m2_paid_status)->first();
        $data['data6'] = MarkAccountStatus::where('id', $data->closer2_m2_paid_status)->first();
        $data['data7'] = MarkAccountStatus::where('id', $data->setter1_m2_paid_status)->first();
        $data['data8'] = MarkAccountStatus::where('id', $data->setter2_m2_paid_status)->first();
        // foreach($data1 as $data)
        // dd($data['data1']->account_status);
        if ($data) {
            // dd($data);
            return view('auth.index')->with('data', $data);
        } else {
            echo 'No record found';
        }
    }
}
