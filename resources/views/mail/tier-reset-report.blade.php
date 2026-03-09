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
                                                                        Tier Reset Report!!</h2>
                                                                </td>
                                                            </tr>

                                                            @foreach ($tiers as $tier)
                                                                <tr>
                                                                    <td>
                                                                        <div
                                                                            style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                            <div
                                                                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px;">
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 20px; color: #616161;
                                                                                font-weight: 500; line-height: 24px;">
                                                                                    Tier Name:
                                                                                    <strong>{{ $tier['tier_name'] }}</strong>
                                                                                </p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 0px; color: #616161;
                                                                                font-weight: 500; line-height: 24px;">
                                                                                    Tier Type:
                                                                                    <strong>{{ $tier['tier_type'] }}</strong>
                                                                                </p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 0px; color: #616161;
                                                                                font-weight: 500; line-height: 24px;">
                                                                                    Start Date:
                                                                                    <strong>{{ $tier['start_date'] }}</strong>
                                                                                </p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 0px; color: #616161;
                                                                                font-weight: 500; line-height: 24px;">
                                                                                    End Date:
                                                                                    <strong>{{ $tier['end_date'] }}</strong>
                                                                                </p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 0px; color: #616161;
                                                                                font-weight: 500; line-height: 24px;">
                                                                                    Reset Date:
                                                                                    <strong>{{ Illuminate\Support\Carbon::now()->format('Y-m-d') }}</strong>
                                                                                </p>
                                                                                <p
                                                                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; margin: 0px; margin-top: 0px; color: #616161;
                                                                                font-weight: 500; line-height: 24px;">
                                                                                    Next Reset Date:
                                                                                    <strong>{{ $tier['next_reset_date'] }}</strong>
                                                                                </p>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>

                                                                <tr>
                                                                    <td>
                                                                        <div
                                                                            style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';padding: 20px 40px; ">
                                                                            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; font-size: 14px; width: 100%; margin: 0px auto;"
                                                                                class="table-mainParent">
                                                                                @if ($tier['is_error'])
                                                                                    <table cellspacing="0"
                                                                                        cellpadding="0"
                                                                                        style="margin-top: 20px; width: 100%;border-radius: 10px;border: 1px solid #e0e0e0;">
                                                                                        <thead
                                                                                            style="background-color: #e0e0e0; border-top-left-radius: 10px;">
                                                                                            <tr>
                                                                                                <th
                                                                                                    style="border-top-left-radius: 10px;padding: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    Message</th>
                                                                                                <th
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    File</th>
                                                                                                <th
                                                                                                    style="border-top-right-radius: 10px;padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    Line No.</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            <tr
                                                                                                style="background-color: rgba(255, 51, 51, 0.1)">
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                    {{ $tier['message'] }}
                                                                                                </td>
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                    {{ $tier['file'] }}
                                                                                                </td>
                                                                                                <td
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                    {{ $tier['line'] }}
                                                                                                </td>
                                                                                            </tr>
                                                                                        </tbody>
                                                                                    </table>
                                                                                @else
                                                                                    <table cellspacing="0"
                                                                                        cellpadding="0"
                                                                                        style="margin-top: 20px; width: 100%;border-radius: 10px;border: 1px solid #e0e0e0;">
                                                                                        <thead
                                                                                            style="background-color: #e0e0e0; border-top-left-radius: 10px;">
                                                                                            <tr>
                                                                                                <th
                                                                                                    style="border-top-left-radius: 10px;padding: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                    font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    User ID</th>
                                                                                                <th
                                                                                                    style="padding: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    User Name</th>
                                                                                                <th
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    Type</th>
                                                                                                <th
                                                                                                    style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                        font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    Sub Tier</th>
                                                                                                <th
                                                                                                    style="border-top-right-radius: 10px;padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #000;font-weight: 600;line-height: 24px;">
                                                                                                    Current Level</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            @if (isset($tier['users']) && sizeOf($tier['users']) != 0)
                                                                                                @foreach ($tier['users'] as $user)
                                                                                                    <tr
                                                                                                        style="background-color: rgb(215, 249, 239)">
                                                                                                        <td
                                                                                                            style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                            {{ $user['user_id'] }}
                                                                                                        </td>
                                                                                                        <td
                                                                                                            style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                            {{ $user['user_name'] }}
                                                                                                        </td>
                                                                                                        <td
                                                                                                            style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                            {{ $user['tier']['type'] }}
                                                                                                        </td>
                                                                                                        <td
                                                                                                            style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                            {{ $user['tier']['sub_type'] }}
                                                                                                        </td>
                                                                                                        <td
                                                                                                            style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;">
                                                                                                            {{ $user['tier']['current_value'] }}
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                @endforeach
                                                                                            @else
                                                                                                <tr>
                                                                                                    <td
                                                                                                        style="padding: 8px;font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol';
                                                                                            font-size: 14px;margin: 0px;color: #616161;font-weight: 400;line-height: 24px; text-align: center;" colspan="5">
                                                                                                        No User Found!!
                                                                                                    </td>

                                                                                                </tr>
                                                                                            @endif
                                                                                        </tbody>
                                                                                    </table>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach



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
