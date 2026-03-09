<?php

namespace App\Http\Controllers\API\Plaid;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PlaidTransactionController extends Controller
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
        //
    }

    public function publicToken(Request $request)
    {

        $json = json_encode([
            'client_id' => $request['client_id'],
            'secret' => $request['secret'],
            'institution_id' => 'ins_3',
            'initial_products' => ['auth', 'transactions', 'liabilities'],
            'options' => ['webhook' => 'https://www.genericwebhookurl.com/webhook'],
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sandbox.plaid.com/sandbox/public_token/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        ]);
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return json_decode($result);
    }

    public function publicTokenExchange(Request $request)
    {

        $json = json_encode([
            'client_id' => $request['client_id'],
            'secret' => $request['secret'],
            'public_token' => $request['public_token'],
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sandbox.plaid.com/item/public_token/exchange',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        ]);
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return json_decode($result);
    }

    public function authGet(Request $request)
    {

        $json = json_encode([
            'client_id' => $request['client_id'],
            'secret' => $request['secret'],
            'access_token' => $request['access_token'],
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sandbox.plaid.com/auth/get',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        ]);
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return json_decode($result);
    }

    public function identityGet(Request $request)
    {

        $json = json_encode([
            'client_id' => $request['client_id'],
            'secret' => $request['secret'],
            'access_token' => $request['access_token'],
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sandbox.plaid.com/identity/get',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        ]);
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return json_decode($result);
    }

    public function transactionsGet(Request $request)
    {
        $json = json_encode([
            'client_id' => $request['client_id'],
            'secret' => $request['secret'],
            'access_token' => $request['access_token'],
            'start_date' => $request['start_date'],
            'end_date' => $request['end_date'],
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sandbox.plaid.com/transactions/get',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        ]);
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return json_decode($result);
    }

    public function identityVerification(Request $request)
    {
        $json = json_encode([
            'client_id' => $request['client_id'],
            'secret' => $request['secret'],
            'identity_verification_id' => $request['identity_verification_id'],
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sandbox.plaid.com/identity_verification/get',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $json,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        ]);
        $result = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return json_decode($result);
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
}
