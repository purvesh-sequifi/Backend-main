<?php

use App\Models\CompanyProfile;
use App\Models\DocumentSigner;
use App\Models\DomainSetting;
use App\Models\EmailNotificationSetting;
use App\Models\Envelope;
use App\Models\EnvelopeDocument;
use App\Models\NewSequiDocsDocument;
use App\Models\NewSequiDocsTemplate;
use App\Models\OnboardingEmployeeOverride;
use App\Models\OnboardingEmployeeRedline;
use App\Models\OnboardingEmployees;
use App\Models\OnboardingEmployeeUpfront;
use App\Models\OnboardingEmployeeWages;
use App\Models\OnboardingEmployeeWithheld;
use App\Models\OnboardingUserRedline;
use App\Models\PositionCommission;
use App\Models\PositionCommissionDeduction;
use App\Models\PositionCommissionUpfronts;
use App\Models\PositionOverride;
use App\Models\PositionProduct;
use App\Models\PositionReconciliations;
use App\Models\Positions;
use App\Models\PositionWage;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserAgreementHistory;
use App\Models\UserCommissionHistory;
use App\Models\UserIsManagerHistory;
use App\Models\UserManagerHistory;
use App\Models\UserOrganizationHistory;
use App\Models\UserOverrideHistory;
use App\Models\UserRedlines;
use App\Models\UserTransferHistory;
use App\Models\UserUpfrontHistory;
use App\Models\UserWagesHistory;
use App\Models\UserWithheldHistory;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

