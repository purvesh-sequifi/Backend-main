@extends('layout.mail_layout')

@section('title')
    Tiers Sync Command Failed
@endsection

{{-- Top head in mail --}}
@section('top_head')
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';color: #767373;
                    font-size: 30px;font-weight: 500; text-align: center;">
                    Tiers Sync Command Failed!!
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
    <table border="1" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
        <thead>
        <tr>
            <th>#</th>
            <th>User ID</th>
            <th>Message</th>
            <th>File</th>
            <th>Line No.</th>
        </tr>
        </thead>
        <tbody>
        @foreach($errors as $key => $error)
            <tr>
                <td>
                    <div style="padding: 10px 0px; text-align: center;">
                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 300; text-align: center;">{{ $key+1 }}</p>
                    </div>
                </td>
                <td>
                    <div style="padding: 10px 0px; text-align: center;">
                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 300; text-align: center;">{{ $error['user_id'] }}</p>
                    </div>
                </td>
                <td>
                    <div style="padding: 10px 0px; text-align: center;">
                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 300; text-align: center;">{{ $error['message'] }}</p>
                    </div>
                </td>
                <td>
                    <div style="padding: 10px 0px; text-align: center;">
                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 300; text-align: center;">{{ $error['file'] }}</p>
                    </div>
                </td>
                <td>
                    <div style="padding: 10px 0px; text-align: center;">
                        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 300; text-align: center;">{{ $error['line'] }}</p>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="text-align: center; padding-top: 20px;">
        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:20px; margin-top:0px;color: #767373;
                    font-size: 16px;font-weight: 400; text-align: center;">If you received this message by mistake,
            ignore this email.
        </p>
    </div>
    <div style="">
        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:24px; margin-top:0px;color: #767373;
                    font-size: 18px;font-weight: 500;margin-left: 75px;">Best regards,
        </p>
        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:10px; margin-top:10px;color: #767373;
                    font-size: 16px;font-weight: 400; margin-left: 75px;">{{ env('DOMAIN_NAME') }}
        </p>
    </div>
@endsection