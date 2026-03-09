<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequiDocsEmailSettings extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sequi_docs_email_settings';

    protected $fillable = [
        'id',
        'tempate_id',
        'email_template_name',
        'unique_email_template_code',
        'tmp_page_info',
        'email_description',
        'category_id',
        'email_content',
        'email_subject',
        'email_trigger',
        'is_active',
    ];

    /**
     * Get the SequiDocsTemplateCategories that owns the SequiDocsEmailSettings
     */
    public function SequiDocsTemplateCategorie(): BelongsTo
    {
        return $this->belongsTo(SequiDocsTemplateCategories::class, 'category_id', 'id');
    }

    public static function company_and_other_static_images($CompanyProfile)
    {

        $company_and_other_static_images = [];
        $logo = $CompanyProfile->logo;

        $defaultCompanyImage = config('app.aws_s3bucket_url').'/public_images/defaultCompanyImage.png';
        $header_image = config('app.aws_s3bucket_url').'/public_images/header-img.png';
        $sequifi_logo_with_name = config('app.aws_s3bucket_url').'/public_images/sequifi-logo.png';
        $letter_box = config('app.aws_s3bucket_url').'/public_images/letter-box.png';
        $sequifiLogo = config('app.aws_s3bucket_url').'/public_images/sequifiLogo.png';

        // replace contents
        // if(file_exists($logo)){
        //     // $Company_Logo = asset('/'.$logo);
        //     $Company_Logo = s3_getTempUrl(config('app.domain_name').'/'.$CompanyProfile->logo);
        //     // $Company_Logo = 'https://dev.sequifi.com/sequifi/company-image/1697558066custom_image_name.png';
        // }else{
        //     $Company_Logo = $defaultCompanyImage;
        // }
        // $Company_Logo = s3_getTempUrl(config('app.domain_name').'/'.$CompanyProfile->logo);
        $Company_Logo = config('app.aws_s3bucket_url').'/'.config('app.domain_name').'/'.$CompanyProfile->logo;

        $company_and_other_static_images['defaultCompanyImage'] = $defaultCompanyImage;
        $company_and_other_static_images['header_image'] = $header_image;
        $company_and_other_static_images['Company_Logo'] = $Company_Logo;
        $company_and_other_static_images['sequifi_logo_with_name'] = $sequifi_logo_with_name;
        $company_and_other_static_images['letter_box'] = $letter_box;
        $company_and_other_static_images['sequifiLogo'] = $sequifiLogo;

        return $company_and_other_static_images;
    }

    // Email templates header and footer like welcome mail , change password and other.
    public static function email_header_footer()
    {

        $CompanyProfile = CompanyProfile::first();
        $Company_Email = $CompanyProfile->company_email;
        $business_address = $CompanyProfile->business_address;
        $business_phone = $CompanyProfile->business_phone;
        $Company_Website = $CompanyProfile->company_website;
        $Company_name = $business_name = $CompanyProfile->business_name;
        $mailing_address = $CompanyProfile->mailing_address;

        $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($CompanyProfile);
        $header_image = $company_and_other_static_images['header_image'];
        $Company_Logo = $company_and_other_static_images['Company_Logo'];
        $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
        $letter_box = $company_and_other_static_images['letter_box'];
        $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

        $Business_Name_With_Other_Details = $Footer_Content = "<span>$business_name</span> | <span> + $business_phone </span> | <span>$business_address</span>";

        // <tr>
        // <td>
        // <div style="text-align: center;">
        // <img src="'.$header_image.'" alt="" style="width: 100%; margin: 0px auto;">
        // </div>
        // </td>
        // </tr>
        return $email_header_footer = '<div style="background-color:#efefef; height: auto;">
            <div class="aHl"></div>
            <div tabindex="-1"></div>
            <div class="ii gt">
                <div class="a3s aiL ">
                    <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                        <tr>
                        <td>
                            <div align="center" style="padding: 30px; align-items: center;">
                                <table cellpadding="0" cellspacing="0" width="650" class="wrapper" style="background-color: #fff; border-radius: 5px; margin-top: 5%;">
                                    <tr>
                                    <td>
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td bgcolor="#ffffff" align="left">
                                                <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
                                                    <tr>
                                                        <td>
                                                            <div style="text-align: center;">
                                                            <img src="'.$Company_Logo.'" alt="" style="width: 120px; height: 120px; margin: 0px auto;">
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    
                                                    <tr>
                                                        <td>
                                                            <div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';padding: 10px 40px; ">
                                                            <div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 14px;">
                                                                [Email_Content]
                                                            </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <div style="padding: 10px 40px;">
                                                            <div style="margin-top: 5px;">
                                                                <div style="margin-top: 5px;">
                                                                    <div style="border-bottom: 1px solid #e2e2e2; width: 100%; height: 2px; margin-top: 5px;"></div>
                                                                    <div style="padding-top: 10px;">
                                                                        <p style="font-weight: 500;font-size: 14px;line-height: 20px;color: #757575; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">
                                                                        '.$Business_Name_With_Other_Details.' 
                                                                        </p>

                                                                        <p style="font-weight: 500;font-size: 14px;line-height: 20px;color: #9E9E9E; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">© Copyright | <a href="https://'.$Company_Website.'" target="_blank" style="    font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #4879fe;font-size: 14px;text-decoration: none;">
                                                                        '.$Company_Website.'
                                                                        </a> All rights reserved
                                                                        </p>


                                                                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin: auto;">
                                                                            <tr>
                                                                                <td style="text-align: center;">
                                                                                    <p style="font-weight: 500; color: #9E9E9E;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-right: 10px;font-size: 18px;">
                                                                                        Powered by
                                                                                    </p>
                                                                                </td>
                                                                                <td style="text-align: center;">
                                                                                    <img src="'.$sequifi_logo_with_name.'"  alt="Sequifi" style="width: 115px;">
                                                                                </td>
                                                                            </tr>
                                                                        </table>
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
        </div>';
    }

    // lead added email content is in lead app\Models\Lead.php
    // change_password_email_content
    public static function change_password_email_content($user_data, $other_data)
    {

        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', '2')->first(); // for welcome mail. unique_email_template_code = 1
        $change_password_email_content['subject'] = 'Password Changed email';
        $change_password_email_content['is_active'] = 0;
        $change_password_email_content['template'] = '';

        // return $datas;
        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            // $auth_user_data = auth()->user();

            $resolve_key_data['Employee_Id'] = isset($user_data->employee_id) ? $user_data->employee_id : '';
            $resolve_key_data['Employee_Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee_User_Name'] = $user_data->email;
            $resolve_key_data['Employee_User_Password'] = $other_data['new_password'];
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;

            $company = CompanyProfile::first();

            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: 120px; height: 120px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);

            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Company Logo'] = config('app.base_url').$company->logo;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }

            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $final_email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $change_password_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
            $change_password_email_content['template'] = $final_email_content;
            $change_password_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
        }

        return $change_password_email_content;
    }

    // forgot_password_email_content
    public static function forgot_password_email_content($user_data, $other_data)
    {

        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', '7')->first(); // for welcome mail. unique_email_template_code = 1
        $forgot_password_email_content['subject'] = 'forgot Changed';
        $forgot_password_email_content['is_active'] = 0;
        $forgot_password_email_content['template'] = '';

        // $Forgot_Password_Link = $other_data['serverIP']."/api/reset-password/".$other_data['encrypt_id'];
        // $forgot_password_email_content['Forgot_Password_Link'] = $Forgot_Password_Link;

        $login_link = config('app.frontend_base_url', config('app.url'));

        // Check if FRONTEND_BASE_URL is retrieved correctly
        if ($login_link === false) {
            // Handle the error
            throw new Exception('FRONTEND_BASE_URL is not set in the environment variables.');
        }

        // Retrieve the encrypt_id from the data
        $encrypt_id = $other_data['encrypt_id'] ?? null;

        // Validate encrypt_id
        if (empty($encrypt_id)) {
            // Handle the error
            throw new Exception('encrypt_id is invalid or not provided.');
        }

        // Construct the Forgot_Password_Link without hashing
        $Forgot_Password_Link = rtrim($login_link, '/').'/reset-password/'.$encrypt_id;
        $Forgot_Password_Display_Link = rtrim($login_link, '/').'/reset-password/'.substr($encrypt_id, 0, 13);

        // Assign the constructed link to the email content array
        $forgot_password_email_content['Forgot_Password_Link'] = $Forgot_Password_Link;

        // Debugging output (optional)
        // echo $Forgot_Password_Link;

        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            // $auth_user_data = auth()->user();

            $resolve_key_data['Employee_Id'] = isset($user_data->employee_id) ? $user_data->employee_id : '';
            $resolve_key_data['Employee_Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee_User_Name'] = $user_data->email;
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;

            $company = CompanyProfile::first();
            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: auto; height: 90px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);

            // Forgot_Password_Button

            $Forgot_Password_Button_is = '<div style="padding: 5px 40px;text-align: center;">
                <a target="_blank" style="background: #0225ee;color: #fff; padding: 15px; text-decoration: none; border-radius: 5px;" href="'.$Forgot_Password_Link.'" class="button">Forgot Password</a>
            </div>';

            // Forgot_Password_Link
            // $Forgot_Password_Link_is = '<div style="text-align: center; width:70%;margin-left:auto;margin-left:15%;">
            //     <div class="borderStyle" style="border: 1px solid #ccc; border-radius: 5px;padding: 15px;background: #efeeee;word-wrap: break-word;">
            //         <a target="_blank" style="color: #0225ee; text-decoration: none;" href="' . $Forgot_Password_Link . '" class="button"> ' . $Forgot_Password_Display_Link . '</a>
            //     </div>
            // </div>';
            $Forgot_Password_Link_is = '<div style="text-align: center; width:70%; margin-left:auto; margin-left:15%;">
            <!-- Polite Message in a Separate Div -->
            <div style="margin-bottom: 20px; font-size: 14px; line-height: 1.5;">
            Please click the link below to reset your password. No need to copy, just <b style="color: black !important">click</b> to proceed.
            </div>
            <!-- Short Display Link in a Separate Div -->
            <div style="border: 1px solid #ccc; border-radius: 5px; padding: 15px; background: #efeeee; word-wrap: break-word;">
                <a target="_blank" style="color: #4879fe; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 14px; text-decoration: none;" href="'.$Forgot_Password_Link.'" class="button">
                    '.$Forgot_Password_Display_Link.'
                </a>
            </div>
            </div>';
            $email_content = str_replace('[Forgot_Password_Link]', $Forgot_Password_Link_is, $email_content);
            $email_content = str_replace('[Forgot_Password_Button]', $Forgot_Password_Button_is, $email_content);

            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Forgot_Password_Link'] = $Forgot_Password_Link;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }

            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $final_email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $forgot_password_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
            $forgot_password_email_content['template'] = $final_email_content;
            $forgot_password_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
        }

        return $forgot_password_email_content;
    }

    public static function review_personal_information_taxes_email_content($user_data, $other_data, $request)
    {
        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', $request->unique_email_template_code)->first(); // for welcome mail. unique_email_template_code = 1
        $forgot_password_email_content['subject'] = 'forgot Changed';
        $forgot_password_email_content['is_active'] = 0;
        $forgot_password_email_content['template'] = '';
        // $Forgot_Password_Link = $other_data['serverIP']."/api/reset-password/".$other_data['encrypt_id'];
        // $forgot_password_email_content['Forgot_Password_Link'] = $Forgot_Password_Link;
        $login_link = config('app.frontend_base_url', config('app.url'));
        // Check if FRONTEND_BASE_URL is retrieved correctly
        if ($login_link === false) {
            // Handle the error
            throw new Exception('FRONTEND_BASE_URL is not set in the environment variables.');
        }
        // Retrieve the encrypt_id from the data
        $encrypt_id = $other_data['encrypt_id'] ?? null;
        // Validate encrypt_id
        if (empty($encrypt_id)) {
            // Handle the error
            throw new Exception('encrypt_id is invalid or not provided.');
        }
        // Construct the Forgot_Password_Link without hashing
        $Forgot_Password_Link = rtrim($login_link, '/').'/reset-password/'.$encrypt_id;
        $Forgot_Password_Display_Link = rtrim($login_link, '/').'/reset-password/'.substr($encrypt_id, 0, 13);
        // Assign the constructed link to the email content array
        $forgot_password_email_content['Forgot_Password_Link'] = $Forgot_Password_Link;
        // Debugging output (optional)
        // echo $Forgot_Password_Link;
        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            // $auth_user_data = auth()->user();
            $resolve_key_data['Employee_Id'] = isset($user_data->employee_id) ? $user_data->employee_id : '';
            $resolve_key_data['Employee_Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee_User_Name'] = $user_data->email;
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;
            $company = CompanyProfile::first();
            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];
            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: auto; height: 90px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);
            // Forgot_Password_Button
            $Forgot_Password_Button_is = '<div style="padding: 5px 40px;text-align: center;">
                <a target="_blank" style="background: #0225EE;color: #fff; padding: 15px; text-decoration: none; border-radius: 5px;" href="'.$Forgot_Password_Link.'" class="button">Forgot Password</a>
            </div>';
            // Forgot_Password_Link
            // $Forgot_Password_Link_is = '<div style="text-align: center; width:70%;margin-left:auto;margin-left:15%;">
            //     <div class="borderStyle" style="border: 1px solid #ccc; border-radius: 5px;padding: 15px;background: #EFEEEE;word-wrap: break-word;">
            //         <a target="_blank" style="color: #0225EE; text-decoration: none;" href="' . $Forgot_Password_Link . '" class="button"> ' . $Forgot_Password_Display_Link . '</a>
            //     </div>
            // </div>';
            $Forgot_Password_Link_is = '<div style="text-align: center; width:70%; margin-left:auto; margin-left:15%;">
            <!-- Polite Message in a Separate Div -->
            <div style="margin-bottom: 20px; font-size: 14px; line-height: 1.5;">
            Please click the link below to reset your password. No need to copy, just <b style="color: black !important">click</b> to proceed.
            </div>
            <!-- Short Display Link in a Separate Div -->
            <div style="border: 1px solid #ccc; border-radius: 5px; padding: 15px; background: #EFEEEE; word-wrap: break-word;">
                <a target="_blank" style="color: #4879FE; font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 14px; text-decoration: none;" href="'.$Forgot_Password_Link.'" class="button">
                    '.$Forgot_Password_Display_Link.'
                </a>
            </div>
            </div>';
            $email_content = str_replace('[Forgot_Password_Link]', $Forgot_Password_Link_is, $email_content);
            $email_content = str_replace('[Forgot_Password_Button]', $Forgot_Password_Button_is, $email_content);
            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Forgot_Password_Link'] = $Forgot_Password_Link;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }
            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $final_email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $forgot_password_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
            $forgot_password_email_content['template'] = $final_email_content;
            $forgot_password_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
        }

        return $forgot_password_email_content;
    }

    // profile or employment package change notification email content
    public static function profile_or_employment_package_change_notification_email_content($user_data)
    {

        $Profile_Changes_Table = '';

        // dump('$user_data->batch_no');
        // dd($user_data->batch_no);

        // get changes in profile:
        $profileHistories = UserProfileHistory::where([
            'user_id' => $user_data->id,
            'updated_by' => auth()->user()->id,
            'batch_no' => $user_data->batch_no,
        ])->get();

        if (! $profileHistories->isEmpty()) {
            $Profile_Changes_Table .= '<table cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">';
            $Profile_Changes_Table .= '<tr>';
            $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">Field Changed</td>';
            $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">From</td>';
            $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">To</td>';
            $Profile_Changes_Table .= '</tr>';

            foreach ($profileHistories as $history) {

                $Profile_Changes_Table .= '<tr>';
                $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">'.$history->field_name.'</td>';
                $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">'.$history->old_value.'</td>';
                $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">'.$history->new_value.'</td>';
                $Profile_Changes_Table .= '</tr>';
            }
            $Profile_Changes_Table .= '</table>';
        }

        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', '5')->first();

        $change_password_email_content['subject'] = 'Profile changes';
        $change_password_email_content['is_active'] = 0;
        $change_password_email_content['template'] = '';

        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            // $auth_user_data = auth()->user();
            $email_content = str_replace('[Profile_Changes_Table]', $Profile_Changes_Table, $email_content);

            $email_content = str_replace('[User_Who_Made_The_Changes]', auth()->user()->full_name, $email_content);

            $resolve_key_data['Employee_Id'] = isset($user_data->employee_id) ? $user_data->employee_id : '';
            $resolve_key_data['Employee_Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee_User_Name'] = $user_data->email;
            // $resolve_key_data['Employee_User_Password'] = $other_data['new_password'];
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;

            $company = CompanyProfile::first();

            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: 120px; height: 120px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);

            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Company Logo'] = config('app.base_url').$company->logo;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }

            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $final_email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $change_password_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
            $change_password_email_content['template'] = $final_email_content;
            $change_password_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
        }

        return $change_password_email_content;
    }

    // Originization employment package change notification email content
    public static function originization_employment_package_change_notification_email_content($user_data, $data)
    {

        $Profile_Changes_Table = '';

        // dump('$user_data->batch_no');

        // get changes in profile:
        // $organization = UserOrganizationHistory::where([
        //     'user_id'       => $user_data->id,
        //     'updater_id'    => auth()->user()->id,
        // ])->orderBy('id','DESC')->first();

        if (! empty($data)) {
            $Profile_Changes_Table .= '<table cellspacing="0" cellpadding="0" style="width: 100%; border-collapse: collapse; border: 1px solid #ccc;">';
            $Profile_Changes_Table .= '<tr>';
            $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">Field Changed</td>';
            $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">From</td>';
            $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">To</td>';
            $Profile_Changes_Table .= '</tr>';

            foreach ($data as $key => $history) {

                $Profile_Changes_Table .= '<tr>';
                $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">'.$key.'</td>';
                $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">'.$history['old_value'].'</td>';
                $Profile_Changes_Table .= '<td style="border: 1px solid #ccc; padding: 10px;">'.$history['new_value'].'</td>';
                $Profile_Changes_Table .= '</tr>';
            }
            $Profile_Changes_Table .= '</table>';
        }

        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', '5')->first();

        $change_password_email_content['subject'] = 'Profile changes';
        $change_password_email_content['is_active'] = 0;
        $change_password_email_content['template'] = '';

        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            // $auth_user_data = auth()->user();
            $email_content = str_replace('[Profile_Changes_Table]', $Profile_Changes_Table, $email_content);

            $email_content = str_replace('[User_Who_Made_The_Changes]', auth()->user()->full_name, $email_content);

            $resolve_key_data['Employee_Id'] = isset($user_data->employee_id) ? $user_data->employee_id : '';
            $resolve_key_data['Employee_Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee_User_Name'] = $user_data->email;
            // $resolve_key_data['Employee_User_Password'] = $other_data['new_password'];
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;

            $company = CompanyProfile::first();

            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: 120px; height: 120px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);

            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Company Logo'] = config('app.base_url').$company->logo;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }

            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $final_email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $change_password_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
            $change_password_email_content['template'] = $final_email_content;
            $change_password_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
        }

        return $change_password_email_content;
    }

    // welcome mail email content
    public static function welcome_email_content($user_data, $other_data)
    {

        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', '1')->first(); // for welcome mail. unique_email_template_code = 1
        $welcome_email_content['subject'] = 'Welcome Mail';
        $welcome_email_content['is_active'] = 0;
        $welcome_email_content['template'] = '';
        // return $datas;
        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            // $auth_user_data = auth()->user();

            $resolve_key_data['Employee_Id'] = isset($user_data->employee_id) ? $user_data->employee_id : '';
            $resolve_key_data['Employee_Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee_User_Name'] = $user_data->email;
            $resolve_key_data['Employee_User_Password'] = $other_data['new_password'];
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;
            $company = CompanyProfile::first();

            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: 120px; height: 120px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);

            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Company Logo'] = $Company_Logo;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }

            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $final_email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $welcome_email_content['template'] = $final_email_content;
            $welcome_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
            $welcome_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
        }

        return $welcome_email_content;
    }

    // Document header and footer
    public static function document_header_footer($Company_Profile_data)
    {
        // Company Data  And other data
        $Company_Website = $Company_Profile_data->company_website;
        $Company_Email = $Company_Profile_data->company_email;
        $mailing_address = $Company_Profile_data->mailing_address;
        $business_address = $Company_Profile_data->business_address;
        $business_phone = $Company_Profile_data->business_phone;
        $Company_name = $business_name = $Company_Profile_data->business_name;

        $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($Company_Profile_data);
        $Company_Logo = $company_and_other_static_images['Company_Logo'];

        $Footer_Content = "<span>$business_name</span> | <span> + $business_phone </span> | <span> $Company_Email</span> | <span>$business_address</span>";

        // <hr style="margin: 2px;padding: 0px;">
        // * { font-family: DejaVu Sans, sans-serif; }

        return $header_footer = '
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <style>
                
                p{
                    margin:.35em;
                }
                @page { margin-top: 5px;margin-bottom: 12px; }
                body { margin: 0px; }
                </style>
            </head>
            <body style="padding-top: 72px; padding-bottom: 22px;margin-bottom: 22px;">
                <!-- Header -->
                <div style ="position: fixed;top: 0;left: 0;width: 100%;text-align: center;" class="header">
                    <img src="'.$Company_Logo.'" alt="" style="width: auto; max-height: 70px; margin: 0px auto;">
                </div>

                <!-- Footer -->
                <div style="position: fixed; bottom: 0; left: 0; width: 100%; text-align: center;" class="footer">
                    <hr style="margin: 2px;padding: 0px;">
                    <p style=" display: flex;justify-content: space-around;padding: 5px 35px;
                    ">'.$Footer_Content.'<p>
                </div>

                <!-- Main content -->
                <div id="mainContent" style="padding: 10px 0px;margin-bottom: 10px;padding-bottom: 5px;">
                    [Main_Content]
                </div>
            </body>
        ';
    }

    // email_header_footer
    public static function new_sequi_doc_email_header_footer()
    {
        return $html_template = '
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
            p{
                margin:.35em;
            }
            
            </style>
        </head>
        <div class="" style="background-color:#efefef; height: auto;">
            <div class="aHl"></div>
            <div tabindex="-1"></div>
            <div class="ii gt">
                <div class="a3s aiL ">
                    <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                        <tr>
                            <td>
                                <div align="center" style="padding: 30px; align-items: center;">
                                    <table cellpadding="0" cellspacing="0" width="650" class="wrapper" style="background-color: #fff; border-radius: 5px; margin-top: 10px;">
                                        <tr>
                                            <td>
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                    <tr>
                                                        <td bgcolor="#ffffff" align="left">
                                                            <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
                                                                <tr>
                                                                    <td>
                                                                        <div style="text-align: center;">
                                                                            <img src="[Company_Logo]" alt="" style="width: 120px; height: 120px; margin: 0px auto;">
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                
                                                                <tr>
                                                                    <td>
                                                                        <div style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';padding: 20px 40px; ">
                                                                            <div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 14px;">
                                                                            [Email_Content]
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        <div style="padding: 5px 40px;">
                                                                            <div style="margin-top: 3px;">
                                                                                <div style="margin-top: 3px;">
                                                                                    <div style="background-color: #f5f5f5; border: 1px solid #e2e2e2; border-radius: 10px; padding: 25px; text-align: center;">
                                                                                        <img src="[Letter_Box]" alt="" style="margin: 0px auto; width: 70px; height: 70px;">

                                                                                        <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #767373;font-size: 16px;font-weight: 600;margin-top: 10px;">[Company_Name] has sent offer with following documents 
                                                                                        </p>
                                                                                            <ul style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin: auto 20px;color: #767373;font-size: 15px;font-weight: 600; text-align: left;">
                                                                                            [Document_list_is]
                                                                                         </ul>
                                                                                        <a href="[Review_Document_Link]" target="_blank" style="font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';background-color: #6078ec;color: #fff;font-size: 14px;font-weight: 500;text-decoration: none;padding: 12px 25px;border-radius: 8px; display: inline-block; margin-top: 25px;">Review the offer</a>
                                                                                    </div>
                                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #6c6969;font-size: 14px;font-weight: 600;margin-top: 35px;">Or Click the link below to review the offer</p>
                                                                                    
                                                                                    <a href="[Review_Document_Link]" target="_blank" style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom:0px; margin-top:20px;color: #4879fe;font-size: 14px;font-weight: 500; display: block;line-height: 30px;">
                                                                                    [Review_Document_Link]
                                                                                    </a>
                                                                                    <div style="border-bottom: 1px solid #e2e2e2; width: 100%; height: 2px; margin-top: 40px;"></div>
                                                                                    <div style="padding-top: 10px;">
                                                                                        <p style="font-weight: 500;font-size: 14px;line-height: 20px;color: #757575; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">
                                                                                        [Business_Name_With_Other_Details] 
                                                                                        </p>
                                                                                        
                                                                                        <p style="font-weight: 500;font-size: 14px;line-height: 20px;color: #9E9E9E; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">Â© Copyright | | <a href="[Company_Email]" target="_blank" style="    font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #4879fe;font-size: 14px;text-decoration: none;">
                                                                                        [Company_Website]
                                                                                        </a> All rights reserved</p>

                                                                                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin: auto;">
                                                                                            <tr>
                                                                                                <td style="text-align: center;">
                                                                                                    <p style="font-weight: 500; color: #9E9E9E;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-right: 10px;font-size: 18px;">
                                                                                                        Powered by
                                                                                                    </p>
                                                                                                </td>
                                                                                                <td style="text-align: center;">
                                                                                                    <img src="[sequifi_logo_with_name]"  alt="Sequifi" style="width: 115px;">
                                                                                                </td>
                                                                                            </tr>
                                                                                        </table>
                                                                                        
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
        </div>';
    }

    public static function new_sequi_doc_email_header_footer_new($Company_Profile_data)
    {
        return $html_template = '
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <style>
        p{
            margin:.35em;
        }
        </style>
    </head>
    <div class="" style="background-color:#efefef; height: auto; max-width: 650px; margin: 0px auto;">
        <div class="aHl"></div>
        <div tabindex="-1"></div>
        <div class="ii gt">
            <div class="a3s aiL ">
                <table cellpadding="0" cellspacing="0" width="100%" style="table-layout: fixed;">
                    <tr>
                        <td>
                            <div align="center" style="padding: 15px; align-items: center;">
                                <table cellpadding="0" cellspacing="0" width="100%" class="wrapper" style="background-color: #fff; border-radius: 5px; margin-top: 10px;">
                                    <tr>
                                        <td>
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                <tr>
                                                    <td bgcolor="#FFFFFF" align="left">
                                                        <table border="0" cellpadding="0" cellspacing="0" style="width: 100%; height: 100%">
                                                            <tr>
                                                                <td>
                                                                    <div style="text-align: center;">
                                                                        <img src="[Company_Logo]" alt="" style="width: 120px; height: 120px; margin: 0px auto;">
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div style="margin-top: 5px; margin-bottom: 5px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';padding: 20px 40px; ">
                                                                        <div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; font-size: 14px;">
                                                                        [Email_Content]
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>
                                                                    <div style="padding: 5px 40px;">
                                                                        <div style="margin-top: 3px;">
                                                                            <div style="margin-top: 3px;">
                                                                                <div style="background-color: #F5F5F5; border: 1px solid #E2E2E2; border-radius: 10px; padding: 25px; text-align: center;">
                                                                                    <img src="[Letter_Box]" alt="" style="margin: 0px auto; width: 70px; height: 70px;">
                                                                                    <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #616161;font-size: 14px;font-weight: 500;margin-top: 20px; padding-left: 20px; text-align: left;">'.$Company_Profile_data->business_name.' has sent an Offer with following documents-</p>
                                                                                    
                                                                                    <ul style="text-align: left;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #616161;font-size: 14px;font-weight: 500;margin-bottom: 10px;">
                                                                                        [Document_list_is]
                                                                                    </ul>

                                                                                    <a href="[Review_Document_Link]" target="_blank" style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';background-color: #6078EC;
                                                                                    color: #fff;font-size: 14px;
                                                                                    font-weight: 500;text-decoration: none;
                                                                                    padding: 14px 30px;display: inline-block;margin-top: 25px;border-radius: 6px;
                                                                                    min-width: 150px;">Review Document</a>
                                                                                </div>
                                                                                <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #6C6969;font-size: 14px;font-weight: 600;margin-top: 35px;">Or Click the link below to review and Sign the document</p>
                                                                                <a href="[Review_Document_Link]" target="_blank" style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;
                                                                                margin-top: 20px;
                                                                                color: #4879FE;
                                                                                font-size: 13px;
                                                                                font-weight: 600;
                                                                                display: block;
                                                                                line-height: 30px;">
                                                                                [Review_Document_Link]
                                                                                </a>
                                                                                <!-- <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #333;font-size: 14px;font-weight: 600;margin-top: 20px;">If you agree to the terms in the [Document_Type], please review and sign the document.</p>
                                                                                <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #333;font-size: 15px;font-weight: 600;margin-top: 10px;">If not, refer back to this email and select from the provided options below.</p> -->
                                                                                <div style="border-bottom: 1px solid #E2E2E2; width: 100%; height: 2px; margin-top: 40px;"></div>
                                                                                <div style="padding-top: 10px;">
                                                                                    <p style="font-weight: 500;font-size: 13px;line-height: 20px;color: #757575; margin-bottom: 20px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">
                                                                                    [Business_Name_With_Other_Details]
                                                                                    </p>
                                                                                    <p style="font-weight: 500;font-size: 12px;line-height: 20px;color: #9E9E9E; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">© Copyright | <a href="[Company_Email]" target="_blank" style="font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #4879FE;font-size: 12px;text-decoration: none;">
                                                                                    [Company_Website]
                                                                                    </a>| All rights reserved</p>
                                                                                    <table role="presentation" cellspacing="0" cellpadding="0" style="margin: auto; margin-bottom: 10px;">
                                                                                        <tr>
                                                                                            <td style="text-align: center;">
                                                                                                <p style="font-weight: 500; color: #9E9E9E;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-right: 10px;font-size: 12px;">
                                                                                                    Powered by
                                                                                                </p>
                                                                                            </td>
                                                                                            <td style="text-align: center;">
                                                                                                <img src="[sequifi_logo_with_name]"  alt="Sequifi" style="width: 115px;">
                                                                                            </td>
                                                                                        </tr>
                                                                                    </table>
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
                            <div style="align-items: center;">
                                <p style="font-weight: 400;font-size: 13px;line-height: 20px;color: #757575; margin-bottom: 20px; text-align: center;">
                                    <a href="#" target="_blank" style="font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #9E9E9E;font-size: 12px;text-decoration: none;">Unsubscribe</a> <span>| Get Help | Report  <span>
                                    </p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>';
    }

    public static function new_sequi_doc_email_body_for_external_recipient()
    {
        return '
            <p>Hi, You have to review some mandatory documents.</p>
        ';
    }

    // Current Pay Stub Notification email content
    public static function current_pay_stub_notification_email_content($user_data)
    {

        $SequiDocsEmailSettings = SequiDocsEmailSettings::where('category_id', '=', '3')->where('unique_email_template_code', '=', '3')->first();
        $change_password_email_content['subject'] = '';
        $change_password_email_content['is_active'] = 0;
        $change_password_email_content['template'] = '';

        if ($SequiDocsEmailSettings != null && $SequiDocsEmailSettings->email_content != null) {
            $email_content = $SequiDocsEmailSettings->email_content;
            // $auth_user_data = auth()->user();

            $resolve_key_data['Employee_Id'] = isset($user_data->employee_id) ? $user_data->employee_id : '';
            $resolve_key_data['Employee_Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee Name'] = isset($user_data->first_name) ? $user_data->first_name.' '.$user_data->last_name : '';
            $resolve_key_data['Employee_User_Name'] = $user_data->email;
            // $resolve_key_data['Employee_User_Password'] = $other_data['new_password'];
            $System_Login_Link = config('app.login_link');
            $resolve_key_data['System_Login_Link'] = $System_Login_Link;

            $company = CompanyProfile::first();

            $company_and_other_static_images = SequiDocsEmailSettings::company_and_other_static_images($company);
            $header_image = $company_and_other_static_images['header_image'];
            $Company_Logo = $company_and_other_static_images['Company_Logo'];
            $sequifi_logo_with_name = $company_and_other_static_images['sequifi_logo_with_name'];
            $letter_box = $company_and_other_static_images['letter_box'];
            $sequifiLogo = $company_and_other_static_images['sequifiLogo'];

            $Company_Logo_is = '<img src="'.$Company_Logo.'" style="width: 120px; height: 120px; margin: 0px auto;">';
            $email_content = str_replace('[Company_Logo]', $Company_Logo_is, $email_content);
            $email_content = str_replace('[Company Logo]', $Company_Logo_is, $email_content);

            $resolve_key_data['Company_Name'] = $company->name;
            $resolve_key_data['Company_Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company_Phone'] = $company->phone_number;
            $resolve_key_data['Company_Email'] = $company->company_email;
            $resolve_key_data['Company_Website'] = $company->company_website;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            $resolve_key_data['Company Name'] = $company->name;
            $resolve_key_data['Company Address'] = $company->business_address;
            $resolve_key_data['company_address_line2'] = $company->business_address;
            $resolve_key_data['Company Phone'] = $company->phone_number;
            $resolve_key_data['Company Email'] = $company->company_email;
            $resolve_key_data['Company Website'] = $company->company_website;
            $resolve_key_data['Company Logo'] = config('app.base_url').$company->logo;
            $resolve_key_data['Current_Date'] = date('d-m-Y');
            foreach ($resolve_key_data as $key => $value) {
                $email_content = str_replace('['.$key.']', $value, $email_content);
            }

            $email_header_footer = SequiDocsEmailSettings::email_header_footer();
            $final_email_content = str_replace('[Email_Content]', $email_content, $email_header_footer);
            $change_password_email_content['is_active'] = $SequiDocsEmailSettings->is_active;
            $change_password_email_content['template'] = $final_email_content;
            $change_password_email_content['subject'] = $SequiDocsEmailSettings->email_subject;
        }

        return $change_password_email_content;
    }
}