if (! function_exists('uploadS3UsingEnv')) {
    /**
     * Upload a file to S3 using centralized credential provider with IAM role fallback
     *
     * @param  string  $fileName  Name of the file to be stored in S3
     * @param  string|resource  $content  File content or path to file
     * @param  bool  $isFile  Whether $content is a file path (true) or actual content (false)
     * @param  string  $storedBucket  S3 bucket type ('private' or 'public')
     * @return array|string Result of upload with status and URL, or error message
     */
    function uploadS3UsingEnv($fileName, $content, $isFile = false, $storedBucket = 'private')
    {
        // Determine content type based on file extension
        $fileNameArr = explode('.', $fileName);
        switch (strtolower(end($fileNameArr))) {
            case 'png':
                $contentType = 'image/png';
                break;
            case 'jpg':
                $contentType = 'image/jpg';
                break;
            case 'jpeg':
                $contentType = 'image/jpeg';
                break;
            case 'gif':
                $contentType = 'image/gif';
                break;
            case 'doc':
                $contentType = 'application/msword';
                break;
            case 'docx':
                $contentType = 'application/msword';
                break;
            case 'msword':
                $contentType = 'application/msword';
                break;
            case 'zip':
                $contentType = 'application/zip';
                break;
            case 'pdf':
                $contentType = 'application/pdf';
                break;
            default:
                $contentType = 'binary/octet-stream';
                break;
        }

        // Use centralized S3 client factory with IAM role fallback
        $s3Client = createS3Client($storedBucket);
        $s3 = $s3Client['s3'];
        $bucket = $s3Client['bucket'];

        // Upload file to S3 bucket
        try {
            if ($isFile) {
                $result = $s3->putObject([
                    'Bucket' => $bucket,
                    'Key' => $fileName,
                    'SourceFile' => $content,
                    'ContentDisposition' => 'inline',
                    'ContentType' => $contentType,
                ]);
            } else {
                $result = $s3->putObject([
                    'Bucket' => $bucket,
                    'Key' => $fileName,
                    'Body' => $content,
                    'ContentDisposition' => 'inline',
                    'ContentType' => $contentType,
                ]);
            }
            $resultArr = $result->toArray();

            if (! empty($resultArr['ObjectURL'])) {
                if (strpos($resultArr['ObjectURL'], env('app.aws_s3bucket_old_url')) !== false) {
                    $resultArr['ObjectURL'] = str_replace(config('app.aws_s3bucket_old_url'), config('app.aws_s3bucket_url'), $resultArr['ObjectURL']);
                }

                return [
                    'status' => true,
                    'ObjectURL' => $resultArr['ObjectURL'],
                ];
            } else {
                return ['status' => false, 'message' => 'Upload Failed! S3 Object URL not found.'];
            }
        } catch (S3Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }
}


if (! function_exists('checkDomainSetting')) {
    function checkDomainSetting($email)
    {
        $emailId = explode('@', $email);
        $emailSetting = EmailNotificationSetting::where(['company_id' => '1', 'status' => '1'])->first();
        if ($emailSetting && $emailSetting->email_setting_type == 1) {
            return [
                'status' => true,
                'message' => 'Domain setting allowed to send e-mail on this domain.',
            ];
        }

        if (! isset($emailId[1])) {
            return [
                'status' => false,
                'message' => "Domain setting isn't allowed to send e-mail on this domain.",
            ];
        }

        if (DomainSetting::where(['domain_name' => $emailId[1], 'status' => 1])->first()) {
            return [
                'status' => true,
                'message' => 'Domain setting allowed to send e-mail on this domain.',
            ];
        }

        return [
            'status' => false,
            'message' => "Domain setting isn't allowed to send e-mail on this domain.",
        ];
    }
}

if (! function_exists('documentHeaderFooterNew')) {
    function documentHeaderFooterNew($companyProfile, $companyAndOtherStaticImages, $headerAllowed = 1, $footerAllowed = 1)
    {
        $companyEmail = $companyProfile->company_email;
        $businessAddress = $companyProfile->business_address;
        $businessPhone = $companyProfile->business_phone;
        $businessName = $companyProfile->business_name;
        $companyLogo = $companyAndOtherStaticImages['Company_Logo'];
        $companyWebsite = $companyProfile->company_website;

        $headerFooter = '<head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
                p { margin:.35em; }
                @page { margin-top: 5px;margin-bottom: 12px; }
                body { margin: 0px; }
            </style>
        </head>
        <body style="padding-top: 72px; padding-bottom: 22px;margin-bottom: 22px;">';

        if ($headerAllowed) {
            $headerFooter .= '<div style ="position: fixed;top: 0;left: 0;width: 100%;text-align: center;" class="header">';
            if (isS3ImageAccessible($companyLogo)) {
                $headerFooter .= '<img src="'.$companyLogo.'" alt="" style="width: auto; max-height: 70px; margin: 0px auto;">';
            }
            $headerFooter .= '</div>';
        }

        if ($footerAllowed) {
            $footerContent = "<span>$businessName</span> | <span> + $businessPhone </span> | <span> $companyEmail</span> | <span>$businessAddress</span> | <span>$companyWebsite</span>";
            $headerFooter .= '<div style="position: fixed; bottom: 0; left: 0; width: 100%; text-align: center;" class="footer">
                <hr style="margin: 2px;padding: 0px;">
                <p style=" display: flex;justify-content: space-around;padding: 5px 35px;">
                    '.$footerContent.'
                </p>
            </div>';
        }

        $headerFooter .= '
            <!-- Main content -->
            <div id="mainContent" style="padding: 10px 0px;margin-bottom: 10px;padding-bottom: 5px;">
                [Main_Content]
            </div>
        </body>';

        return $headerFooter;
    }
}

if (! function_exists('sequiDocsEmailHeaderAndFooterNew')) {
    function sequiDocsEmailHeaderAndFooterNew($text, $isHeader = 1, $isFooter = 1)
    {
        $header = '';
        $footer = '';
        if ($isHeader) {
            $header = '<tr>
                            <td>
                                <div style="text-align: center;">
                                    <img src="[Company_Logo]" alt="" style="width: 120px; height: 120px; margin: 0px auto;">
                                </div>
                            </td>
                        </tr>';
        }
        if ($isFooter) {
            $footer = '<p style="font-weight: 500;font-size: 13px;line-height: 20px;color: #757575; margin-bottom: 20px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">
                    [Business_Name_With_Other_Details]
                    </p>
                    <p style="font-weight: 500;font-size: 12px;line-height: 20px;color: #9E9E9E; margin-bottom: 20px;font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\'; text-align: center;">© Copyright | <a href="[Company_Email]" target="_blank" style="font-family: -apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #4879FE;font-size: 12px;text-decoration: none;">
                    [Company_Website]
                    </a>| All rights reserved</p>';
        }

        return '<head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
            p{
                margin:.35em;
            }
            </style>
        </head>
        <div style="background-color:#efefef; height: auto; max-width: 650px; margin: 0px auto;">
            <div class="aHl"></div>
            <div tabindex="-1"></div>
            <div class="ii gt">
                <div class="a3s aiL">
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
                                                                '.$header.'
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
                                                                                        <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #616161;font-size: 14px;font-weight: 500;margin-top: 20px; padding-left: 20px; text-align: left;"> '.$text.'</p>
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
                                                                                        '.$footer.'
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
}

if (! function_exists('companyDataResolveKeyNew')) {
    function companyDataResolveKeyNew($companyProfile)
    {
        $companyAndOtherStaticImages = companyAndOtherStaticImagesNew($companyProfile);
        $companyLogo = $companyAndOtherStaticImages['Company_Logo'];
        $sequifiLogoWithName = $companyAndOtherStaticImages['sequifi_logo_with_name'];
        $letterBox = $companyAndOtherStaticImages['letter_box'];
        $businessAddress = $companyProfile->business_address;
        $businessPhone = $companyProfile->business_phone;
        $businessName = $companyProfile->business_name;
        $businessNameWithOtherDetails = "$businessName | + $businessPhone | $businessAddress";

        return [
            'Business_Name' => $businessName, // Direct
            'Business_Name_With_Other_Details' => $businessNameWithOtherDetails, // Direct
            'Company_Name' => $companyProfile->name, // Template
            'Company_Address' => $companyProfile->business_address, // Template
            'Company_Email' => $companyProfile->company_email, // Direct
            'Company_Phone' => $companyProfile->business_phone, // Template
            'Company_Website' => $companyProfile->company_website, // Direct
            'Company_Logo' => $companyLogo, // Direct
            'Letter_Box' => $letterBox, // Direct
            'sequifi_logo_with_name' => $sequifiLogoWithName, // Direct
            'Document_Type' => 'Document', // Direct
        ];
    }
}

if (! function_exists('companyAndOtherStaticImagesNew')) {
    function companyAndOtherStaticImagesNew($companyProfile)
    {
        return [
            // 'defaultCompanyImage' => env('S3_BUCKET_PUBLIC_URL') . '/public_images/defaultCompanyImage.png',
            // 'header_image' => env('S3_BUCKET_PUBLIC_URL') . '/public_images/header-img.png',
            'Company_Logo' => config('app.aws_s3bucket_url').'/'.config('app.domain_name').'/'.$companyProfile->logo,
            'sequifi_logo_with_name' => config('app.aws_s3bucket_url').'/public_images/sequifi-logo.png',
            'letter_box' => config('app.aws_s3bucket_url').'/public_images/letter-box.png',
            // 'sequifiLogo' => env('S3_BUCKET_PUBLIC_URL') . '/public_images/sequifiLogo.png',
        ];
    }
}

if (! function_exists('resolveDocumentsContent')) {
    function resolveDocumentsContent($htmlString, $template, $userData, $authUserData, $companyProfile, $isOnboarding = null, $request = null, $useRequest = false, $onlySmartField = false)
    {
        $companyStaticImages = companyAndOtherStaticImagesNew($companyProfile);
        if ($useRequest) {
            $userDataResolveKey = $request->all();
            $compensationPlan = $request->Compensation_Plan;
            $userDataResolveKey = filterKeyForTemplate($userDataResolveKey);
            $htmlString = str_replace('[Compensation_Plan]', $compensationPlan, $htmlString);

            $companyLogoIs = null;
            if (isset($userDataResolveKey['Company_Logo']) && ! empty($userDataResolveKey['Company_Logo']) && checkImageFromUrl($userDataResolveKey['Company_Logo'])) {
                $companyLogoIs = '<img src="'.$userDataResolveKey['Company_Logo'].'" style="max-height: 50px; height: auto; max-width: 100%; display: inline-block; vertical-align: middle; margin-top: 5px; margin-bottom: 5px;">';
            } else {
                $companyLogo = $companyStaticImages['Company_Logo'];
                if (isS3ImageAccessible($companyLogo)) {
                    $companyLogoIs = '<img src="'.$companyLogo.'" style="max-height: 50px; height: auto; max-width: 100%; display: inline-block; vertical-align: middle; margin-top: 5px; margin-bottom: 5px;">';
                }
            }
            $htmlString = str_replace('[Company_Logo]', $companyLogoIs, $htmlString);

            // RESOLVE COMPANY DATA IN DOCUMENT
            foreach ($userDataResolveKey as $key => $value) {
                if ($value != 'emails' && $value != 'email') {
                    $htmlString = str_replace('['.$key.']', $value, $htmlString);
                }
            }

            $companyDataResolveKey = companyDataResolveKeyNew($companyProfile);
            $companyDataResolveKey = filterKeyForCompanyTemplate($request->all(), $companyDataResolveKey);
            // RESOLVE COMPANY DATA IN DOCUMENT
            foreach ($companyDataResolveKey as $key => $value) {
                if ($value != 'emails' && $value != 'email') {
                    $htmlString = str_replace('['.$key.']', $value, $htmlString);
                }
            }

            // RESOLVE SMART TEXT IN DOCUMENT
            foreach ($request->all() as $key => $value) {
                if ($value != 'emails' && $value != 'email' && ! is_array($value)) {
                    $htmlString = str_replace('['.$key.']', $value, $htmlString);
                }
            }
        } else {
            if ($isOnboarding) {
                $userDataResolveKey = resolveOnBoardingUserDataContent($userData, $authUserData);
            } else {
                $userDataResolveKey = resolveUserDataContent($userData, $authUserData);
            }

            $html = createTablesForDocument($userDataResolveKey);
            $userDataResolveKey = array_merge($userDataResolveKey, $html);

            $companyLogoIs = null;
            $companyDataResolveKey = companyDataResolveKeyNew($companyProfile);
            $companyLogo = $companyDataResolveKey['Company_Logo'];
            if (isS3ImageAccessible($companyLogo)) {
                $companyLogoIs = '<img src="'.$companyLogo.'" style="max-height: 50px; height: auto; max-width: 100%; display: inline-block; vertical-align: middle; margin-top: 5px; margin-bottom: 5px;">';
            }
            $htmlString = str_replace('[Company_Logo]', $companyLogoIs, $htmlString);

            // RESOLVE COMPANY DATA IN DOCUMENT
            foreach ($companyDataResolveKey as $key => $value) {
                if ($value != 'emails' && $value != 'email') {
                    $htmlString = str_replace('['.$key.']', $value, $htmlString);
                }
            }

            $compensationPlan = resolveCompensationDataContent($userDataResolveKey);
            $userDataResolveKey = filterKeyForTemplate($userDataResolveKey);
            $htmlString = str_replace('[Compensation_Plan]', $compensationPlan, $htmlString);

            // RESOLVE USER DATA IN DOCUMENT
            foreach ($userDataResolveKey as $key => $value) {
                if ($value != 'emails' && $value != 'email') {
                    $htmlString = str_replace('['.$key.']', $value, $htmlString);
                }
            }

            if ($onlySmartField) {
                foreach ($request->all() as $key => $value) {
                    if ($value != 'emails' && $value != 'email' && ! is_array($value)) {
                        $htmlString = str_replace($key, $value, $htmlString);
                    }
                }
            }
        }

        $pageBreak = "<p style='page-break-before: always;'><br></p>";
        $htmlString = str_replace('[Page_Break]', $pageBreak, $htmlString);

        // content with header and footer
        $headerAllowed = $template->is_header;
        $footerAllowed = $template->is_footer;
        $headerFooter = documentHeaderFooterNew($companyProfile, $companyStaticImages, $headerAllowed, $footerAllowed);
        $headerFooter = str_replace('[Main_Content]', $htmlString, $headerFooter);

        return $headerFooter;
    }
}

if (! function_exists('checkImageFromUrl')) {
    function checkImageFromUrl($url)
    {
        $headers = @get_headers($url, 1);

        if ($headers && isset($headers['Content-Type'])) {
            $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];

            return strpos($contentType, 'image/') === 0;
        }

        return false;
    }
}

if (! function_exists('resolveUserDataContent')) {
    function resolveUserDataContent($userData, $authUserData = null)
    {
        $userHistoryData = [];
        if (! empty($userData)) {
            $user = User::with('team', 'additionalDetail', 'additionalRecruiterOne', 'additionalRecruiterTwo')->find($userData->id);
            if ($user) {
                $userId = $user->id;
                $effectiveDate = date('Y-m-d');
                $companyProfile = CompanyProfile::first();
                $userOrganization = UserOrganizationHistory::with('subPositionId')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $userOrganization) {
                    $userOrganization = UserOrganizationHistory::with('subPositionId')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }

                $position = Positions::withoutGlobalScope('notSuperAdmin')->with('payFrequency')->where('id', $userOrganization?->sub_position_id)->first();
                $corePositions = [];
                if ($position?->is_selfgen == '1') {
                    $corePositions = [2, 3, null];
                } elseif ($position?->is_selfgen == '2' || $position?->is_selfgen == '3') {
                    $corePositions = [$position?->is_selfgen];
                } elseif ($position?->is_selfgen == '0') {
                    $corePositions = [2];
                }

                $redlineArray = [];
                foreach ($corePositions as $corePosition) {
                    $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '<=', $effectiveDate)->orderBy('start_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $redLineHistory) {
                        $redLineHistory = UserRedlines::where(['user_id' => $userId, 'core_position_id' => $corePosition])->where('start_date', '>=', $effectiveDate)->orderBy('start_date', 'ASC')->orderBy('id', 'DESC')->first();
                    }

                    if ($redLineHistory) {
                        $positionName = null;
                        if ($corePosition == 2) {
                            $positionName = 'Closer';
                        } elseif ($corePosition == 3) {
                            $positionName = 'Setter';
                        } elseif (empty($corePosition)) {
                            $positionName = 'Self-Gen';
                        }

                        $redlineArray[] = [
                            'position' => $positionName ?? '',
                            'redline' => $redLineHistory->redline ?? 0,
                            'redline_type' => $redLineHistory->redline_type ?? '',
                        ];
                    }
                }

                $positionProducts = PositionProduct::where(['position_id' => $userOrganization?->sub_position_id])->where('effective_date', '<=', $effectiveDate)->first();
                if ($positionProducts) {
                    $positionProducts = PositionProduct::with('productName')->where(['position_id' => $userOrganization?->sub_position_id, 'effective_date' => $positionProducts->effective_date])->get();
                } else {
                    $positionProducts = PositionProduct::with('productName')->where(['position_id' => $userOrganization?->sub_position_id])->whereNull('effective_date')->get();
                }

                $productArray = [];
                $commissionArray = [];
                $upFrontArray = [];
                $withHeldArray = [];
                $directArray = [];
                $inDirectArray = [];
                $officeArray = [];
                foreach ($positionProducts as $product) {
                    $positionCommission = PositionCommission::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($positionCommission) {
                        $positionCommissions = PositionCommission::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'commission_status' => 1, 'effective_date' => $positionCommission->effective_date])->get();
                    } else {
                        $positionCommissions = PositionCommission::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'commission_status' => 1])->whereNull('effective_date')->get();
                    }

                    foreach ($positionCommissions as $positionCommission) {
                        $productId = $positionCommission->product_id;
                        $corePositionId = $positionCommission->core_position_id;
                        $userCommission = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('commission_effective_date', '<=', $effectiveDate)->orderBy('commission_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $userCommission) {
                            $userCommission = UserCommissionHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('commission_effective_date', '>=', $effectiveDate)->orderBy('commission_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                        }

                        if ($userCommission) {
                            $positionName = null;
                            if ($corePositionId == 2) {
                                $positionName = 'Closer';
                            } elseif ($corePositionId == 3) {
                                $positionName = 'Setter';
                            } elseif (empty($corePositionId)) {
                                $positionName = 'Self-Gen';
                            }

                            $userCommissionValue = number_format($userCommission?->commission ?? 0, 2, '.', ',');
                            $userCommissionType = $userCommission?->commission_type;
                            if (trim($userCommissionType) == 'percent') {
                                $userCommissionType = '%';
                            } else {
                                $userCommissionValue = '$ '.$userCommissionValue;
                                if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                    if ($userCommissionType == 'per kw') {
                                        $userCommissionType = 'per sq ft';
                                    }
                                }
                            }

                            if ($userCommission?->commission && $userCommission?->commission_type) {
                                $commissionArray[$productId][] = [
                                    'position' => $positionName,
                                    'product' => $product?->productName?->name ?? '',
                                    'commission' => $userCommissionValue,
                                    'commission_type' => $userCommissionType,
                                ];
                            }
                        }
                    }

                    $positionUpfront = PositionCommissionUpfronts::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($positionUpfront) {
                        $positionUpFronts = PositionCommissionUpfronts::with('milestoneTrigger')->where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'upfront_status' => 1, 'effective_date' => $positionUpfront->effective_date])->get();
                    } else {
                        $positionUpFronts = PositionCommissionUpfronts::with('milestoneTrigger')->where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'upfront_status' => 1])->whereNull('effective_date')->get();
                    }

                    foreach ($positionUpFronts as $positionUpFront) {
                        $productId = $positionUpFront->product_id;
                        $corePositionId = $positionUpFront->core_position_id;
                        $schemaId = $positionUpFront->milestone_schema_trigger_id;
                        $userUpFront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId, 'milestone_schema_trigger_id' => $schemaId])->where('upfront_effective_date', '<=', $effectiveDate)->orderBy('upfront_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $userUpFront) {
                            $userUpFront = UserUpfrontHistory::where(['user_id' => $userId, 'product_id' => $productId, 'core_position_id' => $corePositionId])->where('upfront_effective_date', '>=', $effectiveDate)->orderBy('upfront_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                        }

                        if ($userUpFront) {
                            $positionName = null;
                            if ($corePositionId == 2) {
                                $positionName = 'Closer';
                            } elseif ($corePositionId == 3) {
                                $positionName = 'Setter';
                            } elseif (empty($corePositionId)) {
                                $positionName = 'Self-Gen';
                            }

                            $triggerName = $positionUpFront?->milestoneTrigger?->name;
                            $userUpFrontValue = number_format($userUpFront?->upfront_pay_amount ?? 0, 2, '.', ',');
                            $userUpFrontType = $userUpFront?->upfront_sale_type;
                            if (trim($userUpFrontType) == 'percent') {
                                $userUpFrontType = '%';
                            } else {
                                $userUpFrontValue = '$ '.$userUpFrontValue;
                                if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                    if ($userUpFrontValue == 'per kw') {
                                        $userUpFrontValue = 'per sq ft';
                                    }
                                }
                            }

                            if ($userUpFront?->upfront_pay_amount && $userUpFront?->upfront_sale_type) {
                                $upFrontArray[$productId][] = [
                                    'position' => $positionName,
                                    'product' => $product?->productName?->name ?? '',
                                    'trigger' => $triggerName,
                                    'upfront' => $userUpFrontValue,
                                    'upfront_type' => $userUpFrontType,
                                ];
                            }
                        }
                    }

                    $positionOverride = PositionOverride::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if ($positionOverride) {
                        $positionOverrides = PositionOverride::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'status' => 1, 'effective_date' => $positionOverride->effective_date])->get();
                    } else {
                        $positionOverrides = PositionOverride::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'status' => 1])->whereNull('effective_date')->get();
                    }

                    foreach ($positionOverrides as $positionOverride) {
                        $productId = $positionOverride->product_id;
                        $override = UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '<=', $effectiveDate)->orderBy('override_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $override) {
                            $override = UserOverrideHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('override_effective_date', '>=', $effectiveDate)->orderBy('override_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                        }

                        if ($override) {
                            if ($positionOverride->override_id == 1) {
                                $userOverrideValue = number_format($override?->direct_overrides_amount ?? 0, 2, '.', ',');
                                $userOverrideType = $override?->direct_overrides_type;
                                if (trim($userOverrideType) == 'percent') {
                                    $userOverrideType = '%';
                                } else {
                                    $userOverrideValue = '$ '.$userOverrideValue;
                                    if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                        if ($userOverrideType == 'per kw') {
                                            $userOverrideType = 'per sq ft';
                                        }
                                    }
                                }

                                if ($override?->direct_overrides_amount && $override?->direct_overrides_type) {
                                    $directArray[$productId][] = [
                                        'product' => $product?->productName?->name ?? '',
                                        'override_id' => $positionOverride->override_id,
                                        'override_name' => 'Direct',
                                        'override_value' => $userOverrideValue,
                                        'override_type' => $userOverrideType,
                                    ];
                                }
                            } elseif ($positionOverride->override_id == 2) {
                                $userOverrideValue = number_format($override?->indirect_overrides_amount ?? 0, 2, '.', ',');
                                $userOverrideType = $override?->indirect_overrides_type;
                                if (trim($userOverrideType) == 'percent') {
                                    $userOverrideType = '%';
                                } else {
                                    $userOverrideValue = '$ '.$userOverrideValue;
                                    if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                        if ($userOverrideType == 'per kw') {
                                            $userOverrideType = 'per sq ft';
                                        }
                                    }
                                }

                                if ($override?->indirect_overrides_amount && $override?->indirect_overrides_type) {
                                    $inDirectArray[$productId][] = [
                                        'product' => $product?->productName?->name ?? '',
                                        'override_id' => $positionOverride->override_id,
                                        'override_name' => 'InDirect',
                                        'override_value' => $userOverrideValue,
                                        'override_type' => $userOverrideType,
                                    ];
                                }
                            } elseif ($positionOverride->override_id == 3) {
                                $userOverrideValue = number_format($override?->office_overrides_amount ?? 0, 2, '.', ',');
                                $userOverrideType = $override?->office_overrides_type;
                                if (trim($userOverrideType) == 'percent') {
                                    $userOverrideType = '%';
                                } else {
                                    $userOverrideValue = '$ '.$userOverrideValue;
                                    if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                        if ($userOverrideType == 'per kw') {
                                            $userOverrideType = 'per sq ft';
                                        }
                                    }
                                }

                                if ($override?->office_overrides_amount && $override?->office_overrides_type) {
                                    $officeArray[$productId][] = [
                                        'product' => $product?->productName?->name ?? '',
                                        'override_id' => $positionOverride->override_id,
                                        'override_name' => 'Office',
                                        'override_value' => $userOverrideValue,
                                        'override_type' => $userOverrideType,
                                    ];
                                }
                            }
                        }
                    }

                    $positionSettlement = PositionReconciliations::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'status' => 1])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $positionSettlement) {
                        $positionSettlement = PositionReconciliations::where(['position_id' => $userOrganization?->sub_position_id, 'product_id' => $product->product_id, 'status' => 1])->whereNull('effective_date')->first();
                    }

                    if ($positionSettlement) {
                        $productId = $positionCommission->product_id;
                        $userWithHeld = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '<=', $effectiveDate)->orderBy('withheld_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                        if (! $userWithHeld) {
                            $userWithHeld = UserWithheldHistory::where(['user_id' => $userId, 'product_id' => $productId])->where('withheld_effective_date', '>=', $effectiveDate)->orderBy('withheld_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                        }

                        if ($userWithHeld) {
                            $userWithHeldValue = number_format($userWithHeld?->withheld_amount ?? 0, 2, '.', ',');
                            $userWithHeldType = $userWithHeld?->withheld_type;
                            if (trim($userWithHeldType) == 'percent') {
                                $userWithHeldType = '%';
                            } else {
                                $userWithHeldValue = '$ '.$userWithHeldValue;
                                if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                    if ($userWithHeldType == 'per kw') {
                                        $userWithHeldType = 'per sq ft';
                                    }
                                }
                            }

                            if ($userWithHeld?->withheld_amount && $userWithHeld?->withheld_type) {
                                $withHeldArray[$productId][] = [
                                    'product' => $product?->productName?->name ?? '',
                                    'withheld_value' => $userWithHeldValue,
                                    'withheld_type' => $userWithHeldType,
                                ];
                            }
                        }
                    }

                    $productArray[$product->product_id] = $product?->productName?->name;
                }

                $deductionsArray = [];
                $positionCommissionDeduction = PositionCommissionDeduction::with('costcenter')->where('position_id', $userOrganization?->sub_position_id)->get();
                if (count($positionCommissionDeduction) != 0) {
                    foreach ($positionCommissionDeduction as $deduction) {
                        $deductionAmount = '$'.number_format($deduction->ammount_par_paycheck ?? 0, 2, '.', ',');
                        $deductionsArray[] = [
                            'cost_name' => $deduction->costcenter->name,
                            'amount' => $deductionAmount,
                        ];
                    }
                }

                $manager = UserManagerHistory::with('managerInfo.departmentDetail')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $manager) {
                    $manager = UserManagerHistory::with('managerInfo.departmentDetail')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }

                $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $isManager) {
                    $isManager = UserIsManagerHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }

                $userTransfer = UserTransferHistory::with('office.state')->where(['user_id' => $userId])->where('transfer_effective_date', '<=', $effectiveDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $userTransfer) {
                    $userTransfer = UserTransferHistory::with('office.state')->where(['user_id' => $userId])->where('transfer_effective_date', '>=', $effectiveDate)->orderBy('transfer_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }

                $userWagesHistory = null;
                $positionWage = PositionWage::where(['position_id' => $userOrganization?->sub_position_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($positionWage) {
                    $positionWage = PositionWage::where(['position_id' => $userOrganization?->sub_position_id, 'effective_date' => $positionWage->effective_date, 'wages_status' => 1])->first();
                } else {
                    $positionWage = PositionWage::where(['position_id' => $userOrganization?->sub_position_id, 'wages_status' => 1])->whereNull('effective_date')->first();
                }
                if ($positionWage) {
                    $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                    if (! $userWagesHistory) {
                        $userWagesHistory = UserWagesHistory::where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                    }
                }

                $managerOrganization = UserOrganizationHistory::with('subPositionId')->where(['user_id' => $manager?->manager_id])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $managerOrganization) {
                    $managerOrganization = UserOrganizationHistory::with('subPositionId')->where(['user_id' => $manager?->manager_id])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }

                $recruiterTransfer = UserTransferHistory::with('office.state', 'userInfo.positionDetail')->where(['user_id' => $user?->recruiter_id])->where('transfer_effective_date', '<=', $effectiveDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if (! $recruiterTransfer) {
                    $recruiterTransfer = UserTransferHistory::with('office.state', 'userInfo.positionDetail')->where(['user_id' => $user?->recruiter_id])->where('transfer_effective_date', '>=', $effectiveDate)->orderBy('transfer_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
                }
                $recruiterName = isset($recruiterTransfer->userInfo->first_name) ? $recruiterTransfer->userInfo->first_name.' '.$recruiterTransfer->userInfo->last_name : '';
                $userAgreement = UserAgreementHistory::where(['user_id' => $userId])->first();

                $additionalRecruiter = null;
                if (isset($user->additionalRecruiterOne)) {
                    $additionalRecruiter = $user->additionalRecruiterOne?->first_name.' '.$user->additionalRecruiterOne?->last_name;
                }

                $additionalRecruiter2 = null;
                if (isset($user->additionalRecruiterTwo)) {
                    $additionalRecruiter2 = $user->additionalRecruiterTwo?->first_name.' '.$user->additionalRecruiterTwo?->last_name;
                }

                // MANAGER DATA
                $userHistoryData['Employee_Manager_Name'] = $manager?->managerInfo ? $manager?->managerInfo?->first_name.' '.$manager?->managerInfo?->last_name : null;
                $userHistoryData['Employee_Manager_Position'] = $managerOrganization?->subPositionId?->position_name;
                $userHistoryData['Employee_Manager_Department'] = $manager?->managerInfo?->departmentDetail?->name;
                $userHistoryData['Sender_Name'] = $authUserData?->first_name.' '.$authUserData?->last_name;
                $userHistoryData['Current_Date'] = date('m/d/Y', strtotime(date('Y-m-d')));

                // EMPLOYEE WAGES DATA
                $userHistoryData['Wage_Type'] = isset($userWagesHistory->pay_type) ? $userWagesHistory->pay_type : null;
                $userHistoryData['Pay_Rate'] = isset($userWagesHistory->pay_rate) ? '$'.number_format($userWagesHistory->pay_rate ?? 0, 2, '.', ',') : null;
                $userHistoryData['PTO_Hours'] = isset($userWagesHistory->pto_hours) ? $userWagesHistory->pto_hours : null;
                $userHistoryData['Unused_PTO'] = isset($userWagesHistory->unused_pto_expires) ? $userWagesHistory->unused_pto_expires : null;
                $userHistoryData['Overtime_Rate'] = isset($userWagesHistory->overtime_rate) ? $userWagesHistory->overtime_rate : null;
                $userHistoryData['Expected_Weekly_Hours'] = isset($userWagesHistory->expected_weekly_hours) ? $userWagesHistory->expected_weekly_hours : null;

                // EMPLOYEE DATA
                $userHistoryData['Employee_Name'] = $user->first_name.' '.$user->last_name;
                $userHistoryData['Employee_ID'] = $user->employee_id;
                $userHistoryData['Employee_first_name'] = $user->first_name;
                $userHistoryData['Employee_Address'] = $user->home_address;
                $userHistoryData['Employee_Position'] = $userOrganization?->subPositionId?->position_name;
                $userHistoryData['Employee_SSN'] = $user->business_ein;
                $userHistoryData['Office_Name'] = $userTransfer?->office?->office_name ?? 'NA';
                $userHistoryData['Office_Location'] = $user?->office?->state?->name ?? 'NA';
                $userHistoryData['Employee_Is_Manager'] = $isManager?->is_manager ? 'Yes' : 'No';
                $userHistoryData['Employee_Team'] = $user?->team?->team_name;
                $userHistoryData['Recruiter_Name'] = $recruiterName;
                $userHistoryData['Additional_Recruiter1_Name'] = $additionalRecruiter;
                $userHistoryData['Additional_Recruiter2_Name'] = $additionalRecruiter2;

                // COMPENSATION PLAN
                $userHistoryData['commission_data'] = $commissionArray;
                $userHistoryData['redline_data'] = $redlineArray;
                $userHistoryData['upfront_data'] = $upFrontArray;
                $userHistoryData['direct_override_data'] = $directArray;
                $userHistoryData['in_direct_override_data'] = $inDirectArray;
                $userHistoryData['office_override_data'] = $officeArray;
                $userHistoryData['withholding_data'] = $withHeldArray;
                $userHistoryData['deductions_data'] = $deductionsArray;

                // EMPLOYEE AGREEMENT DATA
                $userHistoryData['Bonus_amount'] = isset($userAgreement->hiring_bonus_amount) ? '$ '.number_format($userAgreement->hiring_bonus_amount ?? 0, 2, '.', ',') : 'NA';
                $userHistoryData['Bonus_Pay_Date'] = (isset($userAgreement->date_to_be_paid) && ! empty($userAgreement->date_to_be_paid)) ? $userAgreement->date_to_be_paid : 'NA';
                $userHistoryData['start_date'] = (isset($userAgreement->period_of_agreement) && ! empty($userAgreement->period_of_agreement)) ? date('m/d/Y', strtotime($userAgreement->period_of_agreement)) : 'NA';
                $userHistoryData['end_date'] = (isset($userAgreement->end_date) && ! empty($userAgreement->end_date)) ? date('m/d/Y', strtotime($userAgreement->end_date)) : 'NA';
                $userHistoryData['probation_period'] = (isset($userAgreement->probation_period) && $userAgreement->probation_period != 'None') ? $userAgreement->probation_period.' Days' : 'NA';

                // PRODUCT DATA
                $userHistoryData['employee_products'] = $productArray;
            }
        }

        return $userHistoryData;
    }
}

if (! function_exists('resolveOnBoardingUserDataContent')) {
    function resolveOnBoardingUserDataContent($userData, $authUserData = null)
    {
        $userHistoryData = [];
        if (! empty($userData)) {
            $user = OnboardingEmployees::with('positionDetail', 'office.state', 'managerDetail.departmentDetail', 'managerDetail.positionDetail', 'recruiter', 'teamsDetail', 'additionalRecruiter1', 'additionalRecruiter2')->find($userData->id);
            if ($user) {
                $userId = $user->id;
                $companyProfile = CompanyProfile::first();

                $redlineArray = [];
                $redLineHistories = OnboardingEmployeeRedline::where(['user_id' => $userId])->get();
                foreach ($redLineHistories as $redLineHistory) {
                    $positionName = null;
                    if ($redLineHistory->core_position_id == 2) {
                        $positionName = 'Closer';
                    } elseif ($redLineHistory->core_position_id == 3) {
                        $positionName = 'Setter';
                    } elseif (empty($redLineHistory->core_position_id)) {
                        $positionName = 'Self-Gen';
                    }

                    $redlineArray[] = [
                        'position' => $positionName ?? '',
                        'redline' => $redLineHistory->redline ?? 0,
                        'redline_type' => $redLineHistory->redline_type ?? '',
                    ];
                }

                $productArray = [];
                $commissionArray = [];
                $userCommissions = OnboardingUserRedline::with('product')->where(['user_id' => $userId])->get();
                foreach ($userCommissions as $userCommission) {
                    $positionName = null;
                    if ($userCommission->core_position_id == 2) {
                        $positionName = 'Closer';
                    } elseif ($userCommission->core_position_id == 3) {
                        $positionName = 'Setter';
                    } elseif (empty($userCommission->core_position_id)) {
                        $positionName = 'Self-Gen';
                    }

                    $userCommissionValue = number_format($userCommission?->commission ?? 0, 2, '.', ',');
                    $userCommissionType = $userCommission?->commission_type;
                    if (trim($userCommissionType) == 'percent') {
                        $userCommissionType = '%';
                    } else {
                        $userCommissionValue = '$ '.$userCommissionValue;
                        if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                            if ($userCommissionType == 'per kw') {
                                $userCommissionType = 'per sq ft';
                            }
                        }
                    }

                    if ($userCommission?->commission && $userCommission?->commission_type) {
                        $commissionArray[$userCommission->product_id][] = [
                            'position' => $positionName,
                            'product' => $userCommission?->product?->name ?? '',
                            'commission' => $userCommissionValue,
                            'commission_type' => $userCommissionType,
                        ];
                    }
                    $productArray[$userCommission?->product_id] = $userCommission?->product?->name;
                }

                $upFrontArray = [];
                $userUpFronts = OnboardingEmployeeUpfront::with('product', 'milestoneTrigger')->where(['user_id' => $userId])->get();
                foreach ($userUpFronts as $userUpFront) {
                    $positionName = null;
                    if ($userUpFront->core_position_id == 2) {
                        $positionName = 'Closer';
                    } elseif ($userUpFront->core_position_id == 3) {
                        $positionName = 'Setter';
                    } elseif (empty($userUpFront->core_position_id)) {
                        $positionName = 'Self-Gen';
                    }

                    $triggerName = $userUpFront?->milestoneTrigger?->name;
                    $userUpFrontValue = number_format($userUpFront?->upfront_pay_amount ?? 0, 2, '.', ',');
                    $userUpFrontType = $userUpFront?->upfront_sale_type;
                    if (trim($userUpFrontType) == 'percent') {
                        $userUpFrontType = '%';
                    } else {
                        $userUpFrontValue = '$ '.$userUpFrontValue;
                        if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                            if ($userUpFrontType == 'per kw') {
                                $userUpFrontType = 'per sq ft';
                            }
                        }
                    }

                    if ($userUpFront?->upfront_pay_amount && $userUpFront?->upfront_sale_type) {
                        $upFrontArray[$userUpFront?->product_id][] = [
                            'position' => $positionName,
                            'product' => $userUpFront?->product?->name ?? '',
                            'trigger' => $triggerName,
                            'upfront' => $userUpFrontValue,
                            'upfront_type' => $userUpFrontType,
                        ];
                    }
                    $productArray[$userUpFront?->product_id] = $userUpFront?->product?->name;
                }

                $directArray = [];
                $inDirectArray = [];
                $officeArray = [];
                $positionOverride = PositionOverride::where(['position_id' => $user?->sub_position_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($positionOverride) {
                    $positionOverrides = PositionOverride::where(['position_id' => $user?->sub_position_id, 'status' => 1, 'effective_date' => $positionOverride->effective_date])->get();
                } else {
                    $positionOverrides = PositionOverride::where(['position_id' => $user?->sub_position_id, 'status' => 1])->whereNull('effective_date')->get();
                }
                foreach ($positionOverrides as $positionOverride) {
                    $productId = $positionOverride->product_id;
                    $override = OnboardingEmployeeOverride::with('product')->where(['user_id' => $userId, 'product_id' => $productId])->first();
                    if ($positionOverride->override_id == 1) {
                        $userOverrideValue = number_format($override?->direct_overrides_amount ?? 0, 2, '.', ',');
                        $userOverrideType = $override?->direct_overrides_type;
                        if (trim($userOverrideType) == 'percent') {
                            $userOverrideType = '%';
                        } else {
                            $userOverrideValue = '$ '.$userOverrideValue;
                            if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                if ($userOverrideType == 'per kw') {
                                    $userOverrideType = 'per sq ft';
                                }
                            }
                        }

                        if ($override?->direct_overrides_amount && $override?->direct_overrides_type) {
                            $directArray[$override->product_id][] = [
                                'product' => $override?->product?->name ?? '',
                                'override_id' => $positionOverride->override_id,
                                'override_name' => 'Direct',
                                'override_value' => $userOverrideValue,
                                'override_type' => $userOverrideType,
                            ];
                        }
                    } elseif ($positionOverride->override_id == 2) {
                        $userOverrideValue = number_format($override?->indirect_overrides_amount ?? 0, 2, '.', ',');
                        $userOverrideType = $override?->indirect_overrides_type;
                        if (trim($userOverrideType) == 'percent') {
                            $userOverrideType = '%';
                        } else {
                            $userOverrideValue = '$ '.$userOverrideValue;
                            if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                if ($userOverrideType == 'per kw') {
                                    $userOverrideType = 'per sq ft';
                                }
                            }
                        }

                        if ($override?->indirect_overrides_amount && $override?->indirect_overrides_type) {
                            $inDirectArray[$override->product_id][] = [
                                'product' => $override?->product?->name ?? '',
                                'override_id' => $positionOverride->override_id,
                                'override_name' => 'InDirect',
                                'override_value' => $userOverrideValue,
                                'override_type' => $userOverrideType,
                            ];
                        }
                    } elseif ($positionOverride->override_id == 3) {
                        $userOverrideValue = number_format($override?->office_overrides_amount ?? 0, 2, '.', ',');
                        $userOverrideType = $override?->office_overrides_type;
                        if (trim($userOverrideType) == 'percent') {
                            $userOverrideType = '%';
                        } else {
                            $userOverrideValue = '$ '.$userOverrideValue;
                            if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                                if ($userOverrideType == 'per kw') {
                                    $userOverrideType = 'per sq ft';
                                }
                            }
                        }

                        if ($override?->office_overrides_amount && $override?->office_overrides_type) {
                            $officeArray[$override->product_id][] = [
                                'product' => $override?->product?->name ?? '',
                                'override_id' => $positionOverride->override_id,
                                'override_name' => 'Office',
                                'override_value' => $userOverrideValue,
                                'override_type' => $userOverrideType,
                            ];
                        }
                    }

                    $productArray[$override?->product_id] = $override?->product?->name;
                }

                $withHeldArray = [];
                $userWithHelds = OnboardingEmployeeWithheld::with('product')->where(['user_id' => $userId])->get();
                foreach ($userWithHelds as $userWithHeld) {
                    $userWithHeldValue = number_format($userWithHeld?->withheld_amount ?? 0, 2, '.', ',');
                    $userWithHeldType = $userWithHeld?->withheld_type;
                    if (trim($userWithHeldType) == 'percent') {
                        $userWithHeldType = '%';
                    } else {
                        $userWithHeldValue = '$ '.$userWithHeldValue;
                        if ($companyProfile == CompanyProfile::TURF_COMPANY_TYPE) {
                            if ($userWithHeldType == 'per kw') {
                                $userWithHeldType = 'per sq ft';
                            }
                        }
                    }

                    if ($userWithHeld?->withheld_amount && $userWithHeld?->withheld_type) {
                        $withHeldArray[$userWithHeld->product_id][] = [
                            'product' => $userWithHeld?->product?->name ?? '',
                            'withheld_value' => $userWithHeldValue,
                            'withheld_type' => $userWithHeldType,
                        ];
                    }
                    $productArray[$userWithHeld?->product_id] = $userWithHeld?->product?->name;
                }

                $deductionsArray = [];
                $positionCommissionDeduction = PositionCommissionDeduction::with('costcenter')->where('position_id', $user?->sub_position_id)->get();
                if (count($positionCommissionDeduction) != 0) {
                    foreach ($positionCommissionDeduction as $deduction) {
                        $deductionAmount = '$'.number_format($deduction->ammount_par_paycheck ?? 0, 2, '.', ',');
                        $deductionsArray[] = [
                            'cost_name' => $deduction->costcenter->name,
                            'amount' => $deductionAmount,
                        ];
                    }
                }

                $recruiterName = isset($user?->recruiter?->first_name) ? $user?->recruiter?->first_name.' '.$user?->recruiter?->last_name : '';
                $positionWage = PositionWage::where(['position_id' => $user?->sub_position_id])->where('effective_date', '<=', date('Y-m-d'))->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
                if ($positionWage) {
                    $positionWage = PositionWage::where(['position_id' => $user?->sub_position_id, 'effective_date' => $positionWage->effective_date, 'wages_status' => 1])->first();
                } else {
                    $positionWage = PositionWage::where(['position_id' => $user?->sub_position_id, 'wages_status' => 1])->whereNull('effective_date')->first();
                }
                if ($positionWage) {
                    $userWagesHistory = OnboardingEmployeeWages::where(['user_id' => $userId])->first();
                }

                $additionalRecruiter = null;
                if (isset($user->additionalRecruiter1)) {
                    $additionalRecruiter = $user->additionalRecruiter1?->first_name.' '.$user->additionalRecruiter1?->last_name;
                }

                $additionalRecruiter2 = null;
                if (isset($user->additionalRecruiter2)) {
                    $additionalRecruiter2 = $user->additionalRecruiter2?->first_name.' '.$user->additionalRecruiter2?->last_name;
                }

                // MANAGER DATA
                $userHistoryData['Employee_Manager_Name'] = $user?->managerDetail ? $user?->managerDetail?->first_name.' '.$user?->managerDetail?->last_name : null;
                $userHistoryData['Employee_Manager_Position'] = $user?->managerDetail?->positionDetail?->position_name;
                $userHistoryData['Employee_Manager_Department'] = $user?->managerDetail?->departmentDetail?->name;
                $userHistoryData['Sender_Name'] = $authUserData?->first_name.' '.$authUserData?->last_name;
                $userHistoryData['Current_Date'] = date('m/d/Y', strtotime(date('Y-m-d')));

                // EMPLOYEE WAGES DATA
                $userHistoryData['Wage_Type'] = isset($userWagesHistory->pay_type) ? $userWagesHistory->pay_type : null;
                $userHistoryData['Pay_Rate'] = isset($userWagesHistory->pay_rate) ? '$'.number_format($userWagesHistory->pay_rate ?? 0, 2, '.', ',') : null;
                $userHistoryData['PTO_Hours'] = isset($userWagesHistory->pto_hours) ? $userWagesHistory->pto_hours : null;
                $userHistoryData['Unused_PTO'] = isset($userWagesHistory->unused_pto_expires) ? $userWagesHistory->unused_pto_expires : null;
                $userHistoryData['Overtime_Rate'] = isset($userWagesHistory->overtime_rate) ? $userWagesHistory->overtime_rate : null;
                $userHistoryData['Expected_Weekly_Hours'] = isset($userWagesHistory->expected_weekly_hours) ? $userWagesHistory->expected_weekly_hours : null;

                // EMPLOYEE DATA
                $userHistoryData['Employee_Name'] = $user->first_name.' '.$user->last_name;
                $userHistoryData['Employee_ID'] = $user->employee_id;
                $userHistoryData['Employee_first_name'] = $user->first_name;
                $userHistoryData['Employee_Address'] = $user->home_address;
                $userHistoryData['Employee_Position'] = $user?->positionDetail?->position_name;
                $userHistoryData['Employee_SSN'] = null;
                $userHistoryData['Office_Name'] = $user?->office?->office_name ?? 'NA';
                $userHistoryData['Office_Location'] = $user?->office?->state?->name ?? 'NA';
                $userHistoryData['Employee_Is_Manager'] = $user?->is_manager ? 'Yes' : 'No';
                $userHistoryData['Employee_Team'] = $user?->teamsDetail?->team_name;
                $userHistoryData['Recruiter_Name'] = $recruiterName;
                $userHistoryData['Additional_Recruiter1_Name'] = $additionalRecruiter;
                $userHistoryData['Additional_Recruiter2_Name'] = $additionalRecruiter2;

                // COMPENSATION PLAN
                $userHistoryData['commission_data'] = $commissionArray;
                $userHistoryData['redline_data'] = $redlineArray;
                $userHistoryData['upfront_data'] = $upFrontArray;
                $userHistoryData['direct_override_data'] = $directArray;
                $userHistoryData['in_direct_override_data'] = $inDirectArray;
                $userHistoryData['office_override_data'] = $officeArray;
                $userHistoryData['withholding_data'] = $withHeldArray;
                $userHistoryData['deductions_data'] = $deductionsArray;

                // EMPLOYEE AGREEMENT DATA
                $userHistoryData['Bonus_amount'] = isset($user->hiring_bonus_amount) ? '$ '.number_format($user->hiring_bonus_amount ?? 0, 2, '.', ',') : 'NA';
                $userHistoryData['Bonus_Pay_Date'] = (isset($user->date_to_be_paid) && ! empty($user->date_to_be_paid)) ? $user->date_to_be_paid : 'NA';
                $userHistoryData['start_date'] = (isset($user->period_of_agreement_start_date) && ! empty($user->period_of_agreement_start_date)) ? date('m/d/Y', strtotime($user->period_of_agreement_start_date)) : 'NA';
                $userHistoryData['end_date'] = (isset($user->end_date) && ! empty($user->end_date)) ? date('m/d/Y', strtotime($user->end_date)) : 'NA';
                $userHistoryData['probation_period'] = (isset($user->probation_period) && $user->probation_period != 'None') ? $user->probation_period.' Days' : 'NA';

                // PRODUCT DATA
                $userHistoryData['employee_products'] = $productArray;
            }
        }

        return $userHistoryData;
    }
}

