<?php
// Calculate pay commission

$payCommission = isset($data['earnings']['commission']['period_total']) ? floatval(str_replace(',', '', $data['earnings']['commission']['period_total'])) : 0.00;
$payCommissionYtd = isset($data['earnings']['commission']['ytd_total']) ? floatval(str_replace(',', '', $data['earnings']['commission']['ytd_total'])) : 0.00;

// Calculate pay overrides
$payoverrides = isset($data['earnings']['overrides']['period_total']) ? floatval(str_replace(',', '', $data['earnings']['overrides']['period_total'])) : 0.00;
$payoverridesYtd = isset($data['earnings']['overrides']['ytd_total']) ? floatval(str_replace(',', '', $data['earnings']['overrides']['ytd_total'])) : 0.00;

// Calculate pay reconciliation
$payReconciliation = isset($data['earnings']['reconciliation']['period_total']) ? floatval(str_replace(',', '', $data['earnings']['reconciliation']['period_total'])) : 0.00;
$payReconciliationYtd = isset($data['earnings']['reconciliation']['ytd_total']) ? floatval(str_replace(',', '', $data['earnings']['reconciliation']['ytd_total'])) : 0.00;

// Calculate miscellaneous adjustment
$miscellaneousAdjustment = isset($data['miscellaneous']['adjustment']['period_total']) ? floatval(str_replace(',', '', $data['miscellaneous']['adjustment']['period_total'])) : 0.00;
$miscellaneousAdjustmentYtd = isset($data['miscellaneous']['adjustment']['ytd_total']) ? floatval(str_replace(',', '', $data['miscellaneous']['adjustment']['ytd_total'])) : 0.00;

// Calculate miscellaneous reimbursement
$miscellaneousReimbursement = isset($data['miscellaneous']['reimbursement']['period_total']) ? floatval(str_replace(',', '', $data['miscellaneous']['reimbursement']['period_total'])) : 0.00;
$miscellaneousReimbursementYtd = isset($data['miscellaneous']['reimbursement']['ytd_total']) ? floatval(str_replace(',', '', $data['miscellaneous']['reimbursement']['ytd_total'])) : 0.00;

// Calculate net YTD
// Calculate pay stub net pay
// dd($payCommissionYtd , $payoverridesYtd , $payReconciliationYtd , $miscellaneousAdjustmentYtd , $miscellaneousReimbursementYtd);
$netYTD = number_format(($payCommissionYtd + $payoverridesYtd + $payReconciliationYtd + $miscellaneousAdjustmentYtd + $miscellaneousReimbursementYtd), 2);
$pay_stub_net_pay = isset($data['pay_stub']['net_pay']) ? number_format($data['pay_stub']['net_pay'], 2) : 0.00;

// Calculate gross pay
$grossPay = number_format(($payCommission + $payoverrides + $payReconciliation), 2);
$grossPayYTD = number_format(($payCommissionYtd + $payoverridesYtd + $payReconciliationYtd), 2);

// Calculate miscellaneous total
$miscellaneousTotal = number_format(($miscellaneousAdjustment + $miscellaneousReimbursement), 2);
$miscellaneousTotalYTD = number_format(($miscellaneousReimbursementYtd + $miscellaneousAdjustmentYtd), 2);

$payCommission = number_format($payCommission, 2);
$payCommissionYtd = number_format($payCommissionYtd, 2);
$payoverrides = number_format($payoverrides, 2);
$payoverridesYtd = number_format($payoverridesYtd, 2);
$payReconciliation = number_format($payReconciliation, 2);
$payReconciliationYtd = number_format($payReconciliationYtd, 2);
$miscellaneousAdjustment = number_format($miscellaneousAdjustment, 2);
$miscellaneousAdjustmentYtd = number_format($miscellaneousAdjustmentYtd, 2);
$miscellaneousReimbursement = number_format($miscellaneousReimbursement, 2);
$miscellaneousReimbursementYtd = number_format($miscellaneousReimbursementYtd, 2);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paystub </title>
    <style>
        .page-break {
            page-break-after: always;
        }
    </style>

</head>

