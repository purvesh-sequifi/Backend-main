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
    $startDate = $start_date ?? $startDate;
    $endDate = $end_date ?? $endDate;
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
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td bgcolor="#FFFFFF" align="left">
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
                                                                        Paystub Available</h2>
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
                                                                                    style="font-weight: 600; color: #424242;">{{ $user->first_name }}
                                                                                    {{ $user->last_name }}</strong>,</p>
                                                                            <p
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 10px; color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">
                                                                                We're pleased to inform you that your
                                                                                paystub for
                                                                                @if ($startDate == $endDate && $data['pay_stub']['pay_frequency'] != App\Models\FrequencyType::DAILY_PAY_ID)
                                                                                    one time payment
                                                                                @elseif($data['pay_stub']['pay_frequency'] == App\Models\FrequencyType::DAILY_PAY_ID)
                                                                                    Daily Payroll
                                                                                @else
                                                                                    <strong
                                                                                        style="font-weight: 600; color: #424242;">
                                                                                        {{ date('m/d/Y', strtotime($startDate)) }}
                                                                                        -
                                                                                        {{ date('m/d/Y', strtotime($endDate)) }}
                                                                                    </strong>
                                                                                @endif
                                                                                is now available to download.
                                                                            </p>

                                                                            <p
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 13px; color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">
                                                                                To access your paystub, simply click on
                                                                                the link below: </p>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div style="margin: 15px 0px; text-align: center;">
                                                                        <a href="{{ $s3filePath }}" target="_blank"
                                                                            style="text-decoration:none; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';border-radius: 6px;background: #6078EC;width: 230px; height: 46px; padding: 12px 50px; align-items: center;color: #FFF; text-align: center; font-size: 16px; font-weight: 500; line-height: normal;border: none">Download
                                                                            Paystub</a>
                                                                    </div>

                                                                    <div style="padding: 5px 40px;">
                                                                        <p
                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161; font-size: 14px; font-weight: 600; line-height: 24px; margin-left: 0px; margin-top: 20px;">
                                                                            Or Click the link below- </p>
                                                                        <p
                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161; font-size: 14px; font-weight: 500; line-height: 24px; margin-left: 0px; margin-top: 5px;">
                                                                            <a href="{{ $s3filePath }}"
                                                                                target="_blank"
                                                                                style="font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #4879FE;font-size: 14px;text-decoration: none;">{{ $s3filePath }}</a>
                                                                        </p>

                                                                    </div>


                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div style="padding: 5px 40px;">
                                                                        <div style="margin-top: 3px;">
                                                                            <div style="margin-top: 20px;">
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
                                                                                <div
                                                                                    style="padding-top: 10px; text-align: center;">
                                                                                    <p
                                                                                        style="margin-bottom: 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; text-align: center;color: #757575;
                                                                                        font-size: 12px;
                                                                                        font-weight: 500;
                                                                                        line-height: 18px;">
                                                                                        {{ $footerContent }}
                                                                                    </p>
                                                                                    <p
                                                                                        style="font-weight: 500;font-size: 12px;line-height: 20px;color: #9E9E9E; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">
                                                                                        © Copyright {{ date('Y') }}
                                                                                        | <a href="{{ $companyWebsite }}"
                                                                                            target="_blank"
                                                                                            style="font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #4879FE;font-size: 12px;text-decoration: none;">
                                                                                            {{ $companyWebsite }}
                                                                                        </a>| All rights reserved</p>
                                                                                    <table role="presentation"
                                                                                        cellspacing="0" cellpadding="0"
                                                                                        style="margin: auto; margin-bottom: 10px;">
                                                                                        <tr>
                                                                                            <td
                                                                                                style="text-align: center;">
                                                                                                <p
                                                                                                    style="font-weight: 500; color: #9E9E9E;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-right: 10px;font-size: 12px;">
                                                                                                    Powered by
                                                                                                </p>
                                                                                            </td>
                                                                                            <td
                                                                                                style="text-align: center;">
                                                                                                <img src="{{ $sequifiLogoWithName }}"
                                                                                                    alt="Sequifi"
                                                                                                    style="width: 100px;">
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
