@extends('layout.mail_layout')
@section('title') 
    Close PayRoll
@endsection
<div class="" style="background-color:#efefef; height: auto;">
    <div class="aHl"></div>
    <div tabindex="-1"></div>
    <div class="ii gt">
        <div class="a3s aiL ">
            <u></u>

            <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                <tr>
                    <td>
                        <div align="center" style="padding: 30px; align-items: center;">
                            <table cellpadding="0" cellspacing="0" width="650" class="wrapper"
                                style="background-color: #fff; border-radius: 5px; margin-top: 5%;">
                                <tr>
                                    <td>
                                        <div style="text-align: center;">
                                            <img src="{{url('/')}}/sequifi-images/com-img.png" alt="" style="width: 115px;">
                                            
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div style="text-align: center;">
                                            <img src="{{url('/')}}/sequifi-images/header-img.png"  alt="" style="width: 100%; margin: 0px auto;">
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div
                                            style="margin-top: 30px; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;padding: 0px 40px; ">
                                            <h3
                                                style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 18px; text-align: center;color: #4a4a4a;">
                                                Paystub Available!</h3>
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

                                            {{-- <p
                                                style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 15px;line-height: 24px; margin-bottom: 5px;">
                                                Or click the link below-
                                            </p> --}}

                                            {{-- <p
                                                style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 0px;line-height: 24px;">
                                                <a href="#" target="_blank"
                                                    style="color: #6078ec; text-decoration: none;font-weight: 500;">https://na4.paystub.sequifi/signing/emailsvl-92ed75efb1a94705aebc5556994e83f70f9e6088fa9d4baaa56bf7488ff08eca</a>
                                            </p> --}}

                                            <div style="margin-top: 30px;">
                                                <p
                                                    style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 15px;line-height: 24px; margin-bottom: 0px;">
                                                    Best regards,
                                                </p>
                                                <p 
                                                    style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; font-size: 14px; margin-top: 0px;line-height: 24px;">
                                                    {{$newData['CompanyProfile']->business_name}}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td bgcolor="#ffffff" align="left">
                                                    <table border="0" cellpadding="0" cellspacing="0"
                                                        style="width: 100%; height: 100%">
                                                        <tr>
                                                            <td>
                                                                <div style="padding: 20px 40px; margin-top: 15px;">
                                                                    <div style="margin-top: 45px;">
                                                                        <div style="margin-top: 45px;">
                                                                            <div
                                                                                style="border-bottom: 1px solid #e2e2e2; width: 100%; height: 2px; margin-top: 40px;">
                                                                            </div>

                                                                            <div style="padding-top: 10px;">
                                                                                <p
                                                                                    style="font-weight: 500;
                                                                                font-size: 14px;
                                                                                line-height: 20px;
                                                                                color: #757575; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; text-align: center;">
                                                                                  {{$newData['CompanyProfile']->business_name}} | {{$newData['CompanyProfile']->business_phone}} | {{$newData['CompanyProfile']->business_address}}</p>
                                                                                <p
                                                                                    style="font-weight: 500;
                                                                                font-size: 14px;
                                                                                line-height: 20px;
                                                                                color: #9E9E9E; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol; text-align: center;">
                                                                                    © Copyright | |
                                                                                    <a href="#" target="_blank"
                                                                                        style="font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;;
                                                                                margin-bottom: 0px;
                                                                                color: #4879fe;
                                                                                font-size: 14px;
                                                                                text-decoration: none;">{{$newData['CompanyProfile']->company_website}}</a></a> All
                                                                                    rights
                                                                                    reserved
                                                                                </p>
                                                                                <div
                                                                                    style="margin: 0px auto;
                                                                                width: 65%;text-align: center; margin-top: 40px; margin-bottom: 20px; padding-top: 0px;">
                                                                                    <p
                                                                                        style="font-weight: 500;font-size: 15.8594px;line-height: 18px;color: #9E9E9E;margin-right: 12px;font-family: -apple-system,BlinkMacSystemFont,\Segoe UI\,Roboto,Helvetica,Arial,sans-serif,\Apple Color Emoji\,\Segoe UI Emoji\,\Segoe UI Symbol; margin-right: 10px;display: inline-block; margin-bottom: 0px; margin-top: 0px;">
                                                                                        Powered by</p>

                                                                                    <div style="display: inline-block;">
                                                                                        <img src="{{url('/')}}/sequifi-images/sequifi-logo.png"
                                                                                            alt=""
                                                                                            style="width: 115px;">
                                                                                    </div>
                                                                                </div>


                                                                            </div>

                                                                        </div>
                                                                    </div>
                                                                </div>

                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>




                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>