<?php

use App\Models\FrequencyType;

$payCommission = isset($data['earnings']['commission']['period_total']) ? $data['earnings']['commission']['period_total'] : 0.0;
$payCommissionYtd = isset($data['earnings']['commission']['ytd_total']) ? $data['earnings']['commission']['ytd_total'] : 0.0;

$payOverrides = isset($data['earnings']['overrides']['period_total']) ? $data['earnings']['overrides']['period_total'] : 0.0;
$payOverridesYtd = isset($data['earnings']['overrides']['ytd_total']) ? $data['earnings']['overrides']['ytd_total'] : 0.0;

$payReconciliation = isset($data['earnings']['reconciliation']['period_total']) ? $data['earnings']['reconciliation']['period_total'] : 0.0;
$payReconciliationYtd = isset($data['earnings']['reconciliation']['ytd_total']) ? $data['earnings']['reconciliation']['ytd_total'] : 0.0;

$miscellaneousAdjustment = isset($data['miscellaneous']['adjustment']['period_total']) ? $data['miscellaneous']['adjustment']['period_total'] : 0.0;
$miscellaneousAdjustmentYtd = isset($data['miscellaneous']['adjustment']['ytd_total']) ? $data['miscellaneous']['adjustment']['ytd_total'] : 0.0;

$miscellaneousReimbursement = isset($data['miscellaneous']['reimbursement']['period_total']) ? $data['miscellaneous']['reimbursement']['period_total'] : 0.0;
$miscellaneousReimbursementYtd = isset($data['miscellaneous']['reimbursement']['ytd_total']) ? $data['miscellaneous']['reimbursement']['ytd_total'] : 0.0;

$standardDeduction = isset($data['deduction']['standard_deduction']['period_total']) ? $data['deduction']['standard_deduction']['period_total'] : 0.0;
$standardDeductionYtd = isset($data['deduction']['standard_deduction']['ytd_total']) ? $data['deduction']['standard_deduction']['ytd_total'] : 0.0;

$ficaTax = isset($data['deduction']['fica_tax']['period_total']) ? $data['deduction']['fica_tax']['period_total'] : 0.0;
$ficaTaxYtd = isset($data['deduction']['fica_tax']['ytd_total']) ? $data['deduction']['fica_tax']['ytd_total'] : 0.0;

$customFields = isset($data['miscellaneous']['Total additional values']['period_total']) ? $data['miscellaneous']['Total additional values']['period_total'] : 0.0;
$customFieldsYtd = isset($data['miscellaneous']['Total additional values']['ytd_total']) ? $data['miscellaneous']['Total additional values']['ytd_total'] : 0.0;

$netYTD = isset($data['pay_stub']['net_ytd']) ? $data['pay_stub']['net_ytd'] : 0.0;
$netPay = isset($data['pay_stub']['net_pay']) ? $data['pay_stub']['net_pay'] : 0.0;

$grossPay = $payCommission + $payOverrides + $payReconciliation;
$grossPayYTD = $payCommissionYtd + $payOverridesYtd + $payReconciliationYtd;

$miscellaneousTotal = $miscellaneousAdjustment + $miscellaneousReimbursement + $customFields;
$miscellaneousTotalYTD = $miscellaneousReimbursementYtd + $miscellaneousAdjustmentYtd + $customFieldsYtd;
$companyType = $data['CompanyProfile']['company_type'];

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paystub </title>

    <style>
        @page: first {
            size: A4 portrait;
            margin: 20mm;
        }

        @page {
            size: A4 landscape;
            margin: 15mm;
        }

        .landscape-page {
            page: landscape;
            page-break-before: always;
            /* Forces a new page */
        }
    </style>

</head>

