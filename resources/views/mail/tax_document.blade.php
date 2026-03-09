@extends('layout.mail_layout')

@section('title')
    Finalize Payroll
@endsection

{{-- Top head in mail --}}
@section('top_head')
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                   Paystub Available!
                </p>
            </div>
        </td>
    </tr>
@endsection

{{-- Add icon --}}

@section('icon_section')
    <tr>
        <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                    <td align="center">
                    <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                    <td width="15"></td>
                    <td align="center">
                        <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                    <td width="15"></td>
                    <td align="center">
                        <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                    <td width="15"></td>
                    <td align="center">
                        <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                        </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
@endsection

{{-- main_content --}}
@section('main_content')
    <?php
        $CompanyProfile = DB::table('company_profiles')->first();
        $company_name = $CompanyProfile->name;
    ?>

            <div style="padding: 10px 40px;">
                    <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                        <tr>
                            <td>
                                <div align="center" style="padding: 30px; align-items: center;">
                                    <table cellpadding="0" cellspacing="0" class="wrapper"
                                        style="background-color: #fff; border-radius: 5px; margin-top: 5%;">

                                        <tr>
                                            <td>
                                                <div
                                                    style="margin-top: 30px; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;padding: 0px 40px; ">

                                                    <p
                                                        style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 10px;">
                                                        Dear {{$data['name']}},
                                                    </p>
                                                    <p
                                                        style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 10px;line-height: 24px;">
                                                        We are pleased to inform you your tax documents are now available for you.  Attached to this email you will find the pdf version of your documents.  You can also find these documents easily in your Sequifi profile by logging into <a href="{{ env('LOGIN_LINK') }}">{{ env('DOMAIN_NAME') }}</a> and going to your user profile and selecting the "Tax Info" section.
                                                    </p>

                                                    <div style="margin-top: 30px;">
                                                        <p
                                                            style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 15px;line-height: 24px; margin-bottom: 0px;">
                                                            Best regards,
                                                        </p>
                                                        <p
                                                            style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 0px;line-height: 24px;">
                                                            {{$company_name}}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                </table>
            </div>

@endsection