<body>

    <div class="content">
        <!-- pdf contennt here  -->
        <div class="" style="background-color:#fafafa;">
            <div style="box-sizing:border-box;color:#74787e;line-height:1.4;width:100%!important;word-break:break-word;font-family:Helvetica,Arial,sans-serif;margin:0px;padding:0px;background-color:#ffffff">
                <table role="presentation" style="box-sizing:border-box;width:100%;border-collapse:collapse;border:0px;border-spacing:0px;font-family:Arial,Helvetica,sans-serif;background-color:rgb(239,239,239)">
                    <tbody style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box">
                        <tr>
                            <td align="center" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:1rem 2rem;vertical-align:top;width:100%">
                                <table role="presentation" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box; width:100%; border-collapse:collapse;border:0px;border-spacing:0px;text-align:left">
                                    <tbody style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box">
                                        <tr>
                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:20px 0px 0px; padding-top:0px;">
                                                <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:20px;background-color:rgb(255,255,255); border-radius: 5px; padding-top:0px;">
                                                    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;text-align:left">
                                                        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding-bottom:10px;">
                                                            <table style="width: 100%;">
                                                                <tr>
                                                                    <td style="width: 85%;">
                                                                        <div style="width: 85%;">
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;">
                                                                                {{$data['CompanyProfile']->business_name}}
                                                                            </p>
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;">
                                                                                {{$data['CompanyProfile']->business_address}}
                                                                            </p>
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;">
                                                                                {{$data['CompanyProfile']->business_phone}}
                                                                            </p>
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 13px;font-weight: 500;"><a href="{{$data['CompanyProfile']->company_website}}" style="color: #767373; text-decoration: none;" target="_blank">{{$data['CompanyProfile']->company_website}}</a>
                                                                            </p>
                                                                        </div>
                                                                    </td>

                                                                    <td style="width: 15%; text-align: right;">
                                                                        <p style="margin: 0px; text-align: right;">
                                                                            <img src="{{$data['CompanyProfile']['logo']}}" alt="" width="145">
                                                                        </p>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;color:rgb(0,0,0);text-align:left">
                                                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #000;
                                                        font-size: 13px;font-weight: 500;"><strong>Pay Date : {{
                                                                date('m/d/Y',strtotime($data['pay_stub']['pay_date']))}}</strong>
                                                        </p>
                                                        <table style="width: 100%;">
                                                            <tr>
                                                                <td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; width: 150px; background-color: #edf2fd; color: #5379eb; font-size: 20px; text-align: center; font-weight: 600; letter-spacing: 1.6; padding: 5px;" rowspan="2">
                                                                    PAY STUB
                                                                </td>

                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                text-align: center;
                                                                font-size: 13px;
                                                                font-weight: 500; padding: 5px;">Pay Period</td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                            text-align: center;
                                                            font-size: 13px;
                                                            font-weight: 500; padding: 5px;">Accounts this pay period
                                                                </td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                            color: #5379eb;
                                                            text-align: center;
                                                            font-size: 13px;
                                                            font-weight: 500;  padding: 5px;">YTD</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="width: 165px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">{{ date('m/d/Y',strtotime($data['pay_stub']['pay_period_from']))}} - {{ date('m/d/Y',strtotime($data['pay_stub']['pay_period_to']))}}
                                                                </td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                text-align: center;
                                                                font-size: 13px;
                                                                font-weight: 500; padding: 5px;">
                                                                    {{$data['pay_stub']['period_sale_count']}}
                                                                </td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                color: #767373;
                                                                text-align: center;
                                                                font-size: 13px;
                                                                font-weight: 500; padding: 5px;">
                                                                    {{$data['pay_stub']['ytd_sale_count']}}
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
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;padding: 8px 10px; background-color: #edf2fd;">Name</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                                                        {{$data['employee']->first_name}} {{$data['employee']->last_name}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        <div style="background-color: #f7f7f7; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">User ID</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                                                        {{$data['employee']->employee_id}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        <div style="background-color: #f7f7f7;  margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">Address</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                                                        {{$data['employee']->home_address}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @if($data['employee']->entity_type == 'individual')
                                                                        <div style="background-color: #f7f7f7;  margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">SSN</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                                                        {{$data['employee']->social_sequrity_no}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @else
                                                                        <div style="background-color: #f7f7f7;  margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">EIN</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                                                        {{$data['employee']->business_ein}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @endif
                                                                        <div style="background-color: #f7f7f7; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">Bank Acct.</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                                                        {{$data['employee']->account_no}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @if($data['employee']->entity_type != 'individual')
                                                                        <div style="background-color: #f7f7f7; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0" cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 8px 10px; background-color: #edf2fd;">Business Name</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 13px;
                                                                            font-weight: 500;">
                                                                                        {{$data['employee']->business_name}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @endif
                                                                    </td>

                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #5379eb;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 0px; vertical-align:top">
                                                                        <div style="
                                                                        background-color: #edf2fd;
                                                                        border-radius: 15px;
                                                                        padding: 20px 25px; float:right;">
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #5379eb; font-size: 20px;font-weight: 600; text-align: left; margin-bottom: 5px;">
                                                                                Net Pay</p>
                                                                            <table style="width: 100%;">
                                                                                <tr>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                        color: #5379eb;
                                                                        width: 50%;
                                                                        font-size: 14px;
                                                                        font-weight: 500; padding: 5px; text-align: left; white-space:nowrap">
                                                                                        This Pay check</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                        color: #5379eb;
                                                                        width: 50%;
                                                                        font-size: 14px;
                                                                        font-weight: 500; padding: 5px; text-align: left;white-space:nowrap">
                                                                                        YTD</td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #727171; text-align: center; font-size: 14px; font-weight: 500; padding: 5px; text-align: left;white-space:nowrap">
                                                                                        @if($pay_stub_net_pay >= 0) $ {{$pay_stub_net_pay }} @else $ ({{ $pay_stub_net_pay }}) @endif
                                                                                    </td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #727171; text-align: center; font-size: 14px; font-weight: 500; padding: 5px; text-align: left ; white-space:nowrap">
                                                                                        @if($netYTD >= 0) $ {{ $netYTD }} @else $ ({{ $netYTD }}) @endif

                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>

                                                        <div style="margin-top: 0px;">
                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                            font-size: 13px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                                                Earnings</p>
                                                            <table style="width: 100%;">
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Description</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">Total</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">YTD</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Commisions</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($payCommission >= 0) $ {{ $payCommission }} @else $ ({{ $payCommission }}) @endif

                                                                    </td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($payCommissionYtd >=0) $ {{ $payCommissionYtd }} @else $ ({{ $payCommissionYtd }}) @endif

                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Overrides</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($payoverrides >= 0) $ {{ $payoverrides }} @else $ ({{ $payoverrides }}) @endif
                                                                    </td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($payoverridesYtd >= 0) $ {{ $payoverridesYtd }} @else $ ({{ $payoverridesYtd }}) @endif
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Reconciliations</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($payReconciliation >= 0) $ {{ $payReconciliation }} @else $ ({{ $payReconciliation }}) @endif
                                                                    </td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($payReconciliationYtd >= 0) $ {{ $payReconciliationYtd }} @else $ ({{ $payReconciliationYtd }}) @endif
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: right;
                                                                    padding-right: 8px !important;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">Gross Earning</td>

                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($grossPay >= 0 ) $ {{ $grossPay }} @else $ ({{ $grossPay }}) @endif
                                                                    </td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        @if ($grossPayYTD >= 0) $ {{ $grossPayYTD }} @else $ ({{ $grossPayYTD }}) @endif

                                                                    </td>
                                                                </tr>

                                                            </table>
                                                        </div>
                                                        <div style="margin-top: 10px;">
                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                            font-size: 13px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                                                Deductions</p>
                                                            <table style="width: 100%;">
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Description</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">Total</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">YTD</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Standard Deductions</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">$ 0.00</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 5px;">$ 0.00</td>
                                                                </tr>

                                                            </table>
                                                        </div>
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #5379eb;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px; width: 70%;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Miscellaneous</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                                                Description</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">Total</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">YTD</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                                                Adjustments</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                                                @if ($miscellaneousAdjustment>=0) $ {{ $miscellaneousAdjustment }} @else $ ({{ $miscellaneousAdjustment }}) @endif
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                                                @if ($miscellaneousAdjustmentYtd >= 0) $ {{ $miscellaneousAdjustmentYtd }} @else $ ({{ $miscellaneousAdjustmentYtd }}) @endif
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                                                Reimbursements</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                                                @if ($miscellaneousReimbursement >= 0) $ {{ $miscellaneousReimbursement }} @else $ ({{ $miscellaneousReimbursement }}) @endif

                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                                                @if ($miscellaneousReimbursementYtd >= 0) $ {{ $miscellaneousReimbursementYtd }} @else $ ({{ $miscellaneousReimbursementYtd }}) @endif
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                                                Total</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                                                @if ($miscellaneousTotal >= 0) $ {{ $miscellaneousTotal }} @else $ ({{ $miscellaneousTotal }}) @endif

                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 5px;">
                                                                                @if ($miscellaneousTotalYTD >= 0) $ {{ $miscellaneousTotalYTD }} @else $ ({{ $miscellaneousTotalYTD }}) @endif

                                                                            </td>
                                                                        </tr>

                                                                    </table>
                                                                </td>


                                                            </tr>
                                                        </table>
                                                        <!-- brackdopwn start   -->

                                                        
                                                        <!-- Overrides start  -->
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #000;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Overrides</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                PID</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Customer Name
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Override Over
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Type</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">KW installed
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Override
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Total Amount
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Adjustment
                                                                            </td>
                                                                        </tr>
                                                                        @if (count($override_details) > 0) @foreach($override_details as $override)
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($override['pid']) ? $override['pid'] : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($override['customer_name']) ? $override['customer_name'] : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($override['first_name']) ? $override['first_name'] : ''}} {{ isset($override['first_name']) ? $override['last_name'] : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{isset($override['type']) ? $override['type'] : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($override['kw_installed']) ? $override['kw_installed'] : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($override['calculated_redline']) ? number_format($override['calculated_redline'],2) : 0.00 }} {{ isset($override['override_type'])? $override['override_type'] : 'Per Watt' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">

                                                                                $ {{ isset($override['total_amount']) ? ($override['total_amount']
                                                                                    < 0 ? '('.number_format($override[ 'total_amount'], 2). ')' : number_format($override[
                                                                                    'total_amount'], 2)) : '0.00' }} </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                ${{ isset($override['amount']) ? ($override['amount']
                                                                                            < 0 ? '('.number_format($override[ 'amount'], 2). ')' : number_format($override[ 'amount'], 2)) : '0.00'
                                                                                            }} </td>
                                                                        </tr>
                                                                        @endforeach @else
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 20px;" colspan="8">
                                                                                No data found
                                                                            </td>
                                                                        </tr>
                                                                        @endif
                                                                    </table>
                                                                </td>


                                                            </tr>
                                                        </table>
                                                        <!-- Reconciliation start  -->
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #000;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Reconciliation</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Added to payroll</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Start date/end date (Payout %)
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Commission Withheld
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Override Due
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Clawbacks
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Adjustments
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Payout
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 20px;" colspan="8">
                                                                                No data found
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>


                                                            </tr>
                                                        </table>

                                                        <!-- Deduction start  -->
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #000;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Deduction</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Type</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Amount
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Limit</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Total</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Outstanding</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Adjustment
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 20px;" colspan="8">
                                                                                No data found
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table>

                                                        <!-- Adjustments start  -->
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #000;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Adjustments</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Approved By</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Date
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Type</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Amount
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Description</td>
                                                                        </tr>
                                                                        @if (count($adjustment_details) > 0) @foreach($adjustment_details as $adjustment)
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($adjustment['first_name']) ? $adjustment['first_name'] : '' }} {{ isset($adjustment['first_name']) ? $adjustment['last_name'] : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($adjustment['date']) ? date('m/d/Y',strtotime($adjustment['date'])) : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($adjustment['type']) ? $adjustment['type'] : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">

                                                                                $ {{ isset($adjustment['amount']) ? ($adjustment['amount']
                                                                                    < 0 ? '('.number_format($adjustment[ 'amount'], 2). ')' : number_format($adjustment[ 'amount'], 2)) : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($adjustment['description']) ? $adjustment['description'] : '' }}
                                                                            </td>
                                                                        </tr>
                                                                        @endforeach @else
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 20px;" colspan="5">
                                                                                No data found
                                                                            </td>
                                                                        </tr>
                                                                        @endif
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table>

                                                        <!-- Reimbursements start  -->
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #000;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Reimbursements</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Approved By</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Date
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Amount
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Description</td>
                                                                        </tr>
                                                                        @if (count($reimbursement_details) > 0) @foreach($reimbursement_details as $reimbursement)
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($reimbursement['first_name']) ? $reimbursement['first_name'] : '' }} {{ isset($reimbursement['first_name']) ? $reimbursement['last_name'] : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($reimbursement['date']) ? date('m/d/Y',strtotime($reimbursement['date'])) : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">

                                                                                $ {{ isset($reimbursement['amount']) ? ($reimbursement['amount']
                                                                                    < 0 ? '('.number_format($reimbursement[ 'amount'], 2). ')' : number_format($reimbursement[ 'amount'],
                                                                                    2)) : 0.00 }} </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($reimbursement['description']) ? $reimbursement['description'] : '' }}
                                                                            </td>
                                                                        </tr>
                                                                        @endforeach @else
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 20px;" colspan="8">
                                                                                No data found
                                                                            </td>
                                                                        </tr>
                                                                        @endif
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                        <div class="page-break"></div>
                                                        <!-- Commission start  -->
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #000;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Commission</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                PID</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Customer
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">State</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Rep Redline
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">KW</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Net EPC
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Adders
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Amount
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Adjustment
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Type</td>
                                                                        </tr>
                                                                        @if (count($commission_details) > 0) @foreach($commission_details as $value)
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($value['pid']) ? $value['pid'] : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($value['customer_name']) ? $value['customer_name'] : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($value['customer_state']) ? $value['customer_state'] : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{isset($value['rep_redline']) ? $value['rep_redline'] : ''}}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($value['kw']) ? $value['kw'] : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($value['net_epc'])? $value['net_epc'] : '' }}
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                $ {{ isset($value['adders']) ? ($value['adders']
                                                                                    < 0 ? '('.number_format($value[ 'adders'], 2). ')' : number_format($value[ 'adders'], 2)) : '0.00' }} </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                $ {{ isset($value['amount']) ? ($value['amount']
                                                                                            < 0 ? '('.number_format($value[ 'amount'], 2). ')' : number_format($value[ 'amount'], 2)) : '0.00' }} {{ isset($value[
                                                                                            'date']) ? date( 'm/d/Y',strtotime($value[ 'date'])) : '' }} </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                $ {{ isset($value['adjustAmount']) ? ($value['adjustAmount']
                                                                                                    < 0 ? '('.number_format($value[ 'adjustAmount'], 2). ')' : number_format($value[
                                                                                                    'adjustAmount'], 2)) : '0.00' }} </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                {{ isset($value['amount_type']) ? $value['amount_type'] : ''}}
                                                                            </td>
                                                                        </tr>
                                                                        @endforeach @else
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 20px;" colspan="10">
                                                                                No data found
                                                                            </td>
                                                                        </tr>
                                                                        @endif
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table>


                                                        <!-- Additional Values start  -->
                                                        <!-- <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #000;text-align: center;
                                                                font-size: 13px;font-weight: 500; padding: 5px;">
                                                                    <p
                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;">
                                                                        Additional Values </p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">
                                                                                Type</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Date
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Amount
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Comment
                                                                            </td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #e0e0e0;
                                                                            color: #000;
                                                                            text-align: center;
                                                                            font-size: 12px;
                                                                            font-weight: 500; padding: 5px; ">Approved
                                                                                By</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 13px;
                                                                            font-weight: 500; padding: 20px;"
                                                                                colspan="8">
                                                                                No data found
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table> -->

                                                        <!-- brackdopwn end   -->
                                                        <!-- </div> -->
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- end pdf contennt here  -->
    </div>

</body>
<footer>
    Lorem ipsum dolor sit amet consectetur adipisicing elit. Maiores unde neque dolores explicabo qui totam, soluta, maxime aut quae magnam reprehenderit tenetur quisquam odio vero aperiam accusantium illo sint nihil. Lorem ipsum dolor sit amet consectetur
    adipisicing elit. Maiores unde neque dolores explicabo qui totam, soluta, maxime aut quae magnam reprehenderit tenetur quisquam odio vero aperiam accusantium illo sint nihil. Lorem ipsum dolor sit amet consectetur adipisicing elit. Maiores unde
    neque dolores explicabo qui totam, soluta, maxime aut quae magnam reprehenderit tenetur quisquam odio vero aperiam accusantium illo sint nihil. Lorem ipsum dolor sit amet consectetur adipisicing elit. Maiores unde neque dolores explicabo qui totam,
    soluta, maxime aut quae magnam reprehenderit tenetur quisquam odio vero aperiam accusantium illo sint nihil.
</footer>

</html>