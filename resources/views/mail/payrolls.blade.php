@extends('layout.mail_layout')

@section('title') 
    EOD Report For Overrides Updated
@endsection

{{-- Top head in mail --}}
@section('top_head') 
    <tr>
        <td bgcolor="#ffffff" align="left">
            <div style="padding: 20px 40px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:20px; margin-top:0px;color: #767373;
                font-size: 30px;font-weight: 500; text-align: center;">Solar Company LLC
                </p>
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                font-size: 16px;font-weight: 500; text-align: center;padding: 20px 20px;">1,23 New york
                        street, new york city, new york, 12358
                </p>
                 <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                font-size: 16px;font-weight: 500; text-align: center;">(502) 985-3456
                </p>
                <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:0px; margin-top:0px;color: #767373;
                font-size: 16px;font-weight: 500; text-align: center;"><a
                            href="Solarcompany.com"
                            style="color: #767373; text-decoration: none;"
                            target="_blank">Solarcompany.com</a>
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
            <td
            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:40px 0px 0px">
            <div
                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding:20px;background-color:rgb(255,255,255); border-radius: 5px;">
                <div
                    style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;color:rgb(0,0,0);text-align:left">
                    <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #000;
                    font-size: 14px;font-weight: 500;"><strong>Pay Date :
                            {{$data['pay_date']}} </strong></p>
                    <table style="width: 100%;">
                        <tr>
                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';width: 150px;
                          background-color: #edf2fd;
                          color: #5379eb;
                          font-size: 24px;
                          text-align: center;
                          font-weight: 600;
                          letter-spacing: 1.6; padding: 5px;" rowspan="2">PAY STUB
                            </td>
                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                          color: #5379eb;
                            text-align: center;
                            font-size: 14px;
                            font-weight: 500; padding: 5px;">Pay Period</td>
                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                            color: #5379eb;
                        text-align: center;
                        font-size: 14px;
                        font-weight: 500; padding: 5px;">Accounts this pay period
                            </td>
                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                        color: #5379eb;
                        text-align: center;
                        font-size: 14px;
                        font-weight: 500;  padding: 5px;">YTD</td>
                        </tr>
                        <tr>
                            <td style="width: 165px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                            color: #767373;
                              text-align: center;
                              font-size: 14px;
                              font-weight: 500; padding: 5px;">{{ $data['pay_period'] }}</td>
                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                              color: #767373;
                          text-align: center;
                          font-size: 14px;
                          font-weight: 500; padding: 5px;">200</td>
                            <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                          color: #767373;
                          text-align: center;
                          font-size: 14px;
                          font-weight: 500; padding: 5px;">2195</td>
                        </tr>
                    </table>

                    <div style="margin-top: 40px;">
                        <p
                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                    font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                            Employee Information</p>
                        <table style="width: 100%;">
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                          color: #5379eb;
                            text-align: left;
                            padding-left: 8px !important;
                            font-size: 14px;
                            font-weight: 500; padding: 5px; width: 130px;">Employee
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                            color: #767373;
                        text-align: left;
                        padding-left: 8px !important;
                        font-size: 14px;
                        font-weight: 500; padding: 5px;">{{ $data['emp_name'] }}</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                        color: #5379eb;
                        text-align: left;
                        padding-left: 8px !important;
                        font-size: 14px;
                        font-weight: 500;  padding: 5px; width: 130px;">Employee ID
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                        color: #767373;
                        text-align: left;
                        padding-left: 8px !important;
                        font-size: 14px;
                        font-weight: 500;  padding: 5px;">47</td>
                            </tr>
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                            color: #5379eb;
                              text-align: left;
                              padding-left: 8px !important;
                              font-size: 14px;
                              font-weight: 500; padding: 5px; width: 130px;">SSN
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                              color: #767373;
                          text-align: left;
                          padding-left: 8px !important;
                          font-size: 14px;
                          font-weight: 500; padding: 5px;">XXX-XX-1234</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                          color: #5379eb;
                          text-align: left;
                          padding-left: 8px !important;
                          font-size: 14px;
                          font-weight: 500;  padding: 5px; width: 130px;">Bank
                                    Account</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                          color: #767373;
                          text-align: left;
                          padding-left: 8px !important;
                          font-size: 14px;
                          font-weight: 500;  padding: 5px;">XXXX1234</td>
                            </tr>
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                            color: #5379eb;
                              text-align: left;
                              padding-left: 8px !important;
                              font-size: 14px;
                              font-weight: 500; padding: 5px; width: 130px;">
                                    Address</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                              color: #767373;
                          text-align: left;
                          padding-left: 8px !important;
                          font-size: 14px;
                          font-weight: 500; padding: 5px;" colspan="3">108
                                    building, oute circle, new york, 82688</td>
                            </tr>

                        </table>
                    </div>

                    <div style="margin-top: 40px;">
                        <p
                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                       font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                            Earnings</p>
                        <table style="width: 100%;">
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                             color: #5379eb;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px; width: 60%;">
                                    Description</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                             color: #5379eb;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">Total</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                             color: #5379eb;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">YTD</td>
                            </tr>
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px; width: 60%;">
                                    Commisions</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">$1500.00</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">$25000.00</td>
                            </tr>
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px; width: 60%;">
                                    Overrides</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">$2800.00</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">$31200.00</td>
                            </tr>
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px; width: 60%;">
                                    Reconciliations</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">$800.00</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">$31200.00</td>
                            </tr>
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                            color: #5379eb;
                              text-align: right;
                              padding-right: 8px !important;
                              font-size: 14px;
                              font-weight: 500; padding: 5px; width: 60%;">Gross
                                    Pay</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                            color: #5379eb;
                              text-align: center;
                              font-size: 14px;
                              font-weight: 500; padding: 5px;">$4800.00</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                            color: #5379eb;
                              text-align: center;
                              font-size: 14px;
                              font-weight: 500; padding: 5px;">$56950.00</td>
                            </tr>

                        </table>
                    </div>
                    <div style="margin-top: 40px;">
                        <p
                            style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                       font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                            Deductions</p>
                        <table style="width: 100%;">
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                             color: #5379eb;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px; width: 60%;">
                                    Description</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                             color: #5379eb;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">Total</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                             color: #5379eb;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">YTD</td>
                            </tr>
                            <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: #767373;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px; width: 60%;">
                                    Standard Deductions</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: red;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">($1200.00)</td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                             color: red;
                               text-align: center;
                               font-size: 14px;
                               font-weight: 500; padding: 5px;">($19500.00)</td>
                            </tr>

                        </table>
                    </div>

                    <div style="display: flex; margin-top: 40px; align-items: center;">
                        <div style="width: 70%;">
                            <p
                                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                           font-size: 14px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">
                                Miscellaneous</p>
                            <table style="width: 100%;">
                                <tr>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                 color: #5379eb;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px; width: 60%;">
                                        Description</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                 color: #5379eb;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">Total</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                 color: #5379eb;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">YTD</td>
                                </tr>
                                <tr>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                 color: #767373;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px; width: 60%;">
                                        Adjustments</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                 color: #767373;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">$550.00</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                 color: red;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">($560.00)</td>
                                </tr>
                                <tr>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                 color: #767373;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px; width: 60%;">
                                        Reimbursements</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                 color: #767373;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">$60.24</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                 color: #767373;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">$60.24</td>
                                </tr>
                                <tr>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                 color: #5379eb;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px; width: 60%;">
                                        Total Deductions</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                 color: #5379eb;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">$760.74</td>
                                    <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                 color: #5379eb;
                                   text-align: center;
                                   font-size: 14px;
                                   font-weight: 500; padding: 5px;">$1940.24</td>
                                </tr>

                            </table>
                        </div>

                        <div style="width: 30%; text-align: center; padding: 40px;">
                            <p style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';margin-bottom:5px; margin-top:0px;color: #767373;
                            font-size: 16px;font-weight: 500; text-align: center; margin-bottom: 10px; text-transform: uppercase;">NET PAY</p>
                            <table style="width: 100%;">
                               <tr>
                                  <td  style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #edf2fd;
                                  color: #5379eb;
                                    text-align: center;
                                    font-size: 14px;
                                    font-weight: 500; padding: 5px;">TOTAL</td>
                               </tr>
                               <tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';background-color: #f7f7f7;
                                 color: #000;
                                   text-align: center;
                                   font-size: 17px;
                                   font-weight: 500; padding: 5px;"><strong>$40,39.26</strong></td>
                               </tr>
                            </table>
                        </div>
                    </div>


                </div>
            </div>
            <div
                style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol';box-sizing:border-box;padding-top:20px;color:rgb(153,153,153);text-align:center">
            </div>
        </td>
            
        </tr>
        <tr>
            <td>
               <div style="padding: 40px 0px;">
                    
                </div>
          </td>
        </tr>
        <tr>
            <td>        
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