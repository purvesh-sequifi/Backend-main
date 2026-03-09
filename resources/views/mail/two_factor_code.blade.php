<?php 
$CompanyProfile = DB::table('company_profiles')->first();
$company_name = $CompanyProfile->name;
$address = $CompanyProfile->address;
$company_website = $CompanyProfile->company_website;
$phone_number = $CompanyProfile->phone_number;
$mailing_city = $CompanyProfile->mailing_city;
$mailing_state = $CompanyProfile->mailing_state;
$mailing_zip = $CompanyProfile->mailing_zip;
$country = $CompanyProfile->country;
$company_and_other_static_images = \App\Models\SequiDocsEmailSettings::company_and_other_static_images($CompanyProfile);
$header_image = $company_and_other_static_images['header_image'];
$Company_Logo = $company_and_other_static_images['Company_Logo'];
$sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
$letter_box = $company_and_other_static_images['letter_box'];
$sequifiLogo = $company_and_other_static_images['sequifiLogo'];
$System_Login_Link = env('LOGIN_LINK');

$business_name = $CompanyProfile->business_name;
$business_phone = $CompanyProfile->business_phone;
$company_email = $CompanyProfile->company_email;
$business_address = $CompanyProfile->business_address;

$Footer_Content = "$business_name |  + $business_phone  |  $company_email | $business_address" ;

?>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        p {
            margin: .35em;
        }

        body {
            margin: 0;
        }
    </style> 
</head>
<div style="background-color: #f2f2f2;">
    <div class="" style=" height: auto; max-width: 650px; margin: 0px auto;">
        <div class="aHl"></div>
        <div tabindex="-1"></div>
        <div class="ii gt">
            <div class="a3s aiL ">
                <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                    <tr>
                        <td>
                            <div align="center" style="background-color: #fff; padding: 15px; align-items: center;">
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
                                                                        <img src="{{$Company_Logo}}" alt=""
                                                                            style="width: 120px; height: 120px; margin: 0px auto;">
                                                                    </div>
                                                                    <h2
                                                                        style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; text-align: center; color: #424242; font-weight: 500;">
                                                                        Two Factor Authentication
                                                                    </h2>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div
                                                                        style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                        <div
                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px;">
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px;  color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">Dear <strong
                                                                                    style="font-weight: 600; color: #424242;">{{$user->first_name}} {{$user->last_name}}</strong>,</p>
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 10px; color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;"> To complete your sign-in process, please use the following 6-digit authentication code: <strong>{{ $code }}</strong></p>
                                                                                    
                                                                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 13px; color: #616161;
                                                                            font-weight: 500;
                                                                            line-height: 24px;">If you did not request this code, please disregard this email or contact our support team. </p>

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
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px; margin-top: 20px;">Best regards,</p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #616161;
                                                                                    font-size: 14px;
                                                                                    font-weight: 500;
                                                                                    line-height: 24px; margin-left: 0px; margin-top: 5px;">The <strong style="color: #424242;font-size: 14px;
                                                                                    font-weight: 600;">{{$company_name}} </strong>Team</p>
                                                                                <div
                                                                                    style="border-bottom: 1px solid #E2E2E2; width: 100%; height: 2px; margin-top: 80px;">
                                                                                </div>
                                                                                <div style="padding-top: 10px;">
                                                                                    <p
                                                                                        style="margin-bottom: 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; text-align: center;color: #757575;
                                                                                        font-size: 12px;
                                                                                        font-weight: 500;
                                                                                        line-height: 18px;">
                                                                                       {{$Footer_Content}}
                                                                                    </p>
                                                                                    <p
                                                                                        style="font-weight: 500;font-size: 12px;line-height: 20px;color: #9E9E9E; margin-bottom: 20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; text-align: center;">
                                                                                        © Copyright 2023 | <a
                                                                                            href=" {{$company_website}}"
                                                                                            target="_blank"
                                                                                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom: 0px;color: #4879FE;font-size: 12px;text-decoration: none;">
                                                                                            {{$company_website}} 
                                                                                        </a>| All rights reserved</p>
                                                                                    <table role="presentation"
                                                                                        cellspacing="0" cellpadding="0"
                                                                                        style="margin: auto; margin-bottom: 10px;">
                                                                                        <tr>
                                                                                            <td style="text-align: center;">
                                                                                                <p
                                                                                                    style="font-weight: 500; color: #9E9E9E;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-right: 10px;font-size: 12px;">
                                                                                                    Powered by
                                                                                                </p>
                                                                                            </td>
                                                                                            <td
                                                                                                style="text-align: center;">
                                                                                                <img src="{{$sequifi_logo_with_name}}"
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