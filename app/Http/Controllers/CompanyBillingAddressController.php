<?php

namespace App\Http\Controllers;

use App\Models\BusinessAddress;
use App\Models\CompanyBillingAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyBillingAddressController extends Controller
{
    // get_billing_and_business_address
    public function get_billing_and_business_address(): JsonResponse
    {
        $data = [];
        $CompanyBillingAddress = CompanyBillingAddress::first();
        $BusinessAddress = BusinessAddress::first();

        // if Billing Address not created then company profile data will send
        if ($CompanyBillingAddress == null || $CompanyBillingAddress == '') {
            $CompanyBillingAddress = CompanyBillingAddress::create_Company_Billing_Address();
        }

        $data['CompanyBillingAddress'] = $CompanyBillingAddress;
        $data['BusinessAddress'] = $BusinessAddress;

        return response()->json([
            'ApiName' => 'get_billing_and_business_address',
            'status' => true,
            'message' => 'Data get Successfully.',
            'data' => $data,
        ], 200);
    }

    // update_billing_address

    public function update_billing_address(Request $request): JsonResponse
    {
        $request_data = $request->all();
        $id = $request['id'];
        $CompanyBillingAddress = CompanyBillingAddress::find($id);
        if ($CompanyBillingAddress == null || $CompanyBillingAddress == '') {
            $CompanyBillingAddress = new CompanyBillingAddress;
        }
        foreach ($request_data as $key => $value) {
            $CompanyBillingAddress->$key = $value;
        }
        $CompanyBillingAddress->save();

        return response()->json([
            'ApiName' => 'update_billing_address',
            'status' => true,
            'message' => 'Data updated Successfully.',
            'data' => $CompanyBillingAddress,
        ], 200);
    }
}