if (! function_exists('filterKeyForTemplate')) {
    function filterKeyForTemplate($data)
    {
        $keysToKeep = NewSequiDocsDocument::DOCUMENT_CONTENT_KEY_ARRAY;

        return array_intersect_key($data, array_flip($keysToKeep));
    }
}

if (! function_exists('filterKeyForCompanyTemplate')) {
    function filterKeyForCompanyTemplate($data, $companyDataResolveKey)
    {
        $keysToKeep = NewSequiDocsDocument::COMPANY_CONTENT_KEY_ARRAY;
        foreach ($keysToKeep as $key) {
            if (isset($data[$key])) {
                $companyDataResolveKey[$key] = $data[$key];
            }
        }

        return $companyDataResolveKey;
    }
}

if (! function_exists('createTablesForDocument')) {
    function createTablesForDocument($data)
    {
        $redlineHtml = '';
        $redlineAll = null;
        if (isset($data['redline_data']) && count($data['redline_data']) != 0) {
            foreach ($data['redline_data'] as $redline) {
                $redlineHtml .= '<tr><td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$redline['position'] ?? ''.'</td>';
                $redlineHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">$ '.$redline['redline'] ?? 0 .' '.$redline['redline_type'].'</td>';

                if ($redlineHtml) {
                    $redlineAll = '<table role="presentation" border="1" style="border-collapse: collapse;width: 100%;">
                        <thead>
                            <tr>
                                <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">User Type</th>
                                <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Redline</th>
                            </tr>
                        </thead>
                    <tbody>';
                    $redlineAll .= $redlineHtml;
                    $redlineAll .= '</tbody></table>';
                }
            }
        }

        $commissionHtml = '';
        $upFrontHtml = '';
        $withHeldHtml = '';
        $directHtml = '';
        $inDirectHtml = '';
        $officeHtml = '';
        if (isset($data['commission_data'])) {
            foreach ($data['commission_data'] as $commissions) {
                foreach ($commissions as $commission) {
                    if ($commission['commission'] && $commission['commission_type']) {
                        $commissionHtml .= '<tr><td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$commission['product'] ?? ''.'</td>';
                        $commissionHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$commission['position'].'</td>';
                        $commissionHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$commission['commission'].' '.$commission['commission_type'].'</td></tr>';
                    }
                }
            }
        }

        if (isset($data['upfront_data'])) {
            foreach ($data['upfront_data'] as $milestones) {
                foreach ($milestones as $milestone) {
                    if ($milestone['upfront'] && $milestone['upfront_type']) {
                        $upFrontHtml .= '<tr><td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$milestone['product'] ?? ''.'</td>';
                        $upFrontHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$milestone['position'].'</td>';
                        $upFrontHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$milestone['trigger'].'</td>';
                        $upFrontHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$milestone['upfront'].' '.$milestone['upfront_type'].'</td></tr>';
                    }
                }
            }
        }

        if (isset($data['direct_override_data'])) {
            foreach ($data['direct_override_data'] as $directs) {
                foreach ($directs as $direct) {
                    if ($direct['override_value'] && $direct['override_type']) {
                        $directHtml .= '<tr><td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$direct['product'] ?? ''.'</td>';
                        $directHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$direct['override_name'].'</td>';
                        $directHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$direct['override_value'].' '.$direct['override_type'].'</td></tr>';
                    }
                }
            }
        }

        if (isset($data['in_direct_override_data'])) {
            foreach ($data['in_direct_override_data'] as $inDirects) {
                foreach ($inDirects as $inDirect) {
                    if ($inDirect['override_value'] && $inDirect['override_type']) {
                        $inDirectHtml .= '<tr><td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$inDirect['product'] ?? ''.'</td>';
                        $inDirectHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$inDirect['override_name'].'</td>';
                        $inDirectHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$inDirect['override_value'].' '.$inDirect['override_type'].'</td></tr>';
                    }
                }
            }
        }

        if (isset($data['office_override_data'])) {
            foreach ($data['office_override_data'] as $offices) {
                foreach ($offices as $office) {
                    if ($office['override_value'] && $office['override_type']) {
                        $officeHtml .= '<tr><td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$office['product'] ?? ''.'</td>';
                        $officeHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$office['override_name'].'</td>';
                        $officeHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$office['override_value'].' '.$office['override_type'].'</td></tr>';
                    }
                }
            }
        }

        if (isset($data['withholding_data'])) {
            foreach ($data['withholding_data'] as $withHolds) {
                foreach ($withHolds as $withHold) {
                    if ($withHold['withheld_value'] && $withHold['withheld_type']) {
                        $withHeldHtml .= '<tr><td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$withHold['product'] ?? ''.'</td>';
                        $withHeldHtml .= '<td style="border: 1px solid #000;padding: 8px;text-align: left;">'.$withHold['withheld_value'].' '.$withHold['withheld_type'].'</td></tr>';
                    }
                }
            }
        }

        $commission = null;
        $upfront = null;
        $withHeld = null;
        $direct = null;
        $inDirect = null;
        $office = null;
        if ($commissionHtml) {
            $commission = '<table role="presentation" border="1" style="border-collapse: collapse;width: 100%;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Product</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">User Type</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Commission</th>
                    </tr>
                </thead>
            <tbody>';
            $commission .= $commissionHtml;
            $commission .= '</tbody></table>';
        }
        if ($upFrontHtml) {
            $upfront = '<table role="presentation" border="1" style="border-collapse: collapse;width: 100%;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Product</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">User Type</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Milestone Name</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Milestone Amount</th>
                    </tr>
                </thead>
            <tbody>';
            $upfront .= $upFrontHtml;
            $upfront .= '</tbody></table>';
        }
        if ($withHeldHtml) {
            $withHeld = '<table role="presentation" border="1" style="border-collapse: collapse;width: 100%;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Product</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Withheld</th>
                    </tr>
                </thead>
            <tbody>';
            $withHeld .= $withHeldHtml;
            $withHeld .= '</tbody></table>';
        }
        if ($directHtml) {
            $direct = '<table role="presentation" border="1" style="border-collapse: collapse;width: 100%;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Product</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Override Type</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Override</th>
                    </tr>
                </thead>
            <tbody>';
            $direct .= $directHtml;
            $direct .= '</tbody></table>';
        }
        if ($inDirectHtml) {
            $inDirect = '<table role="presentation" border="1" style="border-collapse: collapse;width: 100%;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Product</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Override Type</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Override</th>
                    </tr>
                </thead>
            <tbody>';
            $inDirect .= $inDirectHtml;
            $inDirect .= '</tbody></table>';
        }
        if ($officeHtml) {
            $office = '<table role="presentation" border="1" style="border-collapse: collapse;width: 100%;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Product</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Override Type</th>
                        <th style="border: 1px solid #000;padding: 8px;text-align: left;background-color: #f2f2f2;">Override</th>
                    </tr>
                </thead>
            <tbody>';
            $office .= $officeHtml;
            $office .= '</tbody></table>';
        }

        $deductions = null;
        if (isset($data['deductions_data']) && count($data['deductions_data']) != 0) {
            foreach ($data['deductions_data'] as $deduction) {
                $deductions .= '<p><strong>'.$deduction['cost_name'].':</strong> '.$deduction['amount'].' </p>';
            }
        }

        return [
            'redline' => $redlineAll,
            'commission' => $commission,
            'upfront_amount' => $upfront,
            'Withholding_Amount' => $withHeld,
            'Direct_Override_Value' => $direct,
            'InDirect_Override_Value' => $inDirect,
            'Office_Override_Value' => $office,
            'deductions' => $deductions,
        ];
    }
}

