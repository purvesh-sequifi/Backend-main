<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paystub </title>
</head>
<body>
   
    <div class="content">
        <!-- pdf contennt here  -->
        <div class="" style="background-color:#fafafa;">
            <div style="box-sizing:border-box;color:#74787e;line-height:1.4;width:100%!important;word-break:break-word;font-family:Helvetica,Arial,sans-serif;margin:0px;padding:0px;background-color:#ffffff">
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
                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:20px 0px 0px; padding-top:0px;">
                                                <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:20px;background-color:rgb(255,255,255); border-radius: 5px; padding-top:0px;">
                                                    <div
                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;text-align:left">
                                                        <div
                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding-bottom:30px;">
                                                            <table style="width: 100%;">
                                                                <tr>
                                                                    <td style="width: 85%;">
                                                                       <div style="width: 85%;">
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;">{{$data['CompanyProfile']->business_name}}
                                                                                </p>
                                                                                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;">{{$data['CompanyProfile']->business_address}}</p>
                                                                                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;">{{$data['CompanyProfile']->business_phone}}</p>
                                                                                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                            font-size: 14px;font-weight: 500;"><a
                                                                                        href="{{$data['CompanyProfile']->company_website}}"
                                                                                        style="color: #767373; text-decoration: none;"
                                                                                        target="_blank">{{$data['CompanyProfile']->company_website}}</a></p>
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
                                                    <div
                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;color:rgb(0,0,0);text-align:left">
                                                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #000;
                                                        font-size: 14px;font-weight: 500;"><strong>Pay Date : {{ date('m/d/Y',strtotime($data['pay_stub']['pay_date']))}}</strong></p>
                                                        <table style="width: 100%;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';width: 150px;
                                                                background-color: #edf2fd;
                                                                color: #5379eb;
                                                                font-size: 24px;
                                                                text-align: center;
                                                                font-weight: 600;
                                                                letter-spacing: 1.6; padding: 5px;" rowspan="2">PAY STUB
                                                                </td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                text-align: center;
                                                                font-size: 14px;
                                                                font-weight: 500; padding: 5px;">Pay Period</td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                            text-align: center;
                                                            font-size: 14px;
                                                            font-weight: 500; padding: 5px;">Accounts this pay period
                                                                </td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                            color: #5379eb;
                                                            text-align: center;
                                                            font-size: 14px;
                                                            font-weight: 500;  padding: 5px;">YTD</td>
                                                            </tr>
                                                            <tr>
                                                                <td style="width: 165px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">{{ date('m/d/Y',strtotime($data['pay_stub']['pay_period_from']))}} -
                                                                    {{ date('m/d/Y',strtotime($data['pay_stub']['pay_period_to']))}}</td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                text-align: center;
                                                                font-size: 14px;
                                                                font-weight: 500; padding: 5px;">  {{$data['pay_stub']['period_sale_count']}}</td>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                color: #767373;
                                                                text-align: center;
                                                                font-size: 14px;
                                                                font-weight: 500; padding: 5px;"> {{$data['pay_stub']['ytd_sale_count']}} </td>
                                                            </tr>
                                                        </table>
                                                        <div style="margin-top: 40px;">
                                                            <p
                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373; font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                                                Employee Information</p>
                                                            <table style="width: 100%;">
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                text-align: left;
                                                                padding-left: 8px !important;
                                                                font-size: 14px;
                                                                font-weight: 500; padding: 5px; width: 130px;">Employee
                                                                    </td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                color: #767373;
                                                            text-align: left;
                                                            padding-left: 8px !important;
                                                            font-size: 14px;
                                                            font-weight: 500; padding: 5px;">{{$data['employee']->first_name}} {{$data['employee']->last_name}}</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                            color: #5379eb;
                                                            text-align: left;
                                                            padding-left: 8px !important;
                                                            font-size: 14px;
                                                            font-weight: 500;  padding: 5px; width: 130px;">Employee ID
                                                                    </td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                            color: #767373;
                                                            text-align: left;
                                                            padding-left: 8px !important;
                                                            font-size: 14px;
                                                            font-weight: 500;  padding: 5px;">{{$data['employee']->employee_id}}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: left;
                                                                    padding-left: 8px !important;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 130px;">SSN
                                                                    </td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                text-align: left;
                                                                padding-left: 8px !important;
                                                                font-size: 14px;
                                                                font-weight: 500; padding: 5px;">{{$data['employee']->social_sequrity_no}}</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                text-align: left;
                                                                padding-left: 8px !important;
                                                                font-size: 14px;
                                                                font-weight: 500;  padding: 5px; width: 130px;">Bank
                                                                        Account</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                color: #767373;
                                                                text-align: left;
                                                                padding-left: 8px !important;
                                                                font-size: 14px;
                                                                font-weight: 500;  padding: 5px;">{{$data['employee']->account_no}}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: left;
                                                                    padding-left: 8px !important;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 130px;">
                                                                        Address</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                text-align: left;
                                                                padding-left: 8px !important;
                                                                font-size: 14px;
                                                                font-weight: 500; padding: 5px;" colspan="3">{{$data['employee']->home_address}}</td>
                                                                </tr>

                                                            </table>
                                                        </div>
                                                        <div style="margin-top: 20px;">
                                                            <p
                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                            font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                                                Earnings</p>
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
                                                                    font-weight: 500; padding: 5px;">Total</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">YTD</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Commisions</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{isset($data['earnings']['commission']['period_total'])?$data['earnings']['commission']['period_total']:0}}</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{isset($data['earnings']['commission']['ytd_total'])?$data['earnings']['commission']['ytd_total']:0}}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Overrides</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{isset($data['earnings']['overrides']['period_total'])?$data['earnings']['overrides']['period_total']:0}}</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{isset($data['earnings']['overrides']['ytd_total'])?$data['earnings']['overrides']['ytd_total']:0}}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Reconciliations</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{isset(['earnings']['reconciliation']['period_total'])?['earnings']['reconciliation']['period_total']:0}}</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{isset(['earnings']['reconciliation']['ytd_total'])?$data['earnings']['reconciliation']['ytd_total']:0}}</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: right;
                                                                    padding-right: 8px !important;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">Gross
                                                                        Pay</td>
                                                                        <?php 
                                                                        $payCommission = isset($data['earnings']['commission']['period_total'])?$data['earnings']['commission']['period_total']:0;
                                                                        $payoverrides = isset($data['earnings']['overrides']['period_total'])?$data['earnings']['overrides']['period_total']:0;
                                                                        $payReconciliation = isset($data['earnings']['reconciliation']['period_total'])?$data['earnings']['reconciliation']['period_total']:0;

                                                                        $payCommissionYtd = isset($data['earnings']['commission']['ytd_total'])?$data['earnings']['commission']['ytd_total']:0;
                                                                        $payoverridesYtd = isset($data['earnings']['overrides']['ytd_total'])?$data['earnings']['overrides']['ytd_total']:0;
                                                                        $payReconciliationYtd = isset( $data['earnings']['reconciliation']['ytd_total'])?$data['earnings']['reconciliation']['ytd_total']:0;
                                                                        
                                                                        ?>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{$payCommission + $payoverrides + $payReconciliation}}</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">${{$payCommissionYtd + $payoverridesYtd + $payReconciliationYtd}}</td>
                                                                </tr>

                                                            </table>
                                                        </div>
                                                        <div style="margin-top: 10px;">
                                                            <p
                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                            font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                                                Deductions</p>
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
                                                                    font-weight: 500; padding: 5px;">Total</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                    color: #5379eb;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">YTD</td>
                                                                </tr>
                                                                <tr>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px; width: 60%;">
                                                                        Standard Deductions</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">0</td>
                                                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                    color: #767373;
                                                                    text-align: center;
                                                                    font-size: 14px;
                                                                    font-weight: 500; padding: 5px;">0</td>
                                                                </tr>

                                                            </table>
                                                        </div>
                                                        <table style="width: 100%; margin-top: 10px;">
                                                            <tr>
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #5379eb;text-align: center;
                                                                font-size: 14px;font-weight: 500; padding: 5px; width: 70%;">
                                                                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                                                                    font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;"> Miscellaneous</p>
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
                                                                            font-weight: 500; padding: 5px;">Total</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">YTD</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                                                Adjustments</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">${{$data['miscellaneous']['adjustment']['period_total']}}</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">${{$data['miscellaneous']['adjustment']['ytd_total']}}</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                                                Reimbursements</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">${{$data['miscellaneous']['reimbursement']['period_total']}}</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                                                            color: #767373;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">${{$data['miscellaneous']['reimbursement']['ytd_total']}}</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px; width: 60%;">
                                                                                Total</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">${{$data['miscellaneous']['adjustment']['period_total']+$data['miscellaneous']['reimbursement']['period_total']}}</td>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">${{$data['miscellaneous']['adjustment']['ytd_total']+$data['miscellaneous']['reimbursement']['ytd_total']}}</td>
                                                                        </tr>

                                                                    </table>
                                                                </td>   
                                                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #fff;color: #5379eb;text-align: center;
                                                                font-size: 14px;font-weight: 500; padding: 5px; width: 30%;">
                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373; font-size: 16px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">NET PAY</p>
                                                                    <table style="width: 100%;">
                                                                        <tr>
                                                                            <td  style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                                                            color: #5379eb;
                                                                            text-align: center;
                                                                            font-size: 14px;
                                                                            font-weight: 500; padding: 5px;">TOTAL</td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7; color: #000; text-align: center; font-size: 17px; font-weight: 500; padding: 5px;"><strong>${{$data['pay_stub']['net_pay']}}</strong>
                                                                            </td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
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

        <!-- end pdf contennt here  -->
    </div>
   
</body>
</html>