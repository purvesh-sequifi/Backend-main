<?php 
$CompanyProfile = DB::table('company_profiles')->first();
$company_name = $CompanyProfile->name;
$address = $CompanyProfile->address;
$company_website = $CompanyProfile->company_website;
$phone_number = $CompanyProfile->phone_number;
$mailing_city = $CompanyProfile->mailing_city;
$mailing_state = $CompanyProfile->mailing_state;
$mailing_zip = $CompanyProfile->mailing_zip;
$country = $CompanyProfile->country;
$company_and_other_static_images = \App\Models\SequiDocsEmailSettings::company_and_other_static_images($CompanyProfile);
$header_image = $company_and_other_static_images['header_image'];
$Company_Logo = $company_and_other_static_images['Company_Logo'];
$sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
$letter_box = $company_and_other_static_images['letter_box'];
$sequifiLogo = $company_and_other_static_images['sequifiLogo'];
$System_Login_Link = env('LOGIN_LINK');

$business_name = $CompanyProfile->business_name;
$business_phone = $CompanyProfile->business_phone;
$company_email = $CompanyProfile->company_email;
$business_address = $CompanyProfile->business_address;

$Footer_Content = "$business_name |  + $business_phone  |  $company_email | $business_address" ;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
        <title>@yield('title')</title>
        <style type="text/css">
            body {
                margin: 0px;
            }
        
            #toggle_npwd
            {
                cursor: pointer;
                margin: 10px;
                position: absolute;
                right: 17px;
            }
            #toggle_cpwd
            {
                cursor: pointer;
                margin: 10px;
                position: absolute;
                right: 17px;
            }
            .eyestyle {
                display: flex;
                position: relative;
            }
            #cpassword-error {
                position: absolute;
                top: 39px;
                color: brown;
            }
            #npassword-error {
                position: absolute;
                top: 39px;
                color: brown;
            }

            p{
                margin: .3em 0px;
            }
        </style>
   </head>
   <body>
      <div style="background-color:#efefef; height: auto;">
         <div class="aHl"></div>
         <div tabindex="-1"></div>
         <div class="ii gt">
            <div class="a3s aiL ">
               <u></u>
               <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                  <tr>
                     <td>
                        <div align="center" style="padding: 20px; align-items: center;">
                           <table cellpadding="0" cellspacing="0" width="700" class="wrapper"
                              style="background-color: #fff; border-radius: 5px; margin-top: 5%;">
                              <tr>
                                 <td>
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                       {{-- company logo row --}}
                                        <tr>
                                          <td width="100" style="text-align: center;">
                                             <a href="{{$System_Login_Link}}" target="_blank" style="margin-top: 15px; display: block;">
                                             <img style="max-height: 90px;width: auto" src="{{$Company_Logo}}" alt="Company Logo">
                                             </a>
                                          </td>
                                       </tr>
                                       {{-- Hr Row --}}
                                       <tr>
                                          <td bgcolor="#ffffff" align="left">
                                             <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
                                                <tr>
                                                   <td>
                                                      <div
                                                         style="border-bottom: 1px solid #E0E0E0; margin: 5px auto;">
                                                      </div>
                                                   </td>
                                                </tr>
                                             </table>
                                          </td>
                                       </tr>

                                       {{-- Heading Row / title Row --}}
                                       @yield('top_head')

                                       {{-- Add icon Row --}}
                                       @yield('icon_section')

                                       <!-- main body tr Row  -->
                                       <tr>
                                          <td align="center">
                                             <div style="padding: 10px 40px;">
                                                @yield('main_content')
                                             </div>
                                          </td>
                                       </tr>

                                       {{-- Footer Row --}}
                                       <tr>
                                          <td align="center" style="height: 123px; background: #F7F7F7;">
                                             <table border="0" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td align="center" style="height: 123px; background: #F7F7F7;">
                                                        <table border="0" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td align="left" style="font-size: 13px; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif,'Apple Color Emoji','Segoe UI Emoji','Segoe UI Symbol'; color: #666666; text-decoration: none;">
                                                                    <div style="display: flex; align-items: center;justify-content: center; padding-top: 20px;">
                                                                        <p style="font-weight: 500;font-size: 13px;line-height: 20px;color: #757575; text-align: center;">{{$Footer_Content}}</p>
                                                                    </div>

                                                                    <p style="font-weight: 500;font-size: 13px;line-height: 20px;color: #757575;text-align: center; ">Copyright 2023 
                                                                        <a href="{{$company_website}}"
                                                                        target="_blank" style="font-weight: 500;font-size: 16px;line-height: 20px;color: #757575;text-align: center;">{{$company_website}}</a> All rights reserved
                                                                    </p>

                                                                    <table role="presentation" cellspacing="0" cellpadding="0" style="margin: auto;">
                                                                        <tr>
                                                                           <td style="text-align: center;">
                                                                              <p style="font-weight: 500; color: #9E9E9E;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-right: 10px;font-size: 18px;">
                                                                                 Powered by
                                                                              </p>
                                                                           </td>
                                                                           <td style="text-align: center;">
                                                                              <img src="{{$sequifi_logo_with_name}}"  alt="Sequifi" style="width: 115px;">
                                                                           </td>
                                                                        </tr>
                                                                     </table>
                                                                </td>
                                                            </tr>
                                                        </table>
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
      <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.0/jquery.validate.min.js"> </script>
<script>
    $("#resetpassword").validate({
        rules: {
        password: {
            minlength: 8,
        },
        confirmPassword: {
            minlength: 8,
            equalTo: "#npassword"
        }
        },
    });

</script>
<script type="text/javascript">
        $(function () {
            $("#toggle_npwd").click(function () {
                $(this).toggleClass("fa-eye fa-eye-slash");
               var type = $(this).hasClass("fa-eye-slash") ? "text" : "password";
                $("#npassword").attr("type", type);
            });
            $("#toggle_cpwd").click(function () {
                $(this).toggleClass("fa-eye fa-eye-slash");
               var type = $(this).hasClass("fa-eye-slash") ? "text" : "password";
                $("#cpassword").attr("type", type);
            });
        });
</script>
   </body>
</html>