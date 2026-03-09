@extends('layout.mail_layout')

@section('title') 
    Request Approval
@endsection

{{-- Top head in mail --}}
@section('top_head') 
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    {{ ucfirst($mailData['type']) }}
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
    <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
        <tr>
            <td>
                <div style="padding: 10px 40px;">
                  <table border="0" cellspacing="0" cellpadding="0">
                       <tr>
                            <td class="col_wid">
                                <div class="text" style="style="padding:0 2.5em;text-align:center;margin: 0px 0px 0px 126px;">
                                    <h2 style="color: 000;font-size: 20px;margin-bottom: auto;font-weight: 400;line-height: 1.4;"2>Request ID:{{ $mailData['id'] }}</h2>
                                    <p style="font-weight: 500;
                                   font-size: 15.8594px;
                                   line-height: 18px;
                                   color: #9E9E9E;
                                   margin-right: 12px;">{{ $mailData['type'] }}:{{ $mailData['comment'] }}</p> 
                                   <p style="font-weight: 500;
                                   font-size: 15.8594px;
                                   line-height: 18px;
                                   color: #9E9E9E;
                                   margin-right: 12px;">{{ $mailData['type'] }} by :{{ $mailData['name'] }}</p> 
                                   
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
                    font-size: 16px;font-weight: 400; margin-left: 42px;">{{$company_name}}
                    </p>
                </div>           
            </td>
        </tr>
    </table>
@endsection