if (! function_exists('resolveCompensationDataContent')) {
    function resolveCompensationDataContent($data)
    {
        $compensationPlan = '';
        $compensationPlan .= '<div style="background-color:#fafafa; height: auto">
            <div role="presentation" style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;box-sizing:border-box; min-width: 100%;text-align:left">
                <div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;box-sizing:border-box">
                    <div>
                        <div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;box-sizing:border-box;padding:10px 0px 0px">
                            <div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;box-sizing:border-box;padding:20px;background-color:rgb(255,255,255); border-radius: 5px;">
                                <div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;box-sizing:border-box;color:rgb(0,0,0);text-align:left">
                                    <div>
                                        <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;margin-bottom:5px; margin-top:0px;color: #000000;font-size: 14px; text-align: left; margin-bottom: 10px; text-transform: uppercase; margin-left: 5px; font-weight: 600;">
                                            Organization</p>
                                        <table style="width: 100%;">
                                            <tr>
                                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                                    Office State
                                                </td>
                                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                                                '.(isset($data['Office_Location']) ? $data['Office_Location'] : 'NA').'
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                                    Office (Name)
                                                </td>
                                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                                                '.(isset($data['Office_Name']) ? $data['Office_Name'] : 'NA').'
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                                    Is Manager
                                                </td>
                                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                                                '.(isset($data['Employee_Is_Manager']) ? $data['Employee_Is_Manager'] : 'NA').'
                                                </td>
                                            </tr>';

        if (isset($data['Employee_Manager_Name']) && ! empty($data['Employee_Manager_Name'])) {
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Manager
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                '.$data['Employee_Manager_Name'].'
                </td>
            </tr>';
        }

        if (isset($data['Employee_Team']) && ! empty($data['Employee_Team'])) {
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Team
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                '.$data['Employee_Team'].'
                </td>
            </tr>';
        }

        if (isset($data['Recruiter_Name']) && ! empty($data['Recruiter_Name'])) {
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Recruiter
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                '.$data['Recruiter_Name'].'
                </td>
            </tr>';
        }

        if (isset($data['Additional_Recruiter1_Name']) && ! empty($data['Additional_Recruiter1_Name'])) {
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Additional Recruiter
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                '.$data['Additional_Recruiter1_Name'].'
                </td>
            </tr>';
        }
        $compensationPlan .= '</table></div>';

        // WAGES SECTION
        if (isset($data['Wage_Type'])) {
            $compensationPlan .= '<div style="margin-top: 10px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;margin-bottom:5px; margin-top:0px;color: #000000;font-size: 14px;; text-align: left; margin-bottom: 10px; text-transform: uppercase; margin-left: 5px; font-weight: 600;">
                    Wages
                </p>
                <table style="width: 100%;">';
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Pay Type
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                    '.(isset($data['Wage_Type']) ? $data['Wage_Type'] : 'NA').'
                </td>
            </tr>';
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Pay Rate
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                    '.(isset($data['Pay_Rate']) ? $data['Pay_Rate'] : 'NA').'
                </td>
            </tr>';
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    PTO Hours(Paid time off)
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                    '.(isset($data['PTO_Hours']) ? $data['PTO_Hours'] : 'NA').'
                </td>
            </tr>';
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Unused PTO
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                    '.(isset($data['Unused_PTO']) ? $data['Unused_PTO'] : 'NA').'
                </td>
            </tr>';
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Expected weekly hours
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                    '.(isset($data['Expected_Weekly_Hours']) ? $data['Expected_Weekly_Hours'] : 'NA').'
                </td>
            </tr>';
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Overtime rate
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                    '.(isset($data['Overtime_Rate']) ? $data['Overtime_Rate'] : 'NA').'
                </td>
            </tr>';
            $compensationPlan .= '</table></div>';
        }

        // PAY SECTION
        if ((isset($data['redline_data']) && ! empty($data['redline_data'])) || (isset($data['commission_data']) && ! empty($data['commission_data'])) || (isset($data['upfront_data']) && ! empty($data['upfront_data'])) || (isset($data['withholding_data']) && ! empty($data['withholding_data']))) {
            $compensationPlan .=
                '<div style="margin-top: 10px;">
                    <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;margin-bottom:5px; margin-top:0px;color: #000000;font-size: 14px;; text-align: left; margin-bottom: 10px; text-transform: uppercase; margin-left: 5px; font-weight: 600;">
                        Pay
                    </p>
                    <table style="width: 100%;">';
            if (isset($data['redline_data']) && ! empty($data['redline_data'])) {
                foreach ($data['redline_data'] as $redline) {
                    $compensationPlan .= '<tr>
                        <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                            '.($redline['position'] ?? '').' Redline
                        </td>
                        <td colspan="2" style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                        '.($redline['redline'] ?? 0).' '.($redline['redline_type'] ?? '').'
                        </td>
                    </tr>';
                }
            }

            if (isset($data['employee_products']) && count($data['employee_products']) != 0) {
                foreach ($data['employee_products'] as $key => $product) {
                    if ((isset($data['commission_data'][$key]) && ! empty($data['commission_data'][$key])) || (isset($data['upfront_data'][$key]) && ! empty($data['upfront_data'][$key])) || (isset($data['withholding_data'][$key]) && ! empty($data['withholding_data'][$key]))) {
                        $compensationPlan .= '<tr>
                            <td colspan="3" style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464; text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 100%;">
                                '.$product.'
                            </td>
                        </tr>';
                    }

                    if (isset($data['commission_data'][$key])) {
                        foreach ($data['commission_data'][$key] as $commission) {
                            $compensationPlan .= '<tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                    '.$commission['position'].' Commission
                                </td>
                                <td colspan="2" style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                                    '.$commission['commission'].' '.$commission['commission_type'].'
                                </td>
                            </tr>';
                        }
                    }

                    if (isset($data['upfront_data'][$key])) {
                        foreach ($data['upfront_data'][$key] as $milestone) {
                            $compensationPlan .= '<tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                    '.($milestone['position'] ?? '').' Milestone
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 30%;">
                                    '.($milestone['upfront'] ?? 0).' '.($milestone['upfront_type'] ?? '').'
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 35%;">
                                    '.$milestone['trigger'].'
                                </td>
                            </tr>';
                        }
                    }

                    if (isset($data['withholding_data'][$key])) {
                        foreach ($data['withholding_data'][$key] as $withHold) {
                            $compensationPlan .= '<tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                    Withholding
                                </td>
                                <td colspan="2" style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                                    '.$withHold['withheld_value'].' '.$withHold['withheld_type'].'
                                </td>
                            </tr>';
                        }
                    }
                }
            }
            $compensationPlan .= '</table></div>';
        }

        // OVERRIDE SECTION
        if ((isset($data['direct_override_data']) && ! empty($data['direct_override_data'])) || (isset($data['in_direct_override_data']) && ! empty($data['in_direct_override_data'])) || (isset($data['office_override_data']) && ! empty($data['office_override_data']))) {
            $compensationPlan .= '<div style="margin-top: 10px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;margin-bottom:5px; margin-top:0px;color: #000000;font-size: 14px;; text-align: left; margin-bottom: 10px; text-transform: uppercase; margin-left: 5px; font-weight: 600;">
                    Overrides
                </p>
                <table style="width: 100%;">';
            if (isset($data['employee_products']) && count($data['employee_products']) != 0) {
                foreach ($data['employee_products'] as $key => $product) {
                    if ((isset($data['direct_override_data'][$key]) && ! empty($data['direct_override_data'][$key])) || (isset($data['in_direct_override_data'][$key]) && ! empty($data['in_direct_override_data'][$key])) || (isset($data['office_override_data'][$key]) && ! empty($data['office_override_data'][$key]))) {
                        $compensationPlan .= '<tr>
                            <td colspan="2" style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464; text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 100%;">
                                '.$product.'
                            </td>
                        </tr>';
                    }
                    if (isset($data['direct_override_data'][$key])) {
                        foreach ($data['direct_override_data'][$key] as $direct) {
                            $compensationPlan .= '<tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                    '.$direct['override_name'].' Override
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 70%;">
                                    '.$direct['override_value'].' '.$direct['override_type'].'
                                </td>
                            </tr>';
                        }
                    }
                    if (isset($data['in_direct_override_data'][$key])) {
                        foreach ($data['in_direct_override_data'][$key] as $inDirect) {
                            $compensationPlan .= '<tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                    '.$inDirect['override_name'].' Override
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 70%;">
                                    '.$inDirect['override_value'].' '.$inDirect['override_type'].'
                                </td>
                            </tr>';
                        }
                    }
                    if (isset($data['office_override_data'][$key])) {
                        foreach ($data['office_override_data'][$key] as $office) {
                            $compensationPlan .= '<tr>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                                    '.$office['override_name'].' Override
                                </td>
                                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 70%;">
                                    '.$office['override_value'].' '.$office['override_type'].'
                                </td>
                            </tr>';
                        }
                    }
                }
            }
            $compensationPlan .= '</table></div>';
        }

        // DEDUCTION SECTION
        if (isset($data['deductions_data']) && count($data['deductions_data']) != 0) {
            $compensationPlan .= '<div style="margin-top: 10px;">
                <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;margin-bottom:5px; margin-top:0px;color: #000000;font-size: 14px;; text-align: left; margin-bottom: 10px; text-transform: uppercase; margin-left: 5px; font-weight: 600;">
                    Deductions
                </p>
                <table style="width: 100%;">';
            foreach ($data['deductions_data'] as $deduction) {
                $compensationPlan .= '<tr>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                        '.$deduction['cost_name'].'
                    </td>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                        '.$deduction['amount'].'
                    </td>
                </tr>';
            }
            $compensationPlan .= '</table></div>';
        }

        // AGREEMENT SECTION
        $compensationPlan .= '<div style="margin-top: 10px;">
            <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;margin-bottom:5px; margin-top:0px;color: #000000;font-size: 14px;; text-align: left; margin-bottom: 10px; text-transform: uppercase; margin-left: 5px; font-weight: 600;">
                Agreement
            </p>
            <table style="width: 100%;">
                <tr>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                        Probation Period
                    </td>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                        '.$data['probation_period'].'
                    </td>
                </tr>           

                <tr>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                        Agreement Start Date
                    </td>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                        '.$data['start_date'].'
                    </td>
                </tr>

                <tr>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                        Agreement End Date
                    </td>
                    <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                        '.$data['end_date'].'
                    </td>
                </tr>';
        if ($data['Bonus_amount'] > 0) {
            $compensationPlan .= '<tr>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #646464;  text-align: left;  padding-left: 8px !important;  font-size: 14px;  font-weight: 500; padding: 5px; width: 30%;">
                    Bonus
                </td>
                <td style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;background-color: #f7f7f7bf;color: #9b9b9b;text-align: left;padding-left: 8px !important;font-size: 14px;font-weight: 500; padding: 5px;width: 65%;">
                    '.$data['Bonus_amount'].'
                </td>
            </tr>';
        }
        $compensationPlan .= '</table></div>';
        $compensationPlan .= '</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';

        return $compensationPlan;
    }
}

