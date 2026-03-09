<?php $imagelogo = DB::table('company_profiles')->select('logo')->first();
$image = $imagelogo->logo;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
  <title>Thankyou</title>
  <style>
    body {
        margin: 0px;
    }
</style>
</head>

<body>

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
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td width="100" style="text-align: center;">
                                                    <a href="#" target="_blank"
                                                        style="margin-top: 45px; margin-bottom:20px; display: block;">
                                                        <img src="{{env('BASE_URL').$image}}" alt="" width="200">
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td bgcolor="#ffffff" align="left">
                                                    <table border="0" cellpadding="0" cellspacing="0"
                                                        style="width: 100%;">
                                                        <tr>
                                                            <td>
                                                                <div
                                                                    style="width: 500px; border-bottom: 1px solid #E0E0E0; margin: 30px auto;">
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td bgcolor="#ffffff" align="left">
                                                    <table border="0" cellpadding="0" cellspacing="0"
                                                        style="width: 100%; height: 100%">
                                                        <tr>
                                                            <td>
                                                                <div style="padding: 20px 40px;">
                                                                    <h3 style="color:#74787e; width:100%!important;word-break:break-word;font-family:Arial,sans-serif;margin:0px;font-size: 16px;">
                                                                        <strong>{{ isset($data['full_company_name']) ? $data['full_company_name']:''}}</strong>
                                                                    </h3>
                                                                </div>
                                                                <div style="padding: 10px 40px; text-align: center;">
                                                                     <p
                                                                        style="margin: 0px;color:#74787e; width:100%!important;word-break:break-word;font-family:Arial,sans-serif;font-size: 15px; margin-bottom: 8px;">
                                                                        {{ isset($data['company_address_line1']) ? $data['company_address_line1']:''}}</p>
                                                                    <p
                                                                        style="margin: 0px;color:#74787e; width:100%!important;word-break:break-word;font-family:Arial,sans-serif;font-size: 15px; margin-bottom: 8px;">
                                                                        {{ isset($data['company_phone']) ? $data['company_phone']:''}}</p>
                                                                    <p
                                                                        style="margin: 0px;color:#74787e; width:100%!important;word-break:break-word;font-family:Arial,sans-serif;font-size: 15px; margin-bottom: 8px;">
                                                                        {{ isset($data['company_email']) ? $data['company_email']:''}}</p>
                                                                    <p
                                                                        style="margin: 0px;color:#74787e; width:100%!important;word-break:break-word;font-family:Arial,sans-serif;font-size: 15px; margin-bottom: 8px;">
                                                                        {{ isset($data['company_website']) ? $data['company_website']:''}}</p>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            <!-- Add icon -->
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
                                            <tr>
                                                <td height="30"></td>
                                            </tr>
                                            <!-- End icon -->
                                            <tr>
                                                <td bgcolor="#ffffff" align="center">
                                                 <table border="0" cellpadding="0" cellspacing="0" width="90%">
                                                        <tr>
                                                            <td>
                                                                <div
                                                                    style="font-family: Arial,sans-serif;box-sizing:border-box;color:rgb(0,0,0);text-align:left; margin-top: 40px">
                                                                    <h1
                                                                        style="font-family: Arial,sans-serif; box-sizing:border-box;color:#3d4852;font-size:16px;font-weight:bold;margin-top:0;text-align:left;margin:1rem 0; margin-bottom: 40px; line-height:1.8em;">
                                                                        {{ $data['current_date'] }}</h1>
                                                                    <h1
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:16px;font-weight:bold;margin-top:0;text-align:left;margin:1rem 0; margin-bottom: 25px; margin-top: 50px;line-height:1.8em;">
                                                                        Dear {{ $data['employee_name'] }} :</h1>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:15px;margin-top:0;text-align:left;padding-bottom:10px;  line-height:1.8em;">
                                                                        We are pleased to offer you a position at
                                                                        <strong>{{ $data['full_company_name'] }}</strong> as the <strong>{{ $data['position'] }}</strong>
                                                                        in <strong>{{ $data['office_location'] }}</strong>. As a
                                                                        <strong>{{ $data['position'] }}</strong> you will primarily be responsible for for a variety of roles and responsibilities that are important to the growth and success of the company as well as yourself. 
                                                                        Please review the following details regarding your offer below:</p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;padding-bottom:10px; line-height:1.8em;">
                                                                        <strong>Base Compensation:</strong> Starting commission will be
                                                                        {{ $data['commission'] }} %. All solar panel sales will have a redline of
                                                                        <strong>{{ $data['redline_par_watt'] }}</strong>. You will receive an upfront
                                                                        payment on all accounts of <strong>{{ $data['upfront_amount'] }}</strong>, with
                                                                        the remainder being paid upon completion of installation. Your
                                                                        commission is on a sliding scale based upon
                                                                        <strong>{{ $data['sliding_scale_metric'] }}</strong> with the following tiers and
                                                                        associated commissions listed below. (Tier system shown in graphic
                                                                        below) Commissions will be paid on a
                                                                        <strong>{{ $data['pay_frequency'] }}</strong> basis. Additionally, a portion
                                                                        equal to <strong>{{ $data['withholding_amount'] }}</strong> of your commissions
                                                                        will be held and paid as a lump sum every
                                                                        <strong>{{ $data['reconciliation_period_length'] }}</strong>.
                                                                    </p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left; line-height:1.8em; margin-bottom: 2px;">
                                                                        <strong>Override Payments:</strong> In addition to your base
                                                                        compensation, you shall also receive override payments on
                                                                        individuals within your network. If you have any questions regarding
                                                                        override policy, please contact your hiring manager. Your override
                                                                        values are as follows: </p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;margin-bottom:2px; line-height:1.8em;">
                                                                        <strong>Direct overrides:</strong>{{ $data['direct_override_value'] }} </p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;margin-bottom:2px; line-height:1.8em;">
                                                                        <strong>Indirect overrides:</strong>{{ $data['indirect_override_value'] }} </p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;padding-bottom:10px; line-height:1.8em;">
                                                                        <strong>Office overrides:</strong>{{ $data['office_override_value'] }} </p>

                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;padding-bottom:10px; line-height:1.8em;">
                                                                        <strong>Signing Bonus:</strong> The Company shall provide you with a
                                                                        sign-on bonus in the amount of <strong>{{ $data['bonus_amount'] }} </strong>. The
                                                                        signing bonus shall be payable on <strong>{{ $data['bonus_pay_date'] }} </strong>
                                                                        and shall be paid gross less applicable taxes.</p>

                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;margin-bottom:2px; line-height:1.8em;">
                                                                        <strong>Standard Deductions:</strong> The Company will deduct the
                                                                        following amounts for predetermined costs in the amounts shown
                                                                        below. The amount(s) will be deducted from each
                                                                        <strong>{{ $data['pay_period'] }} </strong> pay period. If the amount to be
                                                                        deducted is greater than the payroll due, then the amount will be
                                                                        rolled over to a later date.</p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;margin-bottom:2px; line-height:1.8em;">
                                                                        <strong>{{ $data['deduction1_name'] }}:</strong> {{ $data['deduction1_value'] }} </p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;padding-bottom:10px; line-height:1.8em;">
                                                                        <strong>{{ $data['deduction2_name'] }} :</strong>{{ $data['deduction2_value'] }} </p>

                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;padding-bottom:10px; line-height:1.8em;">
                                                                        <strong>Terms and Conditions:</strong> Your effective start date
                                                                        with <strong>{{ $data['full_company_name'] }} </strong> in
                                                                        <strong>{{ $data['office_location'] }}</strong> is set for
                                                                        <strong>{{ $data['start_date'] }}</strong> and has a preset termination date of
                                                                        <strong>{{ $data['end_date'] }}</strong>. If you believe you cannot appear by
                                                                        this date for any reason please reach out to your hiring manager
                                                                        immediately. Additionally, there is an probationary period of
                                                                        <strong>{{ $data['probation_length'] }} </strong> days. At the end of this period
                                                                        you will be given permanent login credentials and full access to the
                                                                        software.</p>

                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:14px;margin-top:0;text-align:left;padding-bottom:10px; line-height:1.8em;">
                                                                        <strong>{{ $data['employee_name'] }} </strong>, we are truly excited about
                                                                        the prospect of your joining the team at
                                                                        <strong>{{ $data['full_company_name'] }} </strong> and believe you will be a great
                                                                        addition to the team. We are confident that you will find your
                                                                        association with the company both challenging and rewarding. If you
                                                                        have any questions or concerns, please feel free to contact me at
                                                                        <strong>{{ $data['recruiter_phone_number'] }} </strong>. If everything above
                                                                        seems in order, please complete and sign the form to begin your
                                                                        onboarding process.</p>

                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:15px;margin-top:30px;text-align:left;margin-bottom:3px; line-height:1.8em;">
                                                                        Sincerely,</p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:15px;margin-top:0;text-align:left;margin-bottom:2px; line-height:1.8em;">
                                                                        {{ $data['recruiter_manager_name'] }} </p>
                                                                    <p
                                                                        style="font-family: Arial,sans-serif;box-sizing:border-box;color:#3d4852;font-size:15px;margin-top:0;text-align:left;padding-bottom:10px; line-height:1.8em;">
                                                                        {{ $data['recruiter_manager_position'] }} </p>

                                                                </div>

                                                                <div
                                                                    style="font-family: Arial,sans-serif;box-sizing:border-box;color:rgb(0,0,0);text-align:left; margin-top: 40px">
                                                                    <a href="#" target="_blank" style="text-decoration: none;
                                                                    background-color: #00ffff;
                                                                    padding: 15px 20px;
                                                                    color: #000;
                                                                    font-weight: 500;
                                                                    font-size: 15px;
                                                                    border-radius: 2px;
                                                                    border-top-right-radius: 12px;
                                                                    border-bottom-left-radius: 12px;">Review Additional Documents</a>

                                                                    <div style="margin-top: 30px;
                                                                    display: flex;
                                                                    align-items: center;">
                                                                        <input type="checkbox" id="checks" checked
                                                                            style="width: 20px; height: 20px;accent-color: #181c32;">
                                                                        <label for="checks" style="margin-left: 5px;">I have reviewed all
                                                                            Information contained in this Email.</label>
                                                                    </div>

                                                                    <div style="display: flex; margin-top: 70px; justify-content: center;">
                                                                        <a href="{{ $data['url'] }}/api/accepted_declined_requested_change_hiring_process/{{ $data['encrypt_id'] }}/Accepted" target="_blank" style="text-decoration: none;
                                                                      background-color: #00ff3e;
                                                                      padding: 13px 25px;
                                                                    color: #fff;
                                                                    font-weight: 500;
                                                                    font-size: 16px;
                                                                    border-radius: 2px;
                                                                    border-top-right-radius: 12px;
                                                                    border-bottom-left-radius: 12px;
                                                                    height: 18px;
                                                                    display: block;
                                                                    line-height: 16px; margin-right: 10px;">Accept</a>
                                                                        <a href="{{ $data['url'] }}/api/requested_change_hiring_process/{{ $data['encrypt_id'] }}/Requested Change" target="_blank" style="text-decoration: none;
                                                                      background-color: #fdc02e ;
                                                                      padding: 13px 25px;
                                                                        color: #fff;
                                                                        font-weight: 500;
                                                                        font-size: 16px;
                                                                        border-radius: 2px;
                                                                        border-top-right-radius: 12px;
                                                                        border-bottom-left-radius: 12px;
                                                                        height: 18px;
                                                                        display: block;
                                                                        line-height: 16px; margin-right: 10px;">Request Changes</a>
                                                                        <a href="{{ $data['url'] }}/api/accepted_declined_requested_change_hiring_process/{{$data['encrypt_id'] }}/Declined" target="_blank" style="text-decoration: none;
                                                                      background-color: #ff0000;
                                                                      padding: 13px 25px;
                                                                        color: #fff;
                                                                        font-weight: 500;
                                                                        font-size: 16px;
                                                                        border-radius: 2px;
                                                                        border-top-right-radius: 12px;
                                                                        border-bottom-left-radius: 12px;
                                                                        height: 18px;
                                                                        display: block;
                                                                        line-height: 16px;">Reject</a>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>

                                                    </table>

                                                </td>
                                                
                                            </tr>
                                            <tr>
                                                <td>
                                                   <div style="padding: 40px 0px;">
                                                        
                                                    </div>
                                              </td>
                                            </tr>

                                            <tr>
                                                <td align="center" style="height: 123px; background: #F7F7F7;">
                                                    <table border="0" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td align="left"
                                                                style="font-size: 13px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #666666; text-decoration: none;">
                                                                <div
                                                                    style="display: flex; align-items: center;justify-content: center; padding-top: 20px;">
                                                                    <p style="font-weight: 500;
                                                                   font-size: 15.8594px;
                                                                   line-height: 18px;
                                                                   color: #9E9E9E;
                                                                   margin-right: 12px;">Powered by</p> <img
                                                                        src="{{env('BASE_URL').$image}}" alt=""
                                                                        style="width: 115px;">
                                                                </div>
                                                                <p style="text-align: center;"><a href="www.sequifi.com"
                                                                        target="_blank" style="font-weight: 500;
                                                                    font-size: 16px;
                                                                    line-height: 20px;
                                                                    color: #757575;
                                                                    text-align: center;"> www.sequifi.com</a></p>
                                                                <p style="font-weight: 500;
                                                                    font-size: 13px;
                                                                    line-height: 20px;
                                                                    color: #757575; margin-bottom: 20px;">Copyright
                                                                    2023 Sequifi All rights reserved</p>
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
</body>

</html>