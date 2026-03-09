<?php

namespace Database\Seeders;

use App\Models\SequiDocsEmailSettings;
use App\Traits\CompanyDependentSeeder;
use Illuminate\Database\Seeder;
use App\Models\CompanyProfile;

class SequiDocsEmailSettingSeeder extends Seeder
{
    use CompanyDependentSeeder;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Validate prerequisites (but don't require company profile for this seeder)
        $this->warnIfProduction();

        $companyProfile = CompanyProfile::first();
        $companyName = $companyProfile->name ?? 'Company/Business Name';
        $sequi_docs_email_templates = [
            [
                'unique_email_template_code' => 1,
                'tmp_page_info' => "This email is automatically generated when an administrator approves a new employee's onboarding process. It contains the login credentials that the employee will use to access our systems.",
                'email_template_name' => 'Welcome Email',
                'category_id' => 3,
                'email_subject' => 'Welcome to '.$companyName.'!',
                'email_trigger' => 'Admin Approves newly hired',
                'email_description' => 'WelCome Email',
                'is_active' => 1,
                'email_content' => "<p>Dear <strong>[Employee_Name] , </strong></p><p><br></p><p><strong>Congratulations on being accepted at $companyName! We are excited to have you on our team. You are now on the top sales team in the U.S.A. Now it\'s time to represent.</strong></p><p><br></p><p><strong>Here are your payroll login credentials to login to Sequifi along with the login link.</strong></p><p><br></p><p><strong> [System_Login_Link] </strong></p><p><br></p><p>Username - <strong> [Employee_User_Name] </strong></p><p><br></p><p>Password - <strong> [Employee_User_Password] </strong></p>",

            ],
            [
                'unique_email_template_code' => 2,
                'tmp_page_info' => 'This email contains the necessary instructions When user change their password and links to update your password securely. Your security is important to us.',
                'email_template_name' => 'Change password',
                'category_id' => 3,
                'email_subject' => 'Change password',
                'email_trigger' => 'Employee wants to change the password',
                'email_description' => 'Change password',
                'is_active' => 1,
                'email_content' => "<p>Dear <strong>[Employee_Name] , </strong></p><p><br></p><p><strong>Congratulations on being accepted at $companyName! We are excited to have you on our team. You are now on the top sales team in the U.S.A. Now it\'s time to represent.</strong></p><p><br></p><p><strong>Here are your payroll login credentials to login to Sequifi along with the login link.</strong></p><p><br></p><p><strong> [System_Login_Link] </strong></p><p><br></p><p>Username - <strong> [Employee_User_Name] </strong></p><p><br></p><p>Password - <strong> [Employee_User_Password] </strong></p>",
            ],
            [
                'unique_email_template_code' => 3,
                'tmp_page_info' => 'This email will automatically generate and sent to user when their current pay stub will be available. It provides access to view your up-to-date pay information and financial details',
                'email_template_name' => 'Current Pay Stub',
                'category_id' => 3,
                'email_subject' => 'Current Pay Stub',
                'email_trigger' => 'Admin finalize payroll',
                'email_description' => 'Current Pay Stub',
                'is_active' => 1,
                'email_content' => '<p>Dear <strong>[Employee_Name] , </strong></p>',
            ],
            [
                'unique_email_template_code' => 4,
                'tmp_page_info' => 'This email will sent to the Lead when a new lead is added to our system. It provides essential information and details about the newly added lead for reference and action.',
                'email_template_name' => 'Lead added',
                'category_id' => 3,
                'email_subject' => 'Lead added',
                'email_trigger' => 'A new lead added',
                'email_description' => 'Lead added',
                'is_active' => 1,
                'email_content' => '<p>Dear <strong>[Employee_Name] , </strong></p>',
            ],
            [
                'unique_email_template_code' => 5,
                'tmp_page_info' => 'This email will be sent to the user when a modification is made to their profile information. It provides details regarding the specific changes and confirms that your profile has been updated in our system to ensure accuracy and security.',
                'email_template_name' => 'Profile changes',
                'category_id' => 3,
                'email_subject' => 'Profile changes',
                'email_trigger' => 'Any changes in profile or employment Package',
                'email_description' => 'Profile changes',
                'is_active' => 1,
                'email_content' => '<p>Dear <strong>[Employee_Name] , </strong></p>',
            ],
            [
                'unique_email_template_code' => 6,
                'tmp_page_info' => "This email template to send a formal notice to address specific concerns regarding member's recent actions or behavior in the workplace. It aims to bring attention to the issue, provide guidance on corrective measures, and promote a more positive work environment.",
                'email_template_name' => 'Warning to employee for bad actions',
                'category_id' => 3,
                'email_subject' => 'Warning to employee for bad actions',
                'email_trigger' => 'Warning to employee for bad actions',
                'email_description' => 'Warning to employee for bad actions',
                'is_active' => 1,
                'email_content' => '<p>Dear <strong>[Employee_Name] , </strong></p>',
            ],
            [
                'unique_email_template_code' => 7,
                'tmp_page_info' => "This email template to send a formal notice to address specific concerns regarding member's recent actions or behavior in the workplace. It aims to bring attention to the issue, provide guidance on corrective measures, and promote a more positive work environment.",
                'email_template_name' => 'Forgot password',
                'category_id' => 3,
                'email_subject' => 'Forgot Password',
                'email_trigger' => 'Forgot Password',
                'email_description' => 'Forgot Password',
                'is_active' => 1,
                'email_content' => "<p><strong> [Company_Name] </strong></p><p><strong>[Employee_Name] </strong></p><p></p><p></p><p><strong style='color: rgb(94, 98, 120);'>[Forgot_Password_Button] </strong></p><p></p><p><strong style='color: rgb(94, 98, 120);'>[Forgot_Password_Link] </strong></p><p></p><p></p><p><strong> [System_Login_Link] </strong></p>",
            ],
            [
                'unique_email_template_code' => 8,
                'tmp_page_info' => '',
                'email_template_name' => 'Offer letter',
                'category_id' => 1,
                'tempate_id' => 1,
                'email_subject' => 'Offer letter',
                'email_trigger' => 'Offer letter',
                'email_description' => 'Offer letter',
                'is_active' => 1,
                'email_content' => '<p> Dear <strong>[Employee_Name] </strong> ,</p><p></p><p>We hope this message finds you well. After careful consideration and thorough discussions, we are pleased to extend an offer for the position of Solar Sales Consultant at <strong>[Company_Name] </strong> . We were impressed by your qualifications, experience, and passion for the solar industry.</p> <p></p><p> Please find attached the official offer letter which details the terms and conditions of your employment. </p> <p> If you have any questions or require any clarifications, feel free to reach out. We hope to welcome you aboard and are looking forward to working together to achieve our shared vision for a sustainable future.</p>',
            ],
        ];

        // Only create templates that don't exist (preserve customizations)
        foreach ($sequi_docs_email_templates as $template) {
            $exists = SequiDocsEmailSettings::where(
                'unique_email_template_code',
                $template['unique_email_template_code']
            )->exists();

            if (!$exists) {
                SequiDocsEmailSettings::create($template);
            }
        }

        // Log that seeder ran independently
        $this->logSeederRun('SequiDocsEmailSettingSeeder', true);
    }
}