if (! function_exists('prepareEmailData')) {
    function prepareEmailData($template, $reviewLink, $attachmentsList, $companyProfile, $type = 'template', $isTest = true, $user = [], $request = null, $useRequest = false)
    {
        // GET EMAIL SUBJECT AND BODY FROM TEMPLATE
        $subject = $template->email_subject;
        $body = $template->email_content;

        // GET HEADER AND FOOTER
        if ($type == 'offer-letter') {
            if ($attachmentsList) {
                $text = '[Business_Name] has sent an Offer with following documents-';
            } else {
                $text = '[Business_Name] has sent an offer letter';
            }
        } else {
            $text = '[Business_Name] has sent a document';
        }
        $isHeader = $template->is_header;
        $isFooter = $template->is_footer;
        $headerFooter = sequiDocsEmailHeaderAndFooterNew($text, $isHeader, $isFooter);
        $body = str_replace('[Email_Content]', $body, $headerFooter);
        $body = str_replace('[Review_Document_Link]', $reviewLink, $body);

        // HANDLE ATTACHMENTS
        $body = str_replace('[Document_list_is]', $attachmentsList, $body);

        // REPLACE USER DATA PLACEHOLDERS
        $companyDataResolveKey = emailDataResolveKeyNew($companyProfile, $user, $isTest);
        if ($useRequest) {
            $companyDataResolveKey = compareAndReplaceEmailData($request->all(), $companyDataResolveKey);
        }

        foreach ($companyDataResolveKey as $key => $value) {
            if ($value != 'emails' && $value != 'email') {
                $body = str_replace('['.$key.']', $value, $body);
            }
        }

        return [
            'subject' => $subject,
            'template' => $body,
        ];
    }
}

