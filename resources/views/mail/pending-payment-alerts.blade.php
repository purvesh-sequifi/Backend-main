@extends('layout.mail_layout')

@section('title')
    Payment Pending for 48 Hours
@endsection

{{-- Top head in mail --}}
@section('top_head')
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 15px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    color: #767373; font-size: 30px;font-weight: 500; text-align: center;">
                    Payment Pending for 48 Hours
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
                <th>User Id</th>
                <th>User Name</th>
                <th>Worker Type</th>
                <th>Net Pay</th>
                <th>Pay Frequency Date</th>
                <th>Pay Period From</th>
                <th>Pay Period To</th>
                <th>Everee Webhook Json</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payments as $index => $payment)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td style="text-align: center;">{{ $payment['id'] }}</td>
                    <td style="text-align: center;">{{ $payment['user_id'] }}</td>
                    <td style="text-align: center;">{{ @$payment['user']['first_name'] }} {{ @$payment['user']['last_name'] }}</td>
                    <td style="text-align: center;">{{ $payment['worker_type'] }}</td>
                    <td style="text-align: center;">{{ $payment['net_pay'] }}</td>
                    <td style="text-align: center;">{{ $payment['pay_frequency_date'] }}</td>
                    <td style="text-align: center;">{{ $payment['pay_period_from'] }}</td>
                    <td style="text-align: center;">{{ $payment['pay_period_to'] }}</td>
                    <td style="text-align: center;">{{ $payment['everee_webhook_json'] }}</td>
                    <td style="text-align: center;">{{ $payment['created_at'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="text-align: center; padding-top: 20px;">
        <p
            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    margin-bottom:20px; margin-top:0px;color: #767373; font-size: 16px;font-weight: 400; text-align: center;">
            If you received this message by mistake, ignore this email.
        </p>
    </div>
    <div style="">
        <p
            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    margin-bottom:24px; margin-top:0px;color: #767373; font-size: 18px;font-weight: 500;margin-left: 75px;">
            Best regards,
        </p>
        <p
            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';
                    margin-bottom:10px; margin-top:10px;color: #767373; font-size: 16px;font-weight: 400; margin-left: 75px;">
            {{ env('DOMAIN_NAME') }}
        </p>
    </div>
@endsection
