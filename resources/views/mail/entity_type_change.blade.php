@extends('layout.mail_layout')

@section('title') 
    Entity Type Change
@endsection

{{-- Top head in mail --}}
@section('top_head') 
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    Entity Type Change
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
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                    font-size: 16px;font-weight: 300; text-align: center;">The Entity Type for the following user has been updated in Sequifi. Since there is no programmatic integration to automatically update this in Everee, manual action is required.
                    </p>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="padding: 20px 40px;">
                    <div style="background-color: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';">
                        
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Sequifi Sub domain:</strong> <a href="https://{{ env('DOMAIN_NAME') }}.sequifi.com" target="_blank" style="color: #1f5582; text-decoration: underline;">{{ env('DOMAIN_NAME') }}.sequifi.com</a>
                        </p>
                        
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>User Name:</strong> {{ isset($data->first_name)?$data->first_name:'' }} {{ isset($data->last_name)?$data->last_name:'' }}
                        </p>
                        
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>User ID:</strong> {{ isset($data->id)?$data->id:'' }}
                        </p>

                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Entity Type:</strong> {{ isset($newData['entity_type'])?ucfirst($newData['entity_type']):'' }}
                        </p>
                        
                        @if($newData['entity_type'] == 'individual')
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Social Security Number:</strong> {{ isset($newData['social_security_no'])?$newData['social_security_no']:'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Old Entity Type:</strong> {{ isset($data->entity_type)?ucfirst($data->entity_type):'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Old Business Name:</strong> {{ isset($data->business_name)?ucfirst($data->business_name):'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Old Business Type:</strong> {{ isset($data->business_type)?ucfirst($data->business_type):'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Old Business EIN:</strong> {{ isset($data->business_ein)?ucfirst($data->business_ein):'' }}
                        </p>
                        @endif
                        
                        @if($newData['entity_type'] == 'business')
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Business Name:</strong> {{ isset($newData['business_name'])?$newData['business_name']:'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Business Type:</strong> {{ isset($newData['business_type'])?$newData['business_type']:'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>EIN:</strong> {{ isset($newData['business_ein'])?$newData['business_ein']:'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Old Entity Type:</strong> {{ isset($data->entity_type)?ucfirst($data->entity_type):'' }}
                        </p>
                        <p style="margin: 10px 0; color: #767373; font-size: 16px; font-weight: 400;">
                            <strong>Old Social Security Number:</strong> {{ isset($data->social_sequrity_no)?ucfirst($data->social_sequrity_no):'' }}
                        </p>
                        @endif
                        
                    </div>
                </div>
                <div style="padding: 10px 40px;">
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                    font-size: 16px;font-weight: 300;"><b>Action Required:</b><br>Please log in to Everee's frontend dashboard and update this user's Entity Type accordingly.
                    </p>
                </div>
                <div style="padding: 10px 40px;">
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                    font-size: 16px;font-weight: 300;">Thank you for your prompt attention to this update. 
                    </p>
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