if (! function_exists('compareAndReplaceEmailData')) {
    function compareAndReplaceEmailData($data, $companyDataResolveKey)
    {
        $keysToKeep = NewSequiDocsDocument::EMAIL_CONTENT_KEY_ARRAY;
        foreach ($keysToKeep as $key) {
            if (isset($data[$key])) {
                $companyDataResolveKey[$key] = $data[$key];
            }
        }

        return $companyDataResolveKey;
    }
}

if (! function_exists('emailDataResolveKeyNew')) {
    function emailDataResolveKeyNew($companyProfile, $user = [], $isTest = true)
    {
        $companyAndOtherStaticImages = companyAndOtherStaticImagesNew($companyProfile);
        $companyLogo = $companyAndOtherStaticImages['Company_Logo'];
        $sequifiLogoWithName = $companyAndOtherStaticImages['sequifi_logo_with_name'];
        $letterBox = $companyAndOtherStaticImages['letter_box'];
        $businessAddress = $companyProfile->business_address;
        $businessPhone = $companyProfile->business_phone;
        $businessName = $companyProfile->business_name;
        $businessNameWithOtherDetails = "$businessName | + $businessPhone | $businessAddress";

        $finalArray = [];
        if (! $isTest) {
            $finalArray['Employee_Name'] = $user['first_name'].' '.$user['last_name'];
            $finalArray['Employee_Position'] = $user['position'];
            $finalArray['Office_Name'] = $user['office'];
            $finalArray['Office_Location'] = $user['office_location'] ?? null;
        }

        $finalArray['Business_Name'] = $businessName;
        $finalArray['Company_Name'] = $companyProfile->name;
        $finalArray['Company_Email'] = $companyProfile->company_email;
        $finalArray['Company_Website'] = $companyProfile->company_website;
        $finalArray['Company_Address'] = $companyProfile->business_address;
        $finalArray['Company_Logo'] = $companyLogo;
        $finalArray['Letter_Box'] = $letterBox;
        $finalArray['Document_Type'] = 'Document';
        $finalArray['Business_Name_With_Other_Details'] = $businessNameWithOtherDetails;
        $finalArray['sequifi_logo_with_name'] = $sequifiLogoWithName;

        return $finalArray;
    }
}

