
<?php 


$reimbursement = isset($data['miscellaneous']['reimbursement']['period_total']) ? $data['miscellaneous']['reimbursement']['period_total'] : 0.00;
$adjustment = isset($data['miscellaneous']['adjustment']['period_total']) ? $data['miscellaneous']['adjustment']['period_total'] : 0.00;

$gross_earning  = $reimbursement + $adjustment;
$net_pay = isset($data['pay_stub']['net_pay']) ? $data['pay_stub']['net_pay'] : 0.00;
$net_ytd = 0.00;


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paystub </title>
    <style>
        body{
            margin: 0;
        }
        @media (max-width: 580px) {
            .grid_cls{
                display: grid;
            }
        }
    </style>
</head>

<body>

    <div class="content">
        <!-- pdf contennt here  -->
        <div class="" style="background-color:#fafafa;">
            <div
                style="box-sizing:border-box;color:#74787e;line-height:1.4;width:100%!important;word-break:break-word;font-family:Helvetica,Arial,sans-serif;margin:0px;padding:0px;background-color:#ffffff;">
                <table role="presentation"
                    style="box-sizing:border-box;width:100%;border-collapse:collapse;border:0px;border-spacing:0px;font-family:Arial,Helvetica,sans-serif;background-color:rgb(239,239,239)">
                    <tbody
                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box">
                        <tr>
                            <td align="center"
                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:1rem 2rem;vertical-align:top;width:100%">
                                <table role="presentation"
                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box; width:100%; border-collapse:collapse;border:0px;border-spacing:0px;text-align:left">
                                    <tbody
                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box">
                                        <tr>
                                            <td
                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:20px 0px 0px; padding-top:0px;">
                                                <div
                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:20px;background-color:rgb(255,255,255); border-radius: 5px; padding-top:0px; max-width: 650px; margin: 0px auto;">
                                                    <div
                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;text-align:left">
                                                        <div
                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding-bottom:30px;">
                                                            <table style="width: 100%;">
                                                                <tr class="grid_cls">
                                                                    <td style="width: 85%;">
                                                                        <div style="width: 85%;">
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;">
                                                                               {{ isset($data['CompanyProfile']->business_name) ? $data['CompanyProfile']->business_name : '' }}
                                                                            </p>
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;">
                                                                                {{ isset($data['CompanyProfile']->business_address) ? $data['CompanyProfile']->business_address : ''}}
                                                                            </p>
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;">
                                                                                {{ isset($data['CompanyProfile']->business_phone) ? $data['CompanyProfile']->business_phone : ''}}
                                                                            </p>
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;"><a
                                                                                    href="{{  isset($data['CompanyProfile']->company_website) ? $data['CompanyProfile']->company_website : ''}}"
                                                                                    style="color: #767373; text-decoration: none;"
                                                                                    target="_blank">{{ isset($data['CompanyProfile']->company_website) ? $data['CompanyProfile']->company_website : ''}}</a>
                                                                            </p>
                                                                        </div>
                                                                    </td>

                                                                    <td style="width: 15%; text-align: right;">
                                                                        <p style="margin: 0px; text-align: right;">
                                                                            <img src="{{ isset($data['CompanyProfile']['logo']) ? $data['CompanyProfile']['logo'] : ''}}"
                                                                                alt="Company Logo" width="145">
                                                                        </p>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div
                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;color:rgb(0,0,0);text-align:left">
                                                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #000;
                                                        font-size: 14px;font-weight: 500;"><strong>Pay Date : {{ 
                                                            isset($data['pay_stub']['pay_date']) ?  date('m/d/Y',strtotime($data['pay_stub']['pay_date'])) : '-' }}</strong>
                                                        </p>
                                                        <table style="width: 100%;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';width: 150px;
                                                                background-color: #edf2fd;
                                                                color: #5379eb;
                                                                font-size: 24px;
                                                                text-align: center;
                                                                font-weight: 600;
                                                                letter-spacing: 1.6; padding: 5px; width: 40%" rowspan="2">PAY STUB</td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                text-align: center;
                                                                font-size: 14px;
                                                                font-weight: 500; padding: 5px;">Pay Period</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="width: 165px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">One-Time Payment
                                                                </td>
                                                            </tr>
                                                        </table>

                                                        <div style="margin-top: 10px;">
                                                            <table style="width: 100%;">
                                                                <tr class="grid_cls">
                                                                    <td>
                                                                        <div
                                                                            style="background-color: #f7f7f7; padding: 8px 10px;">
                                                                            <table style="width: 100%;" cellspacing="0"
                                                                                cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">Name</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">
                                                                                        {{ isset($data['employee']->first_name) ? $data['employee']->first_name : ''}} {{ isset($data['employee']->last_name) ? $data['employee']->last_name : ''}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        <div
                                                                            style="background-color: #f7f7f7; padding: 8px 10px; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0"
                                                                                cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">User ID</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">
                                                                                        {{ isset($data['employee']->employee_id) ? $data['employee']->employee_id : '' }}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        <div
                                                                            style="background-color: #f7f7f7; padding: 8px 10px; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0"
                                                                                cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">Address</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">
                                                                                        {{ isset($data['employee']->home_address) ? $data['employee']->home_address : '' }}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @if( isset($data['employee']->entity_type) && $data['employee']->entity_type == 'individual')
                                                                        <div
                                                                            style="background-color: #f7f7f7; padding: 8px 10px; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0"
                                                                                cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">SSN</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">
                                                                                       {{ isset($data['employee']->social_sequrity_no) ? $data['employee']->social_sequrity_no : ''}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @else
                                                                        <div
                                                                            style="background-color: #f7f7f7; padding: 8px 10px; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0"
                                                                                cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">EIN</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">
                                                                                       {{ isset($data['employee']->business_ein) ? $data['employee']->business_ein : ''}}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @endif
                                                                        <div
                                                                            style="background-color: #f7f7f7; padding: 8px 10px; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0"
                                                                                cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">Bank Acct.</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">
                                                                                        {{ isset($data['employee']->account_no) ? $data['employee']->account_no : '' }}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @if( isset($data['employee']->entity_type) && $data['employee']->entity_type != 'individual')
                                                                        <div
                                                                            style="background-color: #f7f7f7; padding: 8px 10px; margin-top: 3px;">
                                                                            <table style="width: 100%;" cellspacing="0"
                                                                                cellpadding="0">
                                                                                <tr>
                                                                                    <td style="width: 30%; color: rgb(96 120 236);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">Business Name</td>
                                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                                color: #767373;
                                                                            text-align: left;
                                                                            padding-left: 8px !important;
                                                                            font-size: 14px;
                                                                            font-weight: 500;">
                                                                                           {{ isset($data['employee']->business_name) ? $data['employee']->business_name : '' }}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                        @endif
                                                                    </td>

                                                                    <td
                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #5379eb;text-align: center;
                                                                font-size: 14px;font-weight: 500; padding: 5px; width: 50%; padding: 30px; width: 50%;">
                                                                        <div style="width: 70%;
                                                                        float: right;
                                                                        background-color: #edf2fd;
                                                                        border-radius: 15px;
                                                                        padding: 20px 25px;">
                                                                            <p
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #5379eb; font-size: 24px;font-weight: 600; text-align: left; margin-bottom: 15px;">
                                                                                Net Pay</p>
                                                                            <table style="width: 100%;">
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                        color: #5379eb;
                                                                        font-size: 16px;
                                                                        font-weight: 500; padding: 5px; text-align: left;">
                                                                                        This Pay check</td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                        color: #5379eb;
                                                                        font-size: 16px;
                                                                        font-weight: 500; padding: 5px; text-align: left;">
                                                                                        YTD</td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style=" color: <?php echo  $net_pay < 0 ? 'red' : '#727171' ?>; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: <?php echo  $net_pay < 0 ? 'red' : '#727171' ?>; text-align: center; font-size: 16px; font-weight: 500; padding: 5px; text-align: left;">
                                                                                        @if($net_pay >= 0) $ {{ exportNumberFormat(abs((float) $net_pay)) }} @else $ ({{ exportNumberFormat(abs((float)$net_pay)) }}) @endif
                                                                                    </td>
                                                                                    
                                                                                    <td
                                                                                        style="color: <?php echo  $net_ytd < 0 ? 'red' : '#727171' ?>; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; text-align: center; font-size: 16px; font-weight: 500; padding: 5px; text-align: left">
                                                                                        @if($net_ytd >= 0) $ {{ exportNumberFormat(abs((float) $net_ytd)) }} @else $ ({{ exportNumberFormat(abs((float)$net_ytd)) }}) @endif
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </table>
                                                        </div>

                                                        <div style="margin-top: 20px;">
                                                            <p
                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                            font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                                                ONE TIME PAYMENT</p>
                                                            <table style="width: 100%;">
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Description</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">TOTAl</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Adjustment</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">
                                                                       $ {{$adjustment}}
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Reimbursement</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">
                                                                        $ {{$reimbursement}}
                                                                    </td>
                                                                </tr>
                                                               
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: right;
                                                                    padding-right: 8px !important;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">Gross Earning</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">$ {{$gross_earning}}</td>
                                                                </tr>

                                                            </table>
                                                        </div>
                                                        <!-- Adjustment -->
                                                        <table style="width: 100%;">
                                                            <tr>
                                                            <td style="background-color: #fff;color: #000;text-align: center; font-size: 13px;font-weight: 500; padding: 5px;">
                                                            <p style="margin-bottom:5px; margin-top:0px;color: #767373; font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;"> Adjustments</p>
                                                            </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="background-color: #e0e0e0;
                                                                color: #000;
                                                                text-align: center;
                                                                font-size: 12px;
                                                                font-weight: 500; padding: 5px; ">
                                                                    Approved By</td>
                                                                <td style="background-color: #e0e0e0;
                                                                color: #000;
                                                                text-align: center;
                                                                font-size: 12px;
                                                                font-weight: 500; padding: 5px; ">Date
                                                                </td>
                                                                <td style="background-color: #e0e0e0;
                                                                color: #000;
                                                                text-align: center;
                                                                font-size: 12px;
                                                                font-weight: 500; padding: 5px; ">Type</td>
                                                                <td style="background-color: #e0e0e0;
                                                                color: #000;
                                                                text-align: center;
                                                                font-size: 12px;
                                                                font-weight: 500; padding: 5px; ">Amount
                                                                </td>
                                                                <td style="background-color: #e0e0e0;
                                                                color: #000;
                                                                text-align: center;
                                                                font-size: 12px;
                                                                font-weight: 500; padding: 5px; ">
                                                                    Description</td>
                                                            </tr>
                                                            @forelse($adjustment_details as $adjustment)
                                                                <tr>
                                                                        <td style="color: #767373; text-align: center; font-size: 12px; font-weight: 500; padding: 5px; ">
                                                                            {{ isset($adjustment['first_name']) ? $adjustment['first_name'] : '' }} {{ isset($adjustment['first_name']) ? $adjustment['last_name'] : '' }}
                                                                        </td>
                                                                        <td style="background-color: #fff;
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">
                                                                            {{ isset($adjustment['date']) ? date('m/d/Y',strtotime($adjustment['date'])) : ''}}
                                                                        </td>
                                                                        <td style="background-color: #fff;
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">
                                                                            {{ isset($adjustment['type']) ? $adjustment['type'] : ''}}
                                                                        </td>
                                                                        <td style="background-color: #fff;
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">

                                                                            $ {{ isset($adjustment['amount']) ? ($adjustment['amount']
                                                                                < 0 ? '('.number_format(abs((float)$adjustment[ 'amount']), 2). ')' : number_format($adjustment[ 'amount'], 2)) : '' }}
                                                                        </td>
                                                                        <td style="background-color: #fff;
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">
                                                                            {{ isset($adjustment['description']) ? $adjustment['description'] : '' }}
                                                                        </td>
                                                                </tr>
                                                            @empty
                                                                <tr>
                                                                    <td style="background-color: #fff;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 13px;
                                                                    font-weight: 500; padding: 20px;" colspan="5">
                                                                        No data found
                                                                    </td>
                                                                </tr>
                                                            @endforelse
                                                        </table>

                                                        <!-- Reimbursment -->
                                                        <table style="width: 100%;">
                                                                <tr>

                                                                <p style="margin-bottom:5px; margin-top:0px;color: #767373; font-size: 13px;font-weight: 500; text-align: left; margin-bottom: 10px; text-transform: uppercase;"> Reimbursements </p>
                                                                </tr>
                                                                <tr>
                                                                    <td style="background-color: #e0e0e0;
                                                                    color: #000;
                                                                    text-align: center;
                                                                    font-size: 12px;
                                                                    font-weight: 500; padding: 5px; ">
                                                                        Approved By</td>
                                                                    <td style="background-color: #e0e0e0;
                                                                    color: #000;
                                                                    text-align: center;
                                                                    font-size: 12px;
                                                                    font-weight: 500; padding: 5px; ">Date
                                                                    </td>
                                                                    <td style="background-color: #e0e0e0;
                                                                    color: #000;
                                                                    text-align: center;
                                                                    font-size: 12px;
                                                                    font-weight: 500; padding: 5px; ">Amount
                                                                    </td>
                                                                    <td style="background-color: #e0e0e0;
                                                                    color: #000;
                                                                    text-align: center;
                                                                    font-size: 12px;
                                                                    font-weight: 500; padding: 5px; ">
                                                                        Description</td>
                                                                </tr>
                                                                @forelse($reimbursement_details as $reimbursement)
                                                                    <tr>
                                                                        <td style="
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">
                                                                            {{ isset($reimbursement['first_name']) ? $reimbursement['first_name'] : '' }} {{ isset($reimbursement['first_name']) ? $reimbursement['last_name'] : '' }}
                                                                        </td>
                                                                        <td style="
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">
                                                                            {{ isset($reimbursement['date']) ? date('m/d/Y',strtotime($reimbursement['date'])) : ''}}
                                                                        </td>
                                                                        <td style="
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">

                                                                            $ {{ isset($reimbursement['amount']) ? ($reimbursement['amount']
                                                                                < 0 ? '('.number_format(abs((float)$reimbursement[ 'amount']), 2). ')' : number_format($reimbursement[ 'amount'],
                                                                                2)) : 0.00 }} </td>
                                                                        <td style="
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 12px;
                                                                        font-weight: 500; padding: 5px; ">
                                                                            {{ isset($reimbursement['description']) ? $reimbursement['description'] : '' }}
                                                                        </td>
                                                                    </tr>
                                                                @empty
                                                                    <tr>
                                                                        <td style="
                                                                        color: #767373;
                                                                        text-align: center;
                                                                        font-size: 13px;
                                                                        font-weight: 500; padding: 20px;" colspan="8">
                                                                            No data found
                                                                        </td>
                                                                    </tr>
                                                                @endforelse
                                                        </table>
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

    </div>

</body>

</html>