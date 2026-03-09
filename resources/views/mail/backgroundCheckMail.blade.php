@extends('layout.mail_layout')

@section('title') 
    Background Check Mail
@endsection

{{-- Top head in mail --}}
@section('top_head') 
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" crossorigin="anonymous">
<tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 26px;font-weight: 500; text-align: center;">
                   Background Check
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
        // $backgroundCheckURL = $mailData['url'].'/'.'background-verification/'.$mailData['request_id'];
        $backgroundCheckURL = $mailData['url'].'/'.'background-verification/'.$mailData['encrypted_request_id'];
    ?>
    <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
        <tr>
            <td>
                <div style="padding: 10px 40px;">
                  <table border="0" cellspacing="0" cellpadding="0">
                       <tr>
                        <td class="col_wid">
                           <div class="main-content">
                                <p class="main-content__body" data-lead-id="main-content-body">
                                    {{$company_name}} has requested you complete a background Check for the employment with {{ $company_name }}. Click the button below to get started.
                                    <br><br>
                                    <a href="{{$backgroundCheckURL}}" style="background-color: #007bff; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-left:40%">Get Started</a>
                                    <br><br>
                                    Or Click the link below- <br>
                                    <a href="{{$backgroundCheckURL}}" target="_blank">{{$backgroundCheckURL}}</a>
                                </p>
                            </div>
                        </td>
                    </tr>
                  </table> 
                </div>
                <div style="padding: 40px 0px;">
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:24px; margin-top:0px;color: #767373;
                    font-size: 16px;font-weight: 500;margin-left: 42px;">Best regards,
                    </p>
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:10px; margin-top:10px;color: #767373;
                    font-size: 14px;font-weight: 400; margin-left: 42px;"> {{$company_name}}
                    </p>
                </div>           
            </td>
        </tr>
    </table>
@endsection

