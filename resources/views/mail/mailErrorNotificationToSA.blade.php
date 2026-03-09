@extends('layout.mail_layout')

@section('title') 
    Mail Error Notification
@endsection

{{-- Top head in mail --}}
@section('top_head') 
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" crossorigin="anonymous">
<tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 26px;font-weight: 500; text-align: center;">
                    @if($error_type == 'smtp_error')
                        Action Required - SMTP Issue Detected
                    @else
                        Email Delivery Failure Due to Domain Settings Restrictions 
                    @endif
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
        $System_Login_Link = url('/');
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
                                @if($error_type == 'smtp_error')
                                    We've detected a problem with our email delivery system. A recent attempt to send an email was unsuccessful due to an SMTP issue.
                                    <br><br>
                                    <b>Immediate Action Required: </b>Please check the SMTP settings to ensure they are accurate and update them if necessary.
                                    <br><br>
                                    Steps to take:
                                    <ul>
                                        <li>Confirm the SMTP server details are currect</li>
                                        <li>Ensure the server limits have not been exceeded</li>
                                        <li>Verify that the SMTP credentials are correct</li>
                                    </ul>
                                    If you need assistance, please refer to our general SMTP settings guide or reach out to our technical support team.
                                    <br><br>
                                    We rely on your prompt response to avoid any further communication delays.
                                    <br><br>
                                    <a href="{{$System_Login_Link}}" style="background-color: #007bff; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-left:40%">Go To App</a>
                                    <br><br>
                                    Or Click the link below- <br>
                                    <a href="{{$System_Login_Link}}" target="_blank">{{$System_Login_Link}}</a>
                                @else
                                    @php 
                                        $email = '';
                                        if(is_array($errorDetails['recipient_email'])){
                                            if(!empty($errorDetails['recipient_email'])){
                                                $email = implode(',', $errorDetails['recipient_email']);
                                            }
                                        }else{
                                            $email = $errorDetails['recipient_email'];
                                        }
                                    @endphp
                                    We wanted to bring to your attention that an attempt to send an email to {{ $email }} was unsuccessful. The email domain of the intended recipient is currently disabled in our email settings, which has prevented the delivery of the message.
                                    <br><br>
                                    Please review the domain settings to ensure that communications can be sent to necessary parties without interruption. If this domain should be authorized for email communication, please update the settings accordingly.
                                    <br><br>
                                    Thank you for your prompt attention to this matter.
                                    <br><br>
                                    <a href="{{$System_Login_Link}}" style="background-color: #007bff; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; display: inline-block; margin-left:40%">Go To App</a>
                                    <br><br>
                                    Or Click the link below- <br>
                                    <a href="{{$System_Login_Link}}" target="_blank">{{$System_Login_Link}}</a>
                                @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                  </table>
                </div>
                <div style="padding: 40px 0px;">
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:24px; margin-top:0px;color: #767373;
                    font-size: 18px;font-weight: 500;margin-left: 42px;">Best regards,
                    </p>
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:10px; margin-top:10px;color: #767373;
                    font-size: 16px;font-weight: 400; margin-left: 42px;"> {{$company_name}}
                    </p>
                </div>           
            </td>
        </tr>
    </table>
@endsection

