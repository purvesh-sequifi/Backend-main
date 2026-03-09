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
                                                        Dear {{$user->first_name}} {{$user->last_name}},,
                                                    </p>
                                                    <p
                                                        style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 10px;line-height: 24px;">
                                                        We're pleased to inform you that your paystub for the pay period
                                                        <strong>{{$start_date}}- {{$end_date}}</strong> is now
                                                        available for download.
                                                    </p>
                                                    <p
                                                        style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 15px;line-height: 24px;">
                                                        To access your paystub, simply click on link below:
                                                    </p>

                                                    <div style="text-align: center">
                                                        <a href="{{url('/')}}{{$pdfPath}}" download="_blank" target="_blank"
                                                            style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; background-color: #6078ec; color: #fff; font-size: 16px;font-weight: 500; text-decoration: none; padding: 12px 25px; border-radius: 8px; display: inline-block; margin-top: 25px;">Download Paystub</a>
                                                    </div>

                                                    <p
                                                        style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 15px;line-height: 24px; margin-bottom: 5px;">
                                                        Or click the link below-
                                                    </p>

                                                    <p
                                                        style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 0px;line-height: 24px;">
                                                        <a href="#" target="_blank"
                                                            style="color: #6078ec; text-decoration: none;font-weight: 500;">https://na4.paystub.sequifi/signing/emailsvl-92ed75efb1a94705aebc5556994e83f70f9e6088fa9d4baaa56bf7488ff08eca</a>
                                                    </p>

                                                    <div style="margin-top: 30px;">
                                                        <p
                                                            style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 15px;line-height: 24px; margin-bottom: 0px;">
                                                            Best regards,
                                                        </p>
                                                        <p
                                                            style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 0px;line-height: 24px;">
                                                            The Flex Power Team
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