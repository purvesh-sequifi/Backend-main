<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        p {
            margin: .35em;
        }

        body {
            margin: 0;
        }

        @media only screen and (max-width: 600px) {
            .table-mainParent {
                width: 100% !important;
            }
        }
    </style>
</head>

@php
    $companyProfile = App\Models\CompanyProfile::first();
    $companyAndOtherStaticImages = \App\Models\SequiDocsEmailSettings::company_and_other_static_images($companyProfile);

    $businessName = $companyProfile->business_name;
    $businessPhone = $companyProfile->business_phone;
    $companyEmail = $companyProfile->company_email;
    $businessAddress = $companyProfile->business_address;
    $footerContent = "$businessName |  + $businessPhone  |  $companyEmail | $businessAddress";

    $logo = $companyAndOtherStaticImages['Company_Logo'];
    $name = $companyProfile->name;
    $companyWebsite = $companyProfile->company_website;
    $sequifiLogoWithName = $companyAndOtherStaticImages['sequifi_logo_with_name'];
    $systemLoginLink = env('FRONTEND_BASE_URL');
@endphp

<div style="background-color: #f2f2f2;">
    <div class="" style=" height: auto; max-width: 650px; margin: 0px auto;">
        <div class="aHl"></div>
        <div tabindex="-1"></div>
        <div class="ii gt">
            <div class="a3s aiL">
                <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                    <tr>
                        <td>
                            <div align="center" style="padding: 15px; align-items: center;">
                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper"
                                    style="background-color: #fff; border-radius: 5px;">
                                    <tr>
                                        <td>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                <tr>
                                                    <td bgcolor="#FFFFFF" align="left">
                                                        <table border="0" cellpadding="0" cellspacing="0"
                                                            style="width: 100%; height: 100%">
                                                            <tr>
                                                                <td>
                                                                    <div style="text-align: center;">
                                                                        <img src="{{ $logo }}" alt=""
                                                                            style="width: 120px; height: 120px; margin: 0px auto;">
                                                                    </div>

                                                                    <h2
                                                                        style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; text-align: center; color: #424242; font-weight: 500;">
                                                                        Payroll Finalized!</h2>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div
                                                                        style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                        <div
                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px;">
                                                                            <p
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px;  color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">
                                                                                Dear Payroll
                                                                                Administrators,</p>
                                                                            <p
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px;  color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">
                                                                                We confirm that payroll has been calculated and finalized for all your rep's {{ $name }} for the pay period
                                                                            </p>
                                                                            <p
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 5px; color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">
                                                                                <strong
                                                                                    style="font-weight: 600; color: #424242;">
                                                                                    @if ($frequencyType == App\Models\FrequencyType::DAILY_PAY_ID)
                                                                                        {{ 'Daily Payroll' }}
                                                                                    @else
                                                                                        {{ date('m/d/Y', strtotime($startDate)) }}
                                                                                        -
                                                                                        {{ date('m/d/Y', strtotime($endDate)) }}
                                                                                    @endif
                                                                                </strong>
                                                                            </p>
                                                                            <div
                                                                                style="border-bottom: 1px solid #E2E2E2; width: 100%; height: 2px; margin-top: 20px;">
                                                                            </div>
                                                                            
                                                                            @if (isset($allUsersDetails['error']))

                                                                            <p
                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                        font-size: 14px;
                                                                        font-weight: 500;
                                                                        line-height: 24px; margin-left: 0px; margin-top: 20px;">
                                                                            The following rep's payouts have been successfully calculated and is ready to be paid out:-</p>
                                                                        {{-- <p
                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                        font-size: 14px;
                                                                        font-weight: 500;
                                                                        line-height: 24px; margin-left: 0px; margin-top: 5px;">
                                                                            <a href="{{ $systemLoginLink }}payroll/run-payroll?pay_period_from={{$startDate}}&pay_period_to={{$endDate}}&pay_frequency={{ $frequencyType }}"
                                                                                target="_blank"
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #4879FE;font-size: 14px;text-decoration: none;">
                                                                                @if ($frequencyType == App\Models\FrequencyType::DAILY_PAY_ID)
                                                                                    {{ $systemLoginLink }}payroll/run-payroll?Daily-Payroll={{$startDate}}_to_{{$endDate}}&pay_frequency={{ $frequencyType }}
                                                                                @else
                                                                                    {{ $systemLoginLink }}payroll/run-payroll?pay_period_from={{$startDate}}&pay_period_to={{$endDate}}&pay_frequency={{ $frequencyType }}
                                                                                @endif
                                                                            </a>
                                                                        </p> --}}
                                                                        <div
                                                                            style="border-bottom: 1px solid #E2E2E2; width: 100%; height: 2px; margin-top: 20px;margin-bottom: 20px;">
                                                                        </div>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 5px; color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">
                                                                                    Any issues encountered during payroll processing are listed below: - </p>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            @if (isset($allUsersDetails['error']))
                                                                <tr>
                                                                    <td>
                                                                        <div
                                                                            style="margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 0px 40px; ">
                                                                            <div
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px;">
                                                                                <table cellspacing="0" cellpadding="0"
                                                                                    style="margin-top: 0px;">
                                                                                    @foreach ($allUsersDetails['error'] as $detail)
                                                                                        <tr>
                                                                                            <td
                                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                        font-size: 14px;
                                                                                        font-weight: 500;
                                                                                        line-height: 24px; min-width: 160px;padding: 5px 0px;">
                                                                                                <span
                                                                                                    style="background-color: #F33; width: 10px; height: 10px; display: inline-block; border-radius: 25px; margin-right: 5px;"></span>
                                                                                                {{ $detail['name'] }}

                                                                                            </td>
                                                                                            <td
                                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #212121; font-size: 14px; font-weight: 700; line-height: 24px;padding: 5px 0px;">
                                                                                                <table cellspacing="0"
                                                                                                    cellpadding="0"
                                                                                                    style="margin-top: 0px;">
                                                                                                    <tr>
                                                                                                        <td
                                                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #212121;
                                                                                                        font-size: 14px;
                                                                                                        font-weight: 700;
                                                                                                        line-height: 24px;padding: 5px 0px;">
                                                                                                            ${{ $detail['net_pay'] }}
                                                                                                        </td>
                                                                                                        <td
                                                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #212121;
                                                                                                        font-size: 14px;
                                                                                                        font-weight: 700;
                                                                                                        line-height: 24px;padding: 5px 0px; padding-left:15px">
                                                                                                            {{ $detail['remark'] }}
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                </table>
                                                                                            </td>
                                                                                        </tr>
                                                                                    @endforeach
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                            <tr>
                                                                <td>
                                                                    <div style="padding: 5px 40px;">
                                                                        <div style="margin-top: 3px;">
                                                                            <div style="margin-top: 3px;">
                                                                                @if (isset($allUsersDetails['error']))
                                                                                    <p
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color:#616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px;">
                                                                                        To resolve these issues, please log in to Sequifi, review the failure details, and take the necessary action. Once resolved, reattempt processing the payroll. If no errors are reported, you may proceed with executing the payroll.
                                                                                    </p>
                                                                                @endif
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                font-size: 14px;
                                                                                font-weight: 600;
                                                                                line-height: 24px; margin-left: 0px;">
                                                                                    </p>
                                                                                <table cellspacing="0" cellpadding="0"
                                                                                    style="margin-top: 10px;">
                                                                                    @if (isset($allUsersDetails['success']))
                                                                                        @foreach ($allUsersDetails['success'] as $detail)
                                                                                            <tr>
                                                                                                <td
                                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                        font-size: 14px;
                                                                                        font-weight: 500;
                                                                                        line-height: 24px; min-width: 160px;padding: 5px 0px;">
                                                                                                    {{ $detail['name'] }}
                                                                                                </td>
                                                                                                <td
                                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #212121;
                                                                                        font-size: 14px;
                                                                                        font-weight: 700;
                                                                                        line-height: 24px;padding: 5px 0px;">
                                                                                                    ${{ $detail['net_pay'] }}
                                                                                                </td>
                                                                                            </tr>
                                                                                        @endforeach
                                                                                    @endif
                                                                                </table>

                                                                                <div
                                                                                    style="border-bottom: 1px solid #E2E2E2; width: 100%; height: 2px; margin-top: 20px;margin-bottom: 20px;">
                                                                                </div>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                font-size: 14px;
                                                                                font-weight: 600;
                                                                                line-height: 24px; margin-left: 0px; margin-top: 20px;">
                                                                                    To view your finalized payroll and proceed with the payout, please click the link below: -
                                                                                </p>

                                                                                <div style="margin: 30px 0px;">
                                                                                    <a href="{{ $systemLoginLink }}payroll/run-payroll?pay_period_from={{$startDate}}&pay_period_to={{$endDate}}&pay_frequency={{ $frequencyType }}"
                                                                                        target="_blank"
                                                                                        style="text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';border-radius: 6px;
                                                                                    background: #6078EC;width: 230px;
                                                                                    height: 46px;
                                                                                    padding: 12px 50px; align-items: center;color: #FFF;
                                                                                    text-align: center;
                                                                                    font-size: 16px;
                                                                                    font-weight: 500;
                                                                                    line-height: normal;border: none">View
                                                                                        Report</a>
                                                                                </div>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                font-size: 14px;
                                                                                font-weight: 600;
                                                                                line-height: 24px; margin-left: 0px; margin-top: 20px;">
                                                                                    Or Click the link below-
                                                                                </p>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px; margin-top: 5px;">
                                                                                    <a href="{{ $systemLoginLink }}payroll/run-payroll?pay_period_from={{$startDate}}&pay_period_to={{$endDate}}&pay_frequency={{ $frequencyType }}"
                                                                                        target="_blank"
                                                                                        style="font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #4879FE;font-size: 14px;text-decoration: none;">{{ $systemLoginLink }}payroll/run-payroll?pay_period_from={{$startDate}}&pay_period_to={{$endDate}}&pay_frequency={{ $frequencyType }}</a>
                                                                                </p>

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px; margin-top: 20px;">
                                                                                    Best regards,</p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px; margin-top: 5px;">
                                                                                    The <strong
                                                                                        style="color: #424242;font-size: 14px;
                                                                                    font-weight: 600;">{{ $name }}
                                                                                    </strong>Team</p>

                                                                                <div
                                                                                    style="border-bottom: 1px solid #E2E2E2; width: 100%; height: 2px; margin-top: 80px;">
                                                                                </div>
                                                                                <div style="padding-top: 10px; text-align: center;">
                                                                                    <p
                                                                                        style="margin-bottom: 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; text-align: center;color: #757575;
                                                                                        font-size: 12px;
                                                                                        font-weight: 500;
                                                                                        line-height: 18px;">
                                                                                        {{ $footerContent }}
                                                                                    </p>
                                                                                    <p
                                                                                        style="font-weight: 500;font-size: 12px;line-height: 20px;color: #9E9E9E; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">
                                                                                        © Copyright {{ date('Y') }} | <a
                                                                                            href="{{ $companyWebsite }}"
                                                                                            target="_blank"
                                                                                            style="font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #4879FE;font-size: 12px;text-decoration: none;">
                                                                                            {{ $companyWebsite }}
                                                                                        </a>| All rights reserved</p>
                                                                                    <table role="presentation"
                                                                                        cellspacing="0" cellpadding="0"
                                                                                        style="margin: auto; margin-bottom: 10px;">
                                                                                        <tr>
                                                                                            <td style="text-align: center;">
                                                                                                <p
                                                                                                    style="font-weight: 500; color: #9E9E9E;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-right: 10px;font-size: 12px;">
                                                                                                    Powered by
                                                                                                </p>
                                                                                            </td>
                                                                                            <td style="text-align: center;">
                                                                                                <img src="{{ $sequifiLogoWithName }}" alt="Sequifi" style="width: 100px;">
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
