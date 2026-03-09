@extends('layout.mail_layout')

@section('title') 
    Onboarding
@endsection

{{-- Top head in mail --}}
@section('top_head') 
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    Welcome to Sequifi!
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
                    <table border="1" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
                          <tr>
                            <td>
                                <p
                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 500; min-width: 150px;">
                                    Name:
                                </p>
                            </td>
                             <td> 
                                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 500;">
                                    {{ isset($data->first_name) ? $data->first_name:''}} {{isset($data->last_name) ? $data->last_name:''}}
                                </p>
                            </td>
                        </tr>  
                        <tr>
                            <td>
                                <p
                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 500; min-width: 150px;">
                                    Email:
                                </p>
                            </td>
                             <td> 
                                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 500;">
                                    {{ isset($data->email) ? $data->email:''}}
                                </p>
                            </td>
                        </tr>  
                        <tr>
                            <td>
                                <p
                                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 500; min-width: 150px;">
                                    Mobile No. :
                                </p>
                            </td>
                             <td>   
                                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 500;">
                                    {{ isset($data->mobile_no) ? $data->mobile_no:''}}
                                </p>
                                </div>
                            </td>
                          </tr>
                          <tr>
                            <table>
                                <tr>
                                <td height="20" style="width: 160px; background: blue; text-align: center; border-radius: 5px; padding: 10px;">
                                    <a href="{{ env('BASE_URL')}}/api/accepted_declined_requested_change_hiring_process/{{ $data->encrypt_id}}/Requested Change" class="button" style ="color: white; text-decoration: none;">Requested Change</a>
                                </td>
                                <td height="20" style="width: 160px; background: blue; text-align: center; border-radius: 5px; padding: 10px;">
                                    <a href="{{env('BASE_URL')}}/api/accepted_declined_requested_change_hiring_process/{{ $data->encrypt_id}}/Accepted" class="button" style ="color: white; text-decoration: none;">Accepted</a>
                                </td>
                                <td height="20" style="width: 160px; background: blue; text-align: center; border-radius: 5px; padding: 10px;">
                                    <a href="{{ env('BASE_URL')}}/api/accepted_declined_requested_change_hiring_process/{{$data->encrypt_id}}/Declined" class="button" style ="color: white; text-decoration: none;">Declined</a>
                                </td>
                                </tr>
                            </table>
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