if (! function_exists('getUserDataFromUserArray')) {
    function getUserDataFromUserArray($userId, $type = 'user')
    {
        if ($type == 'user') {
            $user = User::find($userId);
            $effectiveDate = date('Y-m-d');
            $userOrganization = UserOrganizationHistory::with('subPositionId')->where(['user_id' => $userId])->where('effective_date', '<=', $effectiveDate)->orderBy('effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userOrganization) {
                $userOrganization = UserOrganizationHistory::with('subPositionId')->where(['user_id' => $userId])->where('effective_date', '>=', $effectiveDate)->orderBy('effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }

            $userTransfer = UserTransferHistory::with('office.state')->where(['user_id' => $userId])->where('transfer_effective_date', '<=', $effectiveDate)->orderBy('transfer_effective_date', 'DESC')->orderBy('id', 'DESC')->first();
            if (! $userTransfer) {
                $userTransfer = UserTransferHistory::with('office.state')->where(['user_id' => $userId])->where('transfer_effective_date', '>=', $effectiveDate)->orderBy('transfer_effective_date', 'ASC')->orderBy('id', 'DESC')->first();
            }

            return [
                'first_name' => $user?->first_name,
                'last_name' => $user?->last_name,
                'position' => $userOrganization?->subPositionId?->position_name,
                'office' => $userTransfer?->office?->office_name ?? 'NA',
                'office_location' => $userTransfer?->office?->state?->name ?? 'NA',
            ];
        } else {
            $user = OnboardingEmployees::with('positionDetail', 'office.state')->find($userId);

            return [
                'first_name' => $user?->first_name,
                'last_name' => $user?->last_name,
                'position' => $user?->positionDetail?->position_name,
                'office' => $user?->office?->office_name ?? 'NA',
                'office_location' => $user?->office?->state?->name ?? 'NA',
            ];
        }
    }
}

if (! function_exists('prepareAttachmentsList')) {
    function prepareAttachmentsList($template, $pdfLink, $companyProfile, $user = null, $authUser = null)
    {
        $attachmentsList = '';
        if (isset($template->document_for_send_with_offer_letter) && count($template->document_for_send_with_offer_letter) == 0) {
            $mandatory = $template?->is_sign_required_for_hire ?? 0;
            $templateCategoryId = $template->categories->id ?? '';
            if ($templateCategoryId == 2 || $templateCategoryId == 101) {
                $attachmentsList .= '';
            } else {
                $attachmentsList .= "<li><a target='_blank' href='".$pdfLink."'>".$template->template_name.'</a>'.($mandatory ? " <span style='color: red'>*</span>" : '').'</li>';
            }

            return $attachmentsList;
        }

        foreach ($template->document_for_send_with_offer_letter as $attachment) {
            // AVOID SENDING POST HIRING DOCUMENTS
            if ($attachment->is_post_hiring_document == 1) {
                continue;
            }

            $mandatory = $attachment->is_sign_required_for_hire;
            if ($attachment->is_document_for_upload == 0) {
                // DOCUMENT IS A TEMPLATE
                $attachmentTemplate = NewSequiDocsTemplate::find($attachment->to_send_template_id);
                if ($attachmentTemplate) {
                    $pdfLink = generatePdfLink($attachmentTemplate, $companyProfile, $user, $authUser);
                    if ($attachment->category_id == 2 || $attachment->category_id == 101) {
                        $attachmentsList .= '';
                    } else {
                        $attachmentsList .= "<li><a target='_blank' href='".$pdfLink."'>".$attachmentTemplate->template_name.'</a>'.($mandatory ? " <span style='color: red'>*</span>" : '').'</li>';
                    }
                }
            } elseif ($attachment->is_document_for_upload == 1) {
                // DOCUMENT IS A MANUAL UPLOAD TYPE
                $docType = $attachment?->upload_document_types;
                if ($docType) {
                    $attachmentsList .= '<li> <b>'.$docType->document_name.'</b> (Document to upload)'.($mandatory ? " <span style='color: red'>*</span>" : '').'</li>';
                }
            }
        }

        return $attachmentsList;
    }
}

if (! function_exists('generatePdfLink')) {
    function generatePdfLink($template, $companyProfile, $user = null, $authUser = null, $request = null, $useRequest = false, $isOnboarding = false, $onlySmartField = false)
    {
        // IF TEMPLATE IS ALREADY A PDF, RETURN ITS PATH
        if ($template->is_pdf == 1) {
            return config('app.aws_s3bucket_url').'/'.config('app.domain_name').'/'.$template->pdf_file_path;
        }

        // OTHERWISE, GENERATE PDF FROM TEMPLATE CONTENT
        $templateContent = $template->template_content;
        $templateName = $template->template_name;
        if (! $templateContent) {
            return 'Template content not found!';
        }

        // GET HEADER AND FOOTER
        $headerAllowed = $template->is_header;
        $footerAllowed = $template->is_footer;
        $companyStaticImages = companyAndOtherStaticImagesNew($companyProfile);
        $headerFooter = documentHeaderFooterNew($companyProfile, $companyStaticImages, $headerAllowed, $footerAllowed);
        $htmlWithHeaderFooter = str_replace('[Main_Content]', $templateContent, $headerFooter);

        // REPLACE PAGE BREAKS
        $pageBreak = '<div style="page-break-before: always;"></div>';
        $htmlWithHeaderFooter = str_replace('[Page_Break]', $pageBreak, $htmlWithHeaderFooter);

        // ADD COMPANY LOGO
        $companyLogo = $companyStaticImages['Company_Logo'];
        $companyLogoHtml = '<img src="'.$companyLogo.'" style="max-height: 50px; height: auto; max-width: 100%; display: inline-block; vertical-align: middle; margin-top: 5px; margin-bottom: 5px;">';
        $htmlWithHeaderFooter = str_replace('[Company_Logo]', $companyLogoHtml, $htmlWithHeaderFooter);

        // RESOLVE TEMPLATE VARIABLES
        $resolvedString = $htmlWithHeaderFooter;
        if ($user || $useRequest) {
            $resolvedString = resolveDocumentsContent($htmlWithHeaderFooter, $template, $user, $authUser, $companyProfile, $isOnboarding, $request, $useRequest, $onlySmartField);
        }

        // GENERATE PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($resolvedString, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdfOutput = $pdf->output();

        // PREPARE FILE PATH
        $generatedTemplate = $templateName.'_'.date('m-d-Y').'_'.time().'.pdf';
        $templatePath = 'template/'.$generatedTemplate;
        $templatePathUrl = config('app.domain_name').'/'.$templatePath;

        // UPLOAD TO S3 USING ENV VARIABLES
        $s3Return = uploadS3UsingEnv($templatePathUrl, $pdfOutput, false, 'public');
        if (isset($s3Return['status']) && $s3Return['status'] == true) {
            return $s3Return['ObjectURL'];
        }

        return 'Uploading onto S3 bucket has failed!';
    }
}

if (! function_exists('documentToUploadEmailTemplate')) {
    function documentToUploadEmailTemplate($recipient = 'external', $review = false)
    {
        $html = '';
        if ($recipient == 'users') {
            $html .= '<p>Dear <strong>[Employee_Name],</strong></p>';
        }
        $emailContent = $html.'<p></p><p>You have to upload some mandatory documents, please login and upload below documents.</p><p></p><p></p>';

        $reviewDocumentButton = '';
        $reviewDocumentLink = '';
        if ($review) {
            $reviewDocumentButton = '<a href="[Review_Document_Link]" target="_blank" style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';background-color: #6078EC; color: #fff;font-size: 14px; font-weight: 500;text-decoration: none; padding: 14px 30px;display: inline-block;margin-top: 25px;border-radius: 6px;min-width: 150px;">Review Document</a>';
            $reviewDocumentLink = '<p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #6C6969;font-size: 14px;font-weight: 600;margin-top: 35px;">Or Click the link below to review and Sign the document</p>
                <a href="[Review_Document_Link]" target="_blank" style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px; margin-top: 20px; color: #4879FE; font-size: 13px; font-weight: 600; display: block; line-height: 30px;">[Review_Document_Link]</a>';
        }

        return '<head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <style>
            p {
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
                                                                            '.$emailContent.'
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
                                                                                        <p style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #616161;font-size: 14px;font-weight: 500;margin-top: 20px; padding-left: 20px; text-align: left;">[Business_Name] has sent an Offer with following documents-</p>
                                                                                        
                                                                                        <ul style="text-align: left;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif,\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Segoe UI Symbol\';margin-bottom: 0px;color: #616161;font-size: 14px;font-weight: 500;margin-bottom: 10px;">
                                                                                            [Document_list_is]
                                                                                        </ul>

                                                                                        '.$reviewDocumentButton.'
                                                                                        </div>
                                                                                        '.$reviewDocumentLink.'
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
}

if (! function_exists('createEnvelope')) {
    function createEnvelope()
    {
        try {
            $folders = [
                'signed_pdfs',
                'unsigned_pdfs',
                'processed_pdf',
                // 'processed_pdf/dktest',
                // 'processed_pdf/e_signed_pdf',
                // 'processed_pdf/form_data_merged_pdf',
            ];

            foreach ($folders as $folder) {
                $folderPath = storage_path('app/'.$folder);
                if (! File::exists($folderPath)) {
                    File::makeDirectory($folderPath, 0777, true);
                }
                File::chmod($folderPath, 0777);
            }

            $envelopeName = Str::ulid();
            $password = generateRandomPassword();

            $envelope = Envelope::create([
                'envelope_name' => $envelopeName,
                'password' => $password['hash_password'],
            ]);

            $envelope->plain_password = $envelope->id.$password['plain_password'];
            $envelope->save();

            return ['success' => true, 'envelope' => $envelope];
        } catch (\Exception $e) {
            return ['success' => false, 'envelope' => $e->getMessage().' '.$e->getLine()];
        }
    }
}

if (! function_exists('addDocumentsInToEnvelope')) {
    function addDocumentsInToEnvelope(int $envelopeId, array $pdfDocDetail)
    {
        $envelope = Envelope::find($envelopeId);
        if (! $envelope) {
            return [
                'status' => false,
                'message' => 'Step 1: Invalid Envelope ID. Failed to process request.',
                'errors' => 'Invalid Envelope ID. Failed to process request.',
            ];
        }

        $validationErrors = validatePdfDocDetails($pdfDocDetail);
        if ($validationErrors) {
            return [
                'status' => false,
                'message' => 'Step 2: Data validation errors',
                'errors' => $validationErrors,
            ];
        }

        // Set envelope expiry from the first document
        if (! empty($pdfDocDetail['offer_expiry_date'])) {
            $envelope->expiry_date_time = $pdfDocDetail['offer_expiry_date'].' 00:00:00';
            $envelope->save();
        }

        $pdfPath = $pdfDocDetail['pdf_path'] ?? null;
        $pagesAsImage = [];

        // // Handle system-uploaded files
        // if ($detail['upload_by_user'] == 0 && !empty($pdfPath)) {
        //     try {
        //         if (!empty($detail['pdf_file_other_parameter'])) {
        //             $context = getStreamContext();
        //             $pdfContent = file_get_contents($pdfPath, false, $context);

        //             $pdfPath = 'unsigned_pdfs/' . Str::ulid() . '.pdf';
        //             Storage::disk($this->disk)->put($pdfPath, $pdfContent);

        //             $absolutePath = Storage::disk($this->disk)->path('') . $pdfPath;
        //             $pagesAsImage = $this->pdfToImage($absolutePath);
        //         }
        //     } catch (\Throwable $th) {
        //         return [
        //             'status' => false,
        //             'message' => 'Step 3: Failed to fetch/process PDF - ' . $th->getMessage()
        //         ];
        //     }
        // }

        // CREATE ENVELOPE DOCUMENT
        $document = EnvelopeDocument::create([
            'envelope_id' => $envelope->id,
            'initial_pdf_path' => $pdfPath,
            'is_pdf' => $pdfDocDetail['is_pdf'],
            'pdf_file_other_parameter' => $pdfDocDetail['pdf_file_other_parameter'],
            'is_sign_required_for_hire' => $pdfDocDetail['is_sign_required_for_hire'],
            'template_name' => $pdfDocDetail['template_name'],
            'template_category_id' => $pdfDocDetail['category_id'],
            'template_category_name' => $pdfDocDetail['category'],
            'template_category_type' => $pdfDocDetail['category_type'] ?? null,
            'is_post_hiring_document' => $pdfDocDetail['is_post_hiring_document'],
            'pdf_pages_as_image' => $pagesAsImage,
            'upload_by_user' => $pdfDocDetail['upload_by_user'],
        ]);

        // CREATE SIGNATURE
        foreach ($pdfDocDetail['signer_array'] as $signer) {
            DocumentSigner::create([
                'envelope_document_id' => $document->id,
                'signer_email' => $signer['email'],
                'signer_name' => $signer['user_name'],
                'signer_role' => $signer['role'],
                'signer_plain_password' => $envelope->plain_password,
            ]);
        }

        $envelopeDocuments = [
            'pdf_path' => $document->initial_pdf_path,
            'template_name' => $document->template_name,
            'message' => 'Document added.',
            'status' => true,
            'signature_request_document_id' => $document->id,
            'is_post_hiring_document' => $document->is_post_hiring_document,
            'signers' => $document->makeHidden('signer_plain_password')->document_signers,
        ];

        return [
            'status' => true,
            'signature_request_id' => $envelope->id,
            'envelope_id' => $envelope->id,
            'message' => 'Envelope and document(s) added.',
            'document' => $envelopeDocuments,
        ];
    }
}

if (! function_exists('validatePdfDocDetails')) {
    function validatePdfDocDetails(array $data)
    {
        $validator = Validator::make($data, [
            'pdf_path' => 'nullable|url|required_if:upload_by_user,0',
            'is_pdf' => 'required|boolean',
            'is_sign_required_for_hire' => 'required|boolean',
            'template_name' => 'string',
            'offer_expiry_date' => 'nullable|date_format:Y-m-d',
            'is_post_hiring_document' => 'required|boolean',
            'signer_array' => 'required|array',
            'signer_array.*.email' => 'required|email',
            'signer_array.*.user_name' => 'required|string',
            'signer_array.*.role' => 'required|string',
            'upload_by_user' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $validator->errors()->all();
        }

        return [];
    }
}

if (! function_exists('addBlankDocumentInToEnvelope')) {
    function addBlankDocumentInToEnvelope(int $envelopeId, array $signerData)
    {
        $envelope = Envelope::find($envelopeId);
        if (! $envelope) {
            return [
                'status' => false,
                'message' => 'Step 1: Invalid Envelope ID. Failed to process request.',
                'errors' => 'Invalid Envelope ID. Failed to process request.',
            ];
        }

        $document = EnvelopeDocument::create([
            'envelope_id' => $envelopeId,
            'upload_by_user' => 1,
            'is_mandatory' => $signerData['is_mandatory'],
        ]);

        $docSigner = DocumentSigner::create([
            'envelope_document_id' => $document->id,
            'signer_email' => $signerData['email'],
            'signer_plain_password' => $envelope->plain_password,
        ]);

        return [
            'status' => true,
            'envelope' => $envelope,
            'document' => $document,
            'docSigner' => $docSigner,
        ];
    }
}

if (! function_exists('getPythonExecutable')) {
    /**
     * Get the Python executable path.
     * Uses virtual environment if available, falls back to system python3.
     *
     * @return string Path to Python executable
     */
    function getPythonExecutable(): string
    {
        // Virtual environment Python path (production)
        $venvPython = base_path('py-scripts/.venv/bin/python');

        // Use venv if it exists and is executable, otherwise fall back to system python3
        if (is_executable($venvPython)) {
            return $venvPython;
        }

        // Fallback to system python3 (local development)
        return 'python3';
    }
}

if (! function_exists('callPyScript')) {
    function callPyScript(string $pyScript, array $arguments = [])
    {
        $pythonScriptPath = base_path('py-scripts/'.$pyScript);

        if (! file_exists($pythonScriptPath)) {
            throw new Exception('File not exist. '.$pythonScriptPath);
        }

        $command = [getPythonExecutable(), $pythonScriptPath];
        $command = array_merge($command, $arguments);

        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            // Try to parse structured error response from stderr
            $errorOutput = $process->getErrorOutput();

            // Check if the error output contains JSON
            $lines = explode("\n", trim($errorOutput));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // Try to decode as JSON
                $decoded = json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['status']) && $decoded['status'] === 'error') {
                    // Return structured error instead of throwing exception
                    return [
                        'success' => false,
                        'error_type' => $decoded['error_type'] ?? 'unknown',
                        'message' => $decoded['message'] ?? 'Unknown error occurred',
                        'details' => $decoded['details'] ?? null,
                    ];
                }
            }

            // If no structured error found, return generic error response
            return [
                'success' => false,
                'error_type' => 'process_failure',
                'message' => 'Script execution failed: '.$process->getErrorOutput(),
                'details' => [
                    'exit_code' => $process->getExitCode(),
                    'command' => $process->getCommandLine(),
                    'stderr' => $process->getErrorOutput(),
                    'stdout' => $process->getOutput(),
                ],
            ];
        }

        // Check if output contains success JSON response
        $errorOutput = $process->getErrorOutput();
        $lines = explode("\n", trim($errorOutput));
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['status']) && $decoded['status'] === 'success') {
                return [
                    'success' => true,
                    'message' => $decoded['message'] ?? 'Operation completed successfully',
                    'output_path' => $decoded['output_path'] ?? null,
                ];
            }
        }

        // If no structured response found, return generic success
        return [
            'success' => true,
            'message' => 'Script executed successfully',
            'output' => $process->getOutput(),
        ];
    }
}

if (! function_exists('checkIfS3FileExists')) {
    function checkIfS3FileExists($url, $bucketType = 'private')
    {
        // Validate bucket type
        if (! in_array($bucketType, ['private', 'public'])) {
            return ['status' => false, 'message' => 'Invalid bucket type'];
        }

        // Use centralized S3 client factory with IAM role fallback
        $s3Client = createS3Client($bucketType);
        $s3 = $s3Client['s3'];
        $bucket = $s3Client['bucket'];

        $parsedPath = ltrim(parse_url($url, PHP_URL_PATH), '/');

        try {
            $s3->headObject([
                'Bucket' => $bucket,
                'Key' => $parsedPath,
            ]);

            return ['status' => true, 'message' => 'File exists'];
        } catch (\Aws\Exception\AwsException $e) {
            if ($e->getStatusCode() === 404) {
                return ['status' => false, 'message' => 'File does not exist'];
            }

            return ['status' => false, 'message' => 'Failed to check file existence'];
        }
    }
}
