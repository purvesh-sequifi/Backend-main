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
                                                                                Dear <strong
                                                                                    style="font-weight: 600; color: #424242;">{{ $newData['employee']['first_name'] }}
                                                                                    {{ $newData['employee']['last_name'] }}</strong>,
                                                                            </p>
                                                                            <p
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 10px; color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">
                                                                                Good news! Your payroll
                                                                                for the pay period
                                                                                <strong
                                                                                    style="font-weight: 600; color: #424242;">
                                                                                    @if ($newData['pay_stub']['pay_frequency'] == App\Models\FrequencyType::DAILY_PAY_ID)
                                                                                        {{ 'Daily Payroll' }}
                                                                                    @else
                                                                                        {{ date('m/d/Y', strtotime($startDate)) }}
                                                                                        -
                                                                                        {{ date('m/d/Y', strtotime($endDate)) }}
                                                                                    @endif
                                                                                </strong> has been
                                                                                finalized. The payment process will
                                                                                commence shortly, and you will be able
                                                                                to download your paystub once this is
                                                                                completed.
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div
                                                                        style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                        <div
                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px;">
                                                                            <h4
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 15px; margin: 0px;  color: #424242;
                                                                            font-weight: 600;
                                                                            line-height: 24px;">
                                                                                Earnings Breakdown
                                                                                @if ($newData['pay_stub']['pay_frequency'] == App\Models\FrequencyType::DAILY_PAY_ID)
                                                                                    {{ 'Daily Payroll' }}
                                                                                @else
                                                                                    ({{ date('m/d/Y', strtotime($startDate)) }}
                                                                                    -
                                                                                    {{ date('m/d/Y', strtotime($endDate)) }})
                                                                                @endif
                                                                            </h4>

                                                                            <table cellspacing="0" cellpadding="0"
                                                                                style="margin-top: 20px;">
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Commissions:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ $newData['earnings']['commission']['period_total'] }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Overrides:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ $newData['earnings']['overrides']['period_total'] }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Adjustments:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ $newData['miscellaneous']['adjustment']['period_total'] }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Reimbursements:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ $newData['miscellaneous']['reimbursement']['period_total'] }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Deductions:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #F33;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        $({{ $newData['deduction']['standard_deduction']['period_total'] }})
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Reconciliations:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ $newData['earnings']['reconciliation']['period_total'] }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Wages:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ isset($newData['earnings']['wages']['period_total']) ? $newData['earnings']['wages']['period_total'] : '0.00' }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Total additional values:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #424242;
                                                                                    font-size: 14px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ isset($newData['custom_payment']) ? $newData['custom_payment'] : '0.00' }}
                                                                                    </td>
                                                                                </tr>
                                                                                <tr>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #212121;
                                                                                    font-size: 16px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; min-width: 150px;padding: 5px 0px;">
                                                                                        Net Pay:
                                                                                    </td>
                                                                                    <td
                                                                                        style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #212121;
                                                                                    font-size: 16px;
                                                                                    font-weight: 600;
                                                                                    line-height: 24px;padding: 5px 0px;">
                                                                                        ${{ isset($newData['pay_stub']['net_pay']) ? $newData['pay_stub']['net_pay'] : '0.00' }}
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div style="padding: 5px 40px;">
                                                                        <div style="margin-top: 3px;">
                                                                            <div style="margin-top: 3px;">

                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color:#616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px;">
                                                                                    Should you have any questions, feel
                                                                                    free to reach out to our payroll
                                                                                    department.</p>
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
