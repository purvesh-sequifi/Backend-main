@extends('layout.mail_layout')

@section('title')
    Email Sending Status
@endsection

{{-- Top head in mail --}}
@section('top_head')
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    color: #767373; font-size: 30px;font-weight: 500; text-align: center;">
                    Email Sending Status
                </p>
            </div>
        </td>
    </tr>
@endsection

{{-- Icon section --}}
@section('icon_section')
    <tr>
        <td align="center">
            <table border="0" cellspacing="0" cellpadding="0">
                <tr>
                    @for ($i = 0; $i < 4; $i++)
                        <td align="center">
                            <table width="5" border="0" align="center" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="5" height="5" bgcolor="#a8803a" style="border-radius:10px;"></td>
                                </tr>
                            </table>
                        </td>
                        @if ($i < 3)
                            <td width="15"></td>
                        @endif
                    @endfor
                </tr>
            </table>
        </td>
    </tr>
@endsection

{{-- Main content --}}
@section('main_content')
    <table border="1" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
        <thead>
            <tr>
                <th>#</th>
                <th>ID</th>
                <th>User Name</th>
                <th>Position Name</th>
                <th>Message</th>
                <th>Status</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            @foreach($response_array as $index => $response)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="text-align: center;">{{ $response['id'] }}</td>
                    <td style="text-align: center;">{{ $response['user_name'] }}</td>
                    <td style="text-align: center;">{{ $response['position_name'] }}</td>
                    <td style="text-align: center;">{{ $response['message'] }}</td>
                    <td style="text-align: center; color: {{ $response['status'] ? 'green' : 'red' }};">
                        {{ $response['status'] ? 'Success' : 'Failed' }}
                    </td>
                    <td style="text-align: center;">{{ @$response['error'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="text-align: center; padding-top: 20px;">
        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    margin-bottom:20px; margin-top:0px;color: #767373; font-size: 16px;font-weight: 400; text-align: center;">
            If you received this message by mistake, ignore this email.
        </p>
    </div>
    <div style="">
        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    margin-bottom:24px; margin-top:0px;color: #767373; font-size: 18px;font-weight: 500;margin-left: 75px;">
            Best regards,
        </p>
        <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    margin-bottom:10px; margin-top:10px;color: #767373; font-size: 16px;font-weight: 400; margin-left: 75px;">
            {{ env('DOMAIN_NAME') }}
        </p>
    </div>
@endsection