<body>
    <div class="content">
        <div class="portrait-page" style="background-color:#fafafa;">
            <div
                style="box-sizing:border-box;color:#74787e;line-height:1.4;width:100%!important;word-break:break-word;margin:0px;padding:0px;background-color:#ffffff">
                <div class="">
                    <div
                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;text-align:left">
                        <div
                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding-bottom:10px;">
                            <table style="width: 100%;">
                                <tr>
                                    <td style="width: 85%;">
                                        <div style="width: 85%;">
                                            <p
                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;">
                                                {{ isset($data['CompanyProfile']['business_name']) ? $data['CompanyProfile']['business_name'] : '' }}
                                            </p>
                                            <p
                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;">
                                                {{ isset($data['CompanyProfile']['business_address']) ? $data['CompanyProfile']['business_address'] : '' }}
                                            </p>
                                            <p
                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;">
                                                {{ isset($data['CompanyProfile']['business_phone']) ? $data['CompanyProfile']['business_phone'] : '' }}
                                            </p>
                                            <p
                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;">
                                                <a href="{{ isset($data['CompanyProfile']['company_website']) ? $data['CompanyProfile']['company_website'] : '' }}"
                                                    style="color: #767373; text-decoration: none;"
                                                    target="_blank">{{ isset($data['CompanyProfile']['company_website']) ? $data['CompanyProfile']['company_website'] : '' }}</a>
                                            </p>
                                        </div>
                                    </td>

                                    <td style="width: 15%; text-align: right;">
                                        <p style="margin: 0px; text-align: right;">
                                            <img src="{{ isset($data['CompanyProfile']['logo']) ? $data['CompanyProfile']['logo'] : '' }}"
                                                alt="" width="145">
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div style="box-sizing:border-box;color:rgb(0,0,0);text-align:left">
                        <p
                            style="margin-bottom:5px; margin-top:0px;color: #000;
                                                        font-size: 13px;font-weight: 500;">
                            <strong>Pay Date :
                                {{ isset($data['pay_stub']['pay_date']) ? date('m/d/Y', strtotime($data['pay_stub']['pay_date'])) : '' }}</strong>
                        </p>
                        <table style="width: 100%;">
                            <tr>
                                <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; width: 150px; background-color: #edf2fd; color: #5379eb; font-size: 20px; text-align: center; font-weight: 600; letter-spacing: 1.6; padding: 5px;"
                                    rowspan="2">
                                    PAY STUB
                                </td>

                                <td
                                    style="background-color: #edf2fd;
                                                                color: #5379eb;
                                                                text-align: center;
                                                                font-size: 13px;
                                                                font-weight: 500; padding: 5px;">
                                    Pay Period</td>
                                <td
                                    style="background-color: #edf2fd;
                                                                color: #5379eb;
                                                            text-align: center;
                                                            font-size: 13px;
                                                            font-weight: 500; padding: 5px;">
                                    Accounts this pay period
                                </td>
                                <td
                                    style="background-color: #edf2fd;
                                                            color: #5379eb;
                                                            text-align: center;
                                                            font-size: 13px;
                                                            font-weight: 500;  padding: 5px;">
                                    YTD</td>
                            </tr>
                            <tr>
                                <td
                                    style="width: 165px;background-color: #f7f7f7;
                                                                color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                    @if ($data['pay_stub']['is_onetime_payment'])
                                        Ont-Time Payment
                                    @else
                                        @if (isset($data['pay_stub']['pay_frequency']) && $data['pay_stub']['pay_frequency'] == FrequencyType::DAILY_PAY_ID)
                                            Daily Payroll
                                        @else
                                            {{ isset($data['pay_stub']['pay_period_from']) ? date('m/d/Y', strtotime($data['pay_stub']['pay_period_from'])) : '' }}
                                            -
                                            {{ isset($data['pay_stub']['pay_period_to']) ? date('m/d/Y', strtotime($data['pay_stub']['pay_period_to'])) : '' }}
                                        @endif
                                    @endif
                                </td>
                                <td
                                    style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                text-align: center;
                                                                font-size: 13px;
                                                                font-weight: 500; padding: 5px;">
                                    {{ isset($data['pay_stub']['period_sale_count']) ? $data['pay_stub']['period_sale_count'] : '' }}
                                </td>
                                <td
                                    style="background-color: #f7f7f7;
                                                                color: #767373;
                                                                text-align: center;
                                                                font-size: 13px;
                                                                font-weight: 500; padding: 5px;">
                                    {{ isset($data['pay_stub']['ytd_sale_count']) ? $data['pay_stub']['ytd_sale_count'] : '' }}
                                </td>
                            </tr>
                        </table>

                        <div style="margin-top: 10px;">
                            <table style="width: 100%;">
                                <tr>
                                    <td style="width: 50%;">
                                        <div style="background-color: #f7f7f7; ">
                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td
                                                        style="width: 30%; color: rgb(96 120 236);background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;padding: 8px 10px; background-color: #edf2fd;">
                                                        Name</td>
                                                    <td
                                                        style="background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                        {{ isset($data['employee']['first_name']) ? $data['employee']['first_name'] : '' }}
                                                        {{ isset($data['employee']['last_name']) ? $data['employee']['last_name'] : '' }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div style="background-color: #f7f7f7; margin-top: 3px;">
                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td
                                                        style="width: 30%; color: rgb(96 120 236);background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">
                                                        User ID</td>
                                                    <td
                                                        style="background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                        {{ isset($data['employee']['employee_id']) ? $data['employee']['employee_id'] : '' }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div style="background-color: #f7f7f7;  margin-top: 3px;">
                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td
                                                        style="width: 30%; color: rgb(96 120 236);background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">
                                                        Address</td>
                                                    <td
                                                        style="background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                        {{ isset($data['employee']['home_address']) ? $data['employee']['home_address'] : '' }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        @if ($data['employee']['entity_type'] == 'individual')
                                            <div style="background-color: #f7f7f7;  margin-top: 3px;">
                                                <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td
                                                            style="width: 30%; color: rgb(96 120 236);background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">
                                                            SSN</td>
                                                        <td
                                                            style="background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                            @if (isset($data['employee']['social_sequrity_no']) && $data['employee']['social_sequrity_no'])
                                                                @php
                                                                    $lastFourDigits = substr(
                                                                        $data['employee']['social_sequrity_no'],
                                                                        -4,
                                                                    ); // Get last 4 digits
                                                                    $socialSecurityNo =
                                                                        str_repeat(
                                                                            '×',
                                                                            strlen(
                                                                                $data['employee']['social_sequrity_no'],
                                                                            ) - 4,
                                                                        ) . $lastFourDigits;
                                                                @endphp
                                                            @endif
                                                            {{ isset($socialSecurityNo) ? $socialSecurityNo : '' }}
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @else
                                            <div style="background-color: #f7f7f7;  margin-top: 3px;">
                                                <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td
                                                            style="width: 30%; color: rgb(96 120 236);background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">
                                                            EIN</td>
                                                        <td
                                                            style="background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                            {{ isset($data['employee']['business_ein']) ? $data['employee']['business_ein'] : '' }}
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @endif
                                        <div style="background-color: #f7f7f7; margin-top: 3px;">
                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td
                                                        style="width: 30%; color: rgb(96 120 236);background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">
                                                        Bank Acct.</td>
                                                    <td
                                                        style="background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                        @if (isset($data['employee']['account_no']) && $data['employee']['account_no'])
                                                            @php
                                                                $lastFourDigits = substr(
                                                                    $data['employee']['account_no'],
                                                                    -4,
                                                                ); // Get last 4 digits
                                                                $accountNo =
                                                                    str_repeat(
                                                                        '×',
                                                                        strlen($data['employee']['account_no']) - 4,
                                                                    ) . $lastFourDigits;
                                                            @endphp
                                                        @endif
                                                        {{ isset($accountNo) ? $accountNo : '' }}
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        @if (isset($data['employee']['entity_type']) && $data['employee']['entity_type'] != 'individual')
                                            <div style="background-color: #f7f7f7; margin-top: 3px;">
                                                <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td
                                                            style="width: 30%; color: rgb(96 120 236);background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">
                                                            Business Name</td>
                                                        <td
                                                            style="background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                            {{ isset($data['employee']['business_name']) ? $data['employee']['business_name'] : '' }}
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @endif
                                    </td>

                                    <td
                                        style="background-color: #fff;color: #5379eb;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 0px; vertical-align:top">
                                        <div
                                            style="
                                                                        background-color: #edf2fd;
                                                                        border-radius: 15px;
                                                                        padding: 20px 25px; float:right;">
                                            <p
                                                style="margin-bottom:5px; margin-top:0px;color: #5379eb; font-size: 20px;font-weight: 600; text-align: left; margin-bottom: 5px;">
                                                Net Pay</p>
                                            <table style="width: 100%;">
                                                <tr>
                                                    <td
                                                        style="background-color: #edf2fd;
                                                                        color: #5379eb;
                                                                        width: 50%;
                                                                        font-size: 14px;
                                                                        font-weight: 500; padding: 5px; text-align: left; white-space:nowrap">
                                                        This Pay check</td>
                                                    <td
                                                        style="background-color: #edf2fd;
                                                                        color: #5379eb;
                                                                        width: 50%;
                                                                        font-size: 14px;
                                                                        font-weight: 500; padding: 5px; text-align: left;white-space:nowrap">
                                                        YTD</td>
                                                </tr>
                                                <tr>
                                                    <td
                                                        style=" color: {{ $netPay < 0 ? 'red' : '#727171' }}; text-align: center; font-size: 14px; font-weight: 500; padding: 5px; text-align: left;white-space:nowrap">
                                                        @if ($netPay >= 0)
                                                            $ {{ exportNumberFormat(abs((float) $netPay)) }}
                                                        @else
                                                            $ ({{ exportNumberFormat(abs((float) $netPay)) }})
                                                        @endif
                                                    </td>
                                                    <td
                                                        style=" color: {{ $netYTD < 0 ? 'red' : '#727171' }}; text-align: center; font-size: 14px; font-weight: 500; padding: 5px; text-align: left ; white-space:nowrap">
                                                        @if ($netYTD >= 0)
                                                            $ {{ exportNumberFormat(abs((float) $netYTD)) }}
                                                        @else
                                                            $ ({{ exportNumberFormat(abs((float) $netYTD)) }})
                                                        @endif
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div style="margin-top: 0px;">
                            <p
                                style="margin-bottom:5px; margin-top:0px;color: #767373;
                                                            font-size: 13px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                Earnings</p>
                            <table style="width: 100%;">
                                <tr>
                                    <td
                                        style="background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Description</td>
                                    <td
                                        style="background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        Total</td>
                                    <td
                                        style="background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        YTD</td>
                                </tr>
                                <tr>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Commissions</td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $payCommission < 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($payCommission >= 0)
                                            $ {{ exportNumberFormat(abs((float) $payCommission)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $payCommission)) }})
                                        @endif

                                    </td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $payCommissionYtd < 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($payCommissionYtd >= 0)
                                            $ {{ exportNumberFormat(abs((float) $payCommissionYtd)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $payCommissionYtd)) }})
                                        @endif

                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Overrides</td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $payOverrides < 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($payOverrides >= 0)
                                            $ {{ exportNumberFormat(abs($payOverrides)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs($payOverrides)) }})
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $payOverridesYtd < 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($payOverridesYtd >= 0)
                                            $ {{ exportNumberFormat(abs((float) $payOverridesYtd)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $payOverridesYtd)) }})
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Reconciliations</td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $payReconciliation < 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($payReconciliation >= 0)
                                            $ {{ exportNumberFormat(abs((float) $payReconciliation)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $payReconciliation)) }})
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $payReconciliationYtd < 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($payReconciliationYtd >= 0)
                                            $ {{ exportNumberFormat(abs((float) $payReconciliationYtd)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $payReconciliationYtd)) }})
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: right;
                                                                    padding-right: 8px !important;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Gross Earning</td>

                                    <td
                                        style="background-color: #edf2fd;
                                                                color: {{ $grossPay < 0 ? 'red' : '#5379eb' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($grossPay >= 0)
                                            $ {{ exportNumberFormat(abs((float) $grossPay)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $grossPay)) }})
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #edf2fd;
                                                                color: {{ $grossPayYTD < 0 ? 'red' : '#5379eb' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($grossPayYTD >= 0)
                                            $ {{ exportNumberFormat(abs((float) $grossPayYTD)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $grossPayYTD)) }})
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div style="margin-top: 10px;">
                            <p
                                style="margin-bottom:5px; margin-top:0px;color: #767373;
                                                            font-size: 13px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                Deductions</p>
                            <table style="width: 100%;">
                                <tr>
                                    <td
                                        style="background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Description</td>
                                    <td
                                        style="background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        Total</td>
                                    <td
                                        style="background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        YTD</td>
                                </tr>
                                <tr>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Standard Deductions</td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $standardDeduction > 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($standardDeduction <= 0)
                                            $ {{ exportNumberFormat(abs((float) $standardDeduction)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $standardDeduction)) }})
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: {{ $standardDeductionYtd >= 0 ? 'red' : '#767373' }};
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($standardDeductionYtd < 0)
                                            $ {{ exportNumberFormat(abs((float) $standardDeductionYtd)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $standardDeductionYtd)) }})
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                        Fica Tax</td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($ficaTax >= 0)
                                            $ {{ exportNumberFormat(abs((float) $ficaTax)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $ficaTax)) }})
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                        @if ($ficaTaxYtd >= 0)
                                            $ {{ exportNumberFormat(abs((float) $ficaTaxYtd)) }}
                                        @else
                                            $ ({{ exportNumberFormat(abs((float) $ficaTaxYtd)) }})
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <table style="width: 100%; margin-top: 10px;">
                            <tr>
                                <td
                                    style="background-color: #fff;color: #5379eb;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px; width: 70%;">
                                    <p
                                        style="margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                        Miscellaneous</p>
                                    <table style="width: 100%;">
                                        <tr>
                                            <td
                                                style="background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                Description</td>
                                            <td
                                                style="background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                Total</td>
                                            <td
                                                style="background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                YTD</td>
                                        </tr>
                                        <tr>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                Adjustment</td>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: {{ $miscellaneousAdjustment < 0 ? 'red' : '#767373' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($miscellaneousAdjustment >= 0)
                                                    $ {{ exportNumberFormat(abs((float) $miscellaneousAdjustment)) }}
                                                @else
                                                    $ ({{ exportNumberFormat(abs((float) $miscellaneousAdjustment)) }})
                                                @endif
                                            </td>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: {{ $miscellaneousAdjustmentYtd < 0 ? 'red' : '#767373' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($miscellaneousAdjustmentYtd >= 0)
                                                    $
                                                    {{ exportNumberFormat(abs((float) $miscellaneousAdjustmentYtd)) }}
                                                @else
                                                    $
                                                    ({{ exportNumberFormat(abs((float) $miscellaneousAdjustmentYtd)) }})
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                Reimbursement</td>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: {{ $miscellaneousReimbursement < 0 ? 'red' : '#767373' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($miscellaneousReimbursement >= 0)
                                                    $
                                                    {{ exportNumberFormat(abs((float) $miscellaneousReimbursement)) }}
                                                @else
                                                    $
                                                    ({{ exportNumberFormat(abs((float) $miscellaneousReimbursement)) }})
                                                @endif
                                            </td>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: {{ $miscellaneousReimbursementYtd < 0 ? 'red' : '#767373' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($miscellaneousReimbursementYtd >= 0)
                                                    $
                                                    {{ exportNumberFormat(abs((float) $miscellaneousReimbursementYtd)) }}
                                                @else
                                                    $
                                                    ({{ exportNumberFormat(abs((float) $miscellaneousReimbursementYtd)) }})
                                                @endif
                                            </td>
                                        </tr>

                                        <tr>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                Total additional values</td>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: {{ $customFields < 0 ? 'red' : '#767373' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($customFields >= 0)
                                                    $ {{ exportNumberFormat(abs((float) $customFields)) }}
                                                @else
                                                    $ ({{ exportNumberFormat(abs((float) $customFields)) }})
                                                @endif
                                            </td>
                                            <td
                                                style="background-color: #f7f7f7;
                                                                            color: {{ $customFieldsYtd < 0 ? 'red' : '#767373' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($customFieldsYtd >= 0)
                                                    $ {{ exportNumberFormat(abs((float) $customFieldsYtd)) }}
                                                @else
                                                    $ ({{ exportNumberFormat(abs((float) $customFieldsYtd)) }})
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td
                                                style="background-color: #edf2fd;
                                                        color: #5379eb;
                                                        text-align: right;
                                                        padding-right: 8px !important;
                                                        font-size: 13px;
                                                        font-weight: 500; padding: 5px; width: 60%;">
                                                Total</td>
                                            <td
                                                style="background-color: #edf2fd;
                                                                            color: {{ $miscellaneousTotal < 0 ? 'red' : '#5379eb' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($miscellaneousTotal >= 0)
                                                    $ {{ exportNumberFormat(abs((float) $miscellaneousTotal)) }}
                                                @else
                                                    $ ({{ exportNumberFormat(abs((float) $miscellaneousTotal)) }})
                                                @endif
                                            </td>
                                            <td
                                                style="background-color: #edf2fd;
                                                                            color: {{ $miscellaneousTotalYTD < 0 ? 'red' : '#5379eb' }};
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                @if ($miscellaneousTotalYTD >= 0)
                                                    $ {{ exportNumberFormat(abs((float) $miscellaneousTotalYTD)) }}
                                                @else
                                                    $ ({{ exportNumberFormat(abs((float) $miscellaneousTotalYTD)) }})
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="landscape-page">
                    <table style="width: 100%; margin-top: 10px; page-break-inside: avoid;">
                        <tr>
                            <td
                                style="background-color: #fff;color: #000;text-align: center;
                        font-size: 11px;font-weight: 500; padding: 5px;">
                                <p
                                    style="margin-bottom:5px; margin-top:0px;color: #767373;
                            font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase; white-space: nowrap;">
                                    Commission</p>
                            </td>
                        </tr>
                        @if (
                            $companyType == \App\Models\CompanyProfile::SOLAR_COMPANY_TYPE ||
                                $companyType == \App\Models\CompanyProfile::SOLAR2_COMPANY_TYPE)
                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    PID</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Customer
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Product
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    State</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Rep Redline
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    KW</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Net EPC
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adders
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Amount
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adjustment
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Type</td>
                            </tr>
                            @forelse($commissionDetails as $commissionDetail)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['pid']) ? $commissionDetail['pid'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['customer_name']) ? $commissionDetail['customer_name'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['product']) ? $commissionDetail['product'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['customer_state']) ? $commissionDetail['customer_state'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ formatCommission($companyType, $commissionDetail['rep_redline'], $commissionDetail['rep_redline_type']) }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['kw']) ? $commissionDetail['kw'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['net_epc']) ? $commissionDetail['net_epc'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adders', $commissionDetail) && $commissionDetail['adders'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adders', $commissionDetail))
                                            @if ($commissionDetail['adders'] >= 0)
                                                $ {{ exportNumberFormat(abs((float) $commissionDetail['adders'])) }}
                                            @else
                                                $ ({{ exportNumberFormat(abs((float) $commissionDetail['adders'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('amount', $commissionDetail) && $commissionDetail['amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('amount', $commissionDetail))
                                            @if ($commissionDetail['amount'] >= 0)
                                                $ {{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }}
                                            @else
                                                $ ({{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adjustment', $commissionDetail) && $commissionDetail['adjustment']['adjustment_amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adjustment', $commissionDetail))
                                            @if ($commissionDetail['adjustment']['adjustment_amount'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['amount_type']) ? $commissionDetail['amount_type'] : '' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;                        
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="11">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        @elseif (in_array($companyType, \App\Models\CompanyProfile::PEST_COMPANY_TYPE))
                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    PID</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Customer
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Product</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Gross Account Value
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Commission</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Amount
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adjustment
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Type
                                </td>
                            </tr>
                            @forelse($commissionDetails as $commissionDetail)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['pid']) ? $commissionDetail['pid'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['customer_name']) ? $commissionDetail['customer_name'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['product']) ? $commissionDetail['product'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('gross_account_value', $commissionDetail) && $commissionDetail['gross_account_value'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('gross_account_value', $commissionDetail))
                                            @if ($commissionDetail['gross_account_value'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $commissionDetail['gross_account_value'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $commissionDetail['gross_account_value'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px;">
                                        {{ formatCommission($companyType, $commissionDetail['commission_amount'], $commissionDetail['commission_type']) }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('amount', $commissionDetail) && $commissionDetail['amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('amount', $commissionDetail))
                                            @if ($commissionDetail['amount'] >= 0)
                                                $ {{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }}
                                            @else
                                                $ ({{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adjustment', $commissionDetail) && $commissionDetail['adjustment']['adjustment_amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adjustment', $commissionDetail))
                                            @if ($commissionDetail['adjustment']['adjustment_amount'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['amount_type']) ? $commissionDetail['amount_type'] : '' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;                        
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="8">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        @elseif ($companyType == \App\Models\CompanyProfile::TURF_COMPANY_TYPE)
                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    PID</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Customer
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Product</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    State
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Sq ft</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Net $ / Sq ft
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adders
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Amount
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adjustment
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Type
                                </td>
                            </tr>
                            @forelse($commissionDetails as $commissionDetail)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['pid']) ? $commissionDetail['pid'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['customer_name']) ? $commissionDetail['customer_name'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['product']) ? $commissionDetail['product'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['customer_state']) ? $commissionDetail['customer_state'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['kw']) ? $commissionDetail['kw'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['net_epc']) ? $commissionDetail['net_epc'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adders', $commissionDetail) && $commissionDetail['adders'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adders', $commissionDetail))
                                            @if ($commissionDetail['adders'] >= 0)
                                                $ {{ exportNumberFormat(abs((float) $commissionDetail['adders'])) }}
                                            @else
                                                $ ({{ exportNumberFormat(abs((float) $commissionDetail['adders'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('amount', $commissionDetail) && $commissionDetail['amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('amount', $commissionDetail))
                                            @if ($commissionDetail['amount'] >= 0)
                                                $ {{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }}
                                            @else
                                                $ ({{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adjustment', $commissionDetail) && $commissionDetail['adjustment']['adjustment_amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adjustment', $commissionDetail))
                                            @if ($commissionDetail['adjustment']['adjustment_amount'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['amount_type']) ? $commissionDetail['amount_type'] : '' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;                        
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="10">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        @elseif ($companyType == \App\Models\CompanyProfile::MORTGAGE_COMPANY_TYPE)
                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    PID</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Customer
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Product</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    State
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Rep Office Fee</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Comp Rate
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Loan Amount
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Amount
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adjustment
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Type
                                </td>
                            </tr>
                            @forelse($commissionDetails as $commissionDetail)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['pid']) ? $commissionDetail['pid'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['customer_name']) ? $commissionDetail['customer_name'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['product']) ? $commissionDetail['product'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['customer_state']) ? $commissionDetail['customer_state'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ formatCommission($companyType, $commissionDetail['rep_redline'], $commissionDetail['rep_redline_type']) }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['comp_rate']) ? $commissionDetail['comp_rate'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: {{ array_key_exists('gross_account_value', $commissionDetail) && $commissionDetail['gross_account_value'] < 0 ? 'red' : '#767373' }};
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('gross_account_value', $commissionDetail))
                                            @if ($commissionDetail['gross_account_value'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $commissionDetail['gross_account_value'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $commissionDetail['gross_account_value'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: {{ array_key_exists('amount', $commissionDetail) && $commissionDetail['amount'] < 0 ? 'red' : '#767373' }};
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('amount', $commissionDetail))
                                            @if ($commissionDetail['amount'] >= 0)
                                                $ {{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }}
                                            @else
                                                $ ({{ exportNumberFormat(abs((float) $commissionDetail['amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: {{ array_key_exists('adjustment', $commissionDetail) && $commissionDetail['adjustment']['adjustment_amount'] < 0 ? 'red' : '#767373' }};
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adjustment', $commissionDetail))
                                            @if ($commissionDetail['adjustment']['adjustment_amount'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $commissionDetail['adjustment']['adjustment_amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($commissionDetail['amount_type']) ? $commissionDetail['amount_type'] : '' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;                        
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="10">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    </table>


                    <!-- Overrides -->
                    <table style="width:100%; margin-top: 10px;">
                        <tr>
                            <td
                                style="background-color: #fff;color: #000;text-align: center;
                        font-size: 11px;font-weight: 500; padding: 5px;">
                                <p
                                    style="margin-bottom:5px; margin-top:0px;color: #767373;
                            font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase; white-space: nowrap;">
                                    Overrides</p>
                            </td>
                        </tr>
                        <tr>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                PID</td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Customer Name
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Product
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Override Over
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Type</td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                @if (
                                    $companyType == \App\Models\CompanyProfile::SOLAR_COMPANY_TYPE ||
                                        $companyType == \App\Models\CompanyProfile::SOLAR2_COMPANY_TYPE)
                                    KW installed
                                @elseif (in_array($companyType, \App\Models\CompanyProfile::PEST_COMPANY_TYPE))
                                    Gross Value
                                @elseif ($companyType == \App\Models\CompanyProfile::TURF_COMPANY_TYPE)
                                    Sq ft installed
                                @elseif ($companyType == \App\Models\CompanyProfile::MORTGAGE_COMPANY_TYPE)
                                    Loan Amount
                                @endif
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Override
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Total Amount
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Adjustment
                            </td>
                        </tr>
                        @forelse($overrideDetails as $overrideDetail)
                            <tr>
                                <td
                                    style="background-color: #fff;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($overrideDetail['pid']) ? $overrideDetail['pid'] : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($overrideDetail['customer_name']) ? $overrideDetail['customer_name'] : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($overrideDetail['product']) ? $overrideDetail['product'] : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($overrideDetail['over_first_name']) ? $overrideDetail['over_first_name'] : '' }}
                                    {{ isset($overrideDetail['over_last_name']) ? $overrideDetail['over_last_name'] : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                    white-space: nowrap;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($overrideDetail['type']) ? $overrideDetail['type'] : '' }}
                                </td>
                                @if (
                                    $companyType == \App\Models\CompanyProfile::SOLAR_COMPANY_TYPE ||
                                        $companyType == \App\Models\CompanyProfile::SOLAR2_COMPANY_TYPE ||
                                        $companyType == \App\Models\CompanyProfile::TURF_COMPANY_TYPE)
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($overrideDetail['kw']) ? $overrideDetail['kw'] : '' }}
                                    </td>
                                @elseif (in_array($companyType, \App\Models\CompanyProfile::PEST_COMPANY_TYPE) ||
                                        $companyType == \App\Models\CompanyProfile::MORTGAGE_COMPANY_TYPE)
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('gross_account_value', $overrideDetail) && $overrideDetail['gross_account_value'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('gross_account_value', $overrideDetail))
                                            @if ($overrideDetail['gross_account_value'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $overrideDetail['gross_account_value'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $overrideDetail['gross_account_value'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                @endif
                                <td
                                    style="background-color: #fff;
                                    white-space: nowrap;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ formatOverride($companyType, $overrideDetail['override_amount'], $overrideDetail['override_type']) }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                        white-space: nowrap;
                                        color: {{ array_key_exists('amount', $overrideDetail) && $overrideDetail['amount'] < 0 ? 'red' : '#767373' }};
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                    @if (array_key_exists('amount', $overrideDetail))
                                        @if ($overrideDetail['amount'] >= 0)
                                            $
                                            {{ exportNumberFormat(abs((float) $overrideDetail['amount'])) }}
                                        @else
                                            $
                                            ({{ exportNumberFormat(abs((float) $overrideDetail['amount'])) }})
                                        @endif
                                    @else
                                        $ 0.00
                                    @endif
                                </td>
                                <td
                                    style="background-color: #fff;
                                        white-space: nowrap;
                                        color: {{ array_key_exists('adjustment', $overrideDetail) && $overrideDetail['adjustment']['adjustment_amount'] < 0 ? 'red' : '#767373' }};
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                    @if (array_key_exists('adjustment', $overrideDetail))
                                        @if ($overrideDetail['adjustment']['adjustment_amount'] >= 0)
                                            $
                                            {{ exportNumberFormat(abs((float) $overrideDetail['adjustment']['adjustment_amount'])) }}
                                        @else
                                            $
                                            ({{ exportNumberFormat(abs((float) $overrideDetail['adjustment']['adjustment_amount'])) }})
                                        @endif
                                    @else
                                        $ 0.00
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td style="background-color: #fff;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 11px;
                                    font-weight: 500; padding: 20px;"
                                    colspan="9">
                                    No data found
                                </td>
                            </tr>
                        @endforelse
                    </table>

                    <!-- Reconciliation -->
                    @if (isset($reconciliationDetails) && count($reconciliationDetails) > 0)
                        <table style="width: 100%; margin-top: 10px;">
                            <tr style="width: 100%;">
                                <td>
                                    <table style="width: 100%;">
                                        <tr>
                                            <td
                                                style="background-color: #fff;color: #000;text-align: center; font-size: 11px;font-weight: 500; padding: 5px;">
                                                <p
                                                    style="margin-bottom:5px; margin-top:0px;color: #767373; font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase; white-space: nowrap">
                                                    Reconciliation</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Added to payroll</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 12px;
                                    font-weight: 500; padding: 5px; ">
                                    Start/end date (Payout %)
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Commission Withheld
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Override Due
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Clawbacks
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adjustments
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Payout
                                </td>
                            </tr>
                            @forelse($reconciliationDetails as $reconciliationDetail)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($reconciliationDetail['payroll_added_date']) ? $reconciliationDetail['payroll_added_date'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($reconciliationDetail['start_end']) ? $reconciliationDetail['start_end'] : '' }}
                                        ({{ isset($reconciliationDetail['payout']) ? $reconciliationDetail['payout'] : '0.00' }}
                                        %)
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('commission', $reconciliationDetail) && $reconciliationDetail['commission'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('commission', $reconciliationDetail))
                                            @if ($reconciliationDetail['commission'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $reconciliationDetail['commission'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $reconciliationDetail['commission'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('override', $reconciliationDetail) && $reconciliationDetail['override'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('override', $reconciliationDetail))
                                            @if ($reconciliationDetail['override'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $reconciliationDetail['override'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $reconciliationDetail['override'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('clawback', $reconciliationDetail) && $reconciliationDetail['clawback'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('clawback', $reconciliationDetail))
                                            @if ($reconciliationDetail['clawback'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $reconciliationDetail['clawback'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $reconciliationDetail['clawback'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adjustment', $reconciliationDetail) && $reconciliationDetail['adjustment'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adjustment', $reconciliationDetail))
                                            @if ($reconciliationDetail['adjustment'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $reconciliationDetail['adjustment'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $reconciliationDetail['adjustment'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('total', $reconciliationDetail) && $reconciliationDetail['total'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('total', $reconciliationDetail))
                                            @if ($reconciliationDetail['total'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $reconciliationDetail['total'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $reconciliationDetail['total'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="7">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        </table>
                    @endif

                    <!-- Deduction -->
                    @if (isset($deductionsDetails) && count($deductionsDetails) > 0)
                        <table style="width: 100%;">
                            <tr>
                                <td
                                    style="background-color: #fff;color: #000;text-align: center;
                                                                            font-size: 11px;font-weight: 500; padding: 5px;">
                                    <p
                                        style="margin-bottom:5px; margin-top:0px;color: #767373;
                                                                                font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                        Deduction</p>
                                </td>
                            </tr>
                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Type</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Amount
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Limit</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Total</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Outstanding</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adjustment
                                </td>
                            </tr>
                            @forelse($deductionsDetails as $deductionsDetail)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($deductionsDetail['type']) ? $deductionsDetail['type'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('amount', $deductionsDetail) && $deductionsDetail['amount'] > 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('amount', $deductionsDetail))
                                            @if ($deductionsDetail['amount'] > 0)
                                                $
                                                ({{ exportNumberFormat(abs((float) $deductionsDetail['amount'])) }})
                                            @else
                                                $
                                                {{ exportNumberFormat(abs((float) $deductionsDetail['amount'])) }}
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($deductionsDetail['limit']) ? $deductionsDetail['limit'] . ' %' : '-' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('total', $deductionsDetail) && $deductionsDetail['total'] > 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('total', $deductionsDetail))
                                            @if ($deductionsDetail['total'] > 0)
                                                $
                                                ({{ exportNumberFormat(abs((float) $deductionsDetail['total'])) }})
                                            @else
                                                $
                                                {{ exportNumberFormat(abs((float) $deductionsDetail['total'])) }}
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('outstanding', $deductionsDetail) && $deductionsDetail['outstanding'] > 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('outstanding', $deductionsDetail))
                                            @if ($deductionsDetail['outstanding'] > 0)
                                                $
                                                ({{ exportNumberFormat(abs((float) $deductionsDetail['outstanding'])) }})
                                            @else
                                                $
                                                {{ exportNumberFormat(abs((float) $deductionsDetail['outstanding'])) }}
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adjustment', $deductionsDetail) && $deductionsDetail['adjustment']['adjustment_amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adjustment', $deductionsDetail))
                                            @if ($deductionsDetail['adjustment']['adjustment_amount'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $deductionsDetail['adjustment']['adjustment_amount'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $deductionsDetail['adjustment']['adjustment_amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="6">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        </table>
                    @endif

                    <!-- Adjustment -->
                    <table style="width: 100%;">
                        <tr>
                            <td
                                style="background-color: #fff;color: #000;text-align: center;
                                                                font-size: 11px;font-weight: 500; padding: 5px;">
                                <p
                                    style="margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                    Adjustments</p>
                            </td>
                        </tr>
                        <tr>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Customer Name</td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Date
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Type</td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Amount
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Description</td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Approved By</td>
                        </tr>
                        @forelse($adjustmentDetails as $adjustment)
                            <tr>
                                <td
                                    style="background-color: #fff;
                                    white-space: nowrap;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($adjustment['customer_name']) ? $adjustment['customer_name'] : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                    white-space: nowrap;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($adjustment['date']) ? date('m/d/Y', strtotime($adjustment['date'])) : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                    white-space: nowrap;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($adjustment['payroll_type']) ? $adjustment['payroll_type'] : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                        white-space: nowrap;
                                        color: {{ array_key_exists('amount', $adjustment) && $adjustment['amount'] < 0 ? 'red' : '#767373' }};
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                    @if (array_key_exists('amount', $adjustment))
                                        @if ($adjustment['amount'] >= 0)
                                            $
                                            {{ exportNumberFormat(abs((float) $adjustment['amount'])) }}
                                        @else
                                            $
                                            ({{ exportNumberFormat(abs((float) $adjustment['amount'])) }})
                                        @endif
                                    @else
                                        $ 0.00
                                    @endif
                                </td>
                                <td
                                    style="background-color: #fff;
                                    color: #767373;
                                    text-align: left;
                                    font-size: 12px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($adjustment['adjustment']['adjustment_comment']) ? $adjustment['adjustment']['adjustment_comment'] : (isset($adjustment['description']) ? $adjustment['description'] : '') }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                    {{ isset($adjustment['adjustment']['adjustment_by']) ? $adjustment['adjustment']['adjustment_by'] : '' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td style="background-color: #fff;
                                    color: #767373;
                                    text-align: center;
                                    font-size: 11px;
                                    font-weight: 500; padding: 20px;"
                                    colspan="6">
                                    No data found
                                </td>
                            </tr>
                        @endforelse
                    </table>

                    <!-- Reimbursment -->
                    <table style="width: 100%;">
                        <tr>
                            <p
                                style="margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                Reimbursements</p>
                        </tr>
                        <tr>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Approved By</td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Date
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Amount
                            </td>
                            <td
                                style="background-color: #e0e0e0;
                                white-space: nowrap;
                                color: #000;
                                text-align: center;
                                font-size: 10px;
                                font-weight: 500; padding: 5px; ">
                                Description</td>
                        </tr>
                        @forelse($reimbursementDetails as $reimbursement)
                            <tr>
                                <td
                                    style="
                                    color: #767373;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($reimbursement['adjustment']['adjustment_by']) ? $reimbursement['adjustment']['adjustment_by'] : '' }}
                                </td>
                                <td
                                    style="
                                    color: #767373;
                                    white-space: nowrap;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($reimbursement['date']) ? date('m/d/Y', strtotime($reimbursement['date'])) : '' }}
                                </td>
                                <td
                                    style="background-color: #fff;
                                        white-space: nowrap;
                                        color: {{ array_key_exists('amount', $reimbursement) && $reimbursement['amount'] < 0 ? 'red' : '#767373' }};
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                    @if (array_key_exists('amount', $reimbursement))
                                        @if ($reimbursement['amount'] >= 0)
                                            $
                                            {{ exportNumberFormat(abs((float) $reimbursement['amount'])) }}
                                        @else
                                            $
                                            ({{ exportNumberFormat(abs((float) $reimbursement['amount'])) }})
                                        @endif
                                    @else
                                        $ 0.00
                                    @endif
                                </td>
                                <td
                                    style="
                                    color: #767373;
                                    text-align: left;
                                    font-size: 12px;
                                    font-weight: 500; padding: 5px; ">
                                    {{ isset($reimbursement['adjustment']['adjustment_comment']) ? $reimbursement['adjustment']['adjustment_comment'] : (isset($reimbursement['description']) ? $reimbursement['description'] : '') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td style="
                                    color: #767373;
                                    text-align: center;
                                    font-size: 11px;
                                    font-weight: 500; padding: 20px;"
                                    colspan="4">
                                    No data found
                                </td>
                            </tr>
                        @endforelse
                    </table>

                    <!-- Additional Values start -->
                    @if (isset($additionalDetails) && count($additionalDetails) > 0)
                        <table style="width:100%;">
                            <tr>
                                <p
                                    style="margin-bottom:5px; margin-top:0px;color: #767373;
                                                                        font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                    Additional Values</p>
                            </tr>
                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Type</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Date
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Amount
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Comment</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Approved By
                                </td>
                            </tr>
                            @forelse($additionalDetails as $customFields)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($customFields['custom_field_name']) ? $customFields['custom_field_name'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        white-space: nowrap;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($customFields['date']) ? date('m/d/Y', strtotime($customFields['date'])) : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('amount', $customFields) && $customFields['amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('amount', $customFields))
                                            @if ($customFields['amount'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $customFields['amount'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $customFields['amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($customFields['comment']) ? $customFields['comment'] : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($customFields['adjustment']['adjustment_by']) ? $customFields['adjustment']['adjustment_by'] : '' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="5">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        </table>
                    @endif

                    <!-- Wages start -->
                    @if (isset($wagesDetails) && count($wagesDetails) > 0)
                        <table style="width:100%;">
                            <tr>
                                <td
                                    style="background-color: #fff;color: #000;text-align: center;
                                    font-size: 11px;font-weight: 500; padding: 5px;">
                                    <p
                                        style="margin-bottom:5px; margin-top:0px;color: #767373;
                                        font-size: 11px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                        Wages</p>
                                </td>
                            </tr>
                            <tr>
                                <td
                                    style="background-color: #e0e0e0;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Date</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Hourly Rate
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    OT Rate
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Salary</td>
                                <td
                                    style="background-color: #e0e0e0;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Regular Hours
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Overtime
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Total
                                </td>
                                <td
                                    style="background-color: #e0e0e0;
                                    white-space: nowrap;
                                    color: #000;
                                    text-align: center;
                                    font-size: 10px;
                                    font-weight: 500; padding: 5px; ">
                                    Adjustment
                                </td>
                            </tr>
                            @forelse($wagesDetails as $wages)
                                <tr>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($wages['date']) ? date('m/d/Y', strtotime($wages['date'])) : '' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        $ {{ isset($wages['hourly_rate']) ? $wages['hourly_rate'] : '0.00' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('overtime_rate', $wages) && $wages['overtime_rate'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('overtime_rate', $wages))
                                            @if ($wages['overtime_rate'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $wages['overtime_rate'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $wages['overtime_rate'])) }})
                                            @endif
                                        @else
                                            0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('salary', $wages) && $wages['salary'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('salary', $wages))
                                            @if ($wages['salary'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $wages['salary'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $wages['salary'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($wages['regular_hour']) ? $wages['regular_hour'] : '0 Hrs' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 10px;
                                        font-weight: 500; padding: 5px; ">
                                        {{ isset($wages['overtime_hour']) ? $wages['overtime_hour'] : '0 Hrs' }}
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('total', $wages) && $wages['total'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('total', $wages))
                                            @if ($wages['total'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $wages['total'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $wages['total'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                    <td
                                        style="background-color: #fff;
                                            white-space: nowrap;
                                            color: {{ array_key_exists('adjustment', $wages) && $wages['adjustment']['adjustment_amount'] < 0 ? 'red' : '#767373' }};
                                            text-align: center;
                                            font-size: 10px;
                                            font-weight: 500; padding: 5px; ">
                                        @if (array_key_exists('adjustment', $wages))
                                            @if ($wages['adjustment']['adjustment_amount'] >= 0)
                                                $
                                                {{ exportNumberFormat(abs((float) $wages['adjustment']['adjustment_amount'])) }}
                                            @else
                                                $
                                                ({{ exportNumberFormat(abs((float) $wages['adjustment']['adjustment_amount'])) }})
                                            @endif
                                        @else
                                            $ 0.00
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td style="background-color: #fff;
                                        color: #767373;
                                        text-align: center;
                                        font-size: 11px;
                                        font-weight: 500; padding: 20px;"
                                        colspan="8">
                                        No data found
                                    </td>
                                </tr>
                            @endforelse
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>

</html>
