<?php

namespace App\Jobs;

use App\Models\AutomationActionLog;
use App\Traits\EmailNotificationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutomationMail implements ShouldQueue
{
    use Dispatchable, EmailNotificationTrait, InteractsWithQueue, Queueable, SerializesModels;

    public $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Enhanced logging for automation email processing
            Log::info('AutomationMail: Processing automation email', [
                'job_id' => $this->job ? $this->job->getJobId() : 'unknown',
                'to_email' => $this->data['send_to'] ?? 'unknown',
                'has_lead_name' => ! empty($this->data['lead_name']),
                'has_onboarding_name' => ! empty($this->data['onboarding_name']),
                'has_cc' => isset($this->data['cc']) && ! empty($this->data['cc']),
                'is_new_contract' => $this->data['is_new_contract'] ?? 0,
            ]);

            // Validate required data
            if (empty($this->data['send_to'])) {
                throw new \InvalidArgumentException('Missing send_to email address');
            }

            if (empty($this->data['mailMessage'])) {
                throw new \InvalidArgumentException('Missing mailMessage content');
            }

            // Determine subject based on automation type
            $isNewContract = isset($this->data['is_new_contract']) && $this->data['is_new_contract'] == 1;

            if (! empty($this->data['lead_name'])) {
                $subject = 'Lead Progress Update '.$this->data['lead_name'];
            } elseif (! empty($this->data['onboarding_name'])) {
                // Add new contract subject only
                if ($isNewContract) {
                    $subject = 'Contract Renewal Update '.$this->data['onboarding_name'];
                } else {
                    // Keep existing subject unchanged
                    $subject = 'Onboarding Progress Update '.$this->data['onboarding_name'];
                }
            } else {
                $subject = 'Automation Notification';
            }

            $to_email = $this->data['send_to'];

            $template = $this->getTemplate([
                'mailMessage' => $this->data['mailMessage'],
                'onboarding_name' => $this->data['onboarding_name'] ?? '',
                'is_new_contract' => $isNewContract,
            ]);

            $emailData = [
                'email' => $to_email,
                'subject' => $subject,
                'template' => $template,
            ];

            if (isset($this->data['cc']) && ! empty($this->data['cc'])) {
                $emailData['cc_emails_arr'] = $this->data['cc'];
            }

            // Send email and capture result
            $result = $this->sendEmailNotification($emailData);

            // Update automation log with email status
            $this->updateAutomationLog(true, $to_email);

            Log::info('AutomationMail: Email sent successfully', [
                'to_email' => $to_email,
                'subject' => $subject,
                'result' => $result,
            ]);

        } catch (\Throwable $e) {
            // Update automation log with failure status
            $this->updateAutomationLog(false, $this->data['send_to'] ?? 'unknown');

            Log::error('AutomationMail: Failed to send automation email', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $this->data,
            ]);

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Update automation action log with email sending status
     */
    private function updateAutomationLog(bool $success, string $email): void
    {
        try {
            // Check if we have automation_log_id in the data (preferred method)
            if (! empty($this->data['automation_log_id'])) {
                $automationLog = AutomationActionLog::find($this->data['automation_log_id']);

                if ($automationLog) {
                    $automationLog->email_sent = $success;
                    if ($success) {
                        $automationLog->status = 1; // Mark as successfully processed
                    }
                    $automationLog->save();

                    Log::info('AutomationMail: Updated automation log with email status', [
                        'log_id' => $this->data['automation_log_id'],
                        'email_sent' => $success,
                        'status' => $automationLog->status,
                        'to_email' => $email,
                    ]);

                    return;
                } else {
                    Log::warning('AutomationMail: Automation log not found', [
                        'log_id' => $this->data['automation_log_id'],
                    ]);
                }
            }

            // Fallback method: Try to find recent log entry based on available data
            if (! empty($this->data['lead_name']) && ! empty($this->data['lead_id'])) {
                $automationLog = AutomationActionLog::where('lead_id', $this->data['lead_id'])
                    ->where('email_sent', false)
                    ->latest()
                    ->first();

                if ($automationLog) {
                    $automationLog->email_sent = $success;
                    if ($success) {
                        $automationLog->status = 1;
                    }
                    $automationLog->save();

                    Log::info('AutomationMail: Updated lead automation log (fallback method)', [
                        'log_id' => $automationLog->id,
                        'lead_id' => $this->data['lead_id'],
                        'email_sent' => $success,
                    ]);

                    return;
                }
            } elseif (! empty($this->data['onboarding_name']) && ! empty($this->data['onboarding_id'])) {
                $automationLog = AutomationActionLog::where('onboarding_id', $this->data['onboarding_id'])
                    ->where('email_sent', false)
                    ->latest()
                    ->first();

                if ($automationLog) {
                    $automationLog->email_sent = $success;
                    if ($success) {
                        $automationLog->status = 1;
                    }
                    $automationLog->save();

                    Log::info('AutomationMail: Updated onboarding automation log (fallback method)', [
                        'log_id' => $automationLog->id,
                        'onboarding_id' => $this->data['onboarding_id'],
                        'email_sent' => $success,
                    ]);

                    return;
                }
            }

            Log::warning('AutomationMail: Unable to find automation log to update', [
                'available_data' => array_keys($this->data),
                'email' => $email,
                'success' => $success,
            ]);

        } catch (\Throwable $e) {
            Log::error('AutomationMail: Failed to update automation log', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $email,
                'success' => $success,
            ]);
        }
    }

    public function getTemplate($data)
    {
        // Get company profile data dynamically (following SequiDocsHelper pattern)
        $companyProfile = DB::table('company_profiles')->first();

        // Use the same pattern as companyDataResolveKeyNew() function
        $companyName = $companyProfile->name ?? null;
        $companyBusinessName = $companyProfile->business_name ?? null;
        $companyEmail = $companyProfile->company_email ?? null;
        $companyPhone = $companyProfile->business_phone ?? null;
        $companyAddress = $companyProfile->business_address ?? null;
        $companyWebsite = $companyProfile->company_website ? 'https://'.$companyProfile->company_website : null;

        // Follow the same logo pattern as companyAndOtherStaticImagesNew()
        $companyLogo = $companyProfile->logo
            ? config('app.aws_s3bucket_url').'/'.config('app.domain_name').'/'.$companyProfile->logo
            : config('app.aws_s3bucket_url').'/public_images/defaultCompanyImage.png';

        // Enhanced email content to include candidate name context
        $candidateName = '';
        $emailContent = $data['mailMessage'];

        // Add context-aware content prefix
        $isNewContract = isset($data['is_new_contract']) && $data['is_new_contract'] === true;

        // Add candidate name context if available
        if (! empty($data['onboarding_name'])) {
            $candidateName = $data['onboarding_name'];

            // Add only new contract prefix - keep old logic unchanged
            if ($isNewContract) {
                $emailContent = "<p style='font-weight: bold; color: #28a745; margin-bottom: 15px;'>🔄 Contract Renewal Update for: {$candidateName}</p>".$emailContent;
            } else {
                // Keep existing logic exactly the same
                $emailContent = "<p style='font-weight: bold; color: #333; margin-bottom: 15px;'>Update for: {$candidateName}</p>".$emailContent;
            }
        } elseif (! empty($data['lead_name'])) {
            $candidateName = $data['lead_name'];
            $emailContent = "<p style='font-weight: bold; color: #333; margin-bottom: 15px;'>Update for: {$candidateName}</p>".$emailContent;
        }

        // Handle null company data gracefully
        $footerCompanyInfo = [];
        if ($companyBusinessName) {
            $footerCompanyInfo[] = $companyBusinessName;
        }
        if ($companyPhone) {
            $footerCompanyInfo[] = $companyPhone;
        }
        if ($companyEmail) {
            $footerCompanyInfo[] = $companyEmail;
        }
        if ($companyAddress) {
            $footerCompanyInfo[] = $companyAddress;
        }
        $footerText = ! empty($footerCompanyInfo) ? implode(' | ', $footerCompanyInfo) : 'Company Information';

        // Handle signature safely
        $signatureText = $companyBusinessName ?: 'Team';

        // Handle website link safely
        $websiteLinkHtml = $companyWebsite
            ? "<a href=\"{$companyWebsite}\" target=\"_blank\" style=\"font-weight: 500;font-size: 16px;line-height: 20px;color: #757575;text-align: center;\">{$companyWebsite}</a>"
            : ($companyBusinessName ?: 'Company Website');

        // Prepare candidate name header HTML
        $candidateHeaderHtml = $candidateName
            ? "<h1 style=\"color: white; font-size: 28px; font-weight: 700; margin: 15px 0; text-shadow: 0 2px 4px rgba(0,0,0,0.3);\">{$candidateName}</h1>"
            : '';

        $template = <<<HTML
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <meta http-equiv="X-UA-Compatible" content="IE=edge" />
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
                <title>Automation Update</title>
                <style type="text/css">
                    body {
                        margin: 0;
                        padding: 0;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                        background-color: #f8f9fa;
                        color: #333;
                    }
                    .email-container {
                        max-width: 600px;
                        margin: 0 auto;
                        background: #ffffff;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                        border-radius: 8px;
                        overflow: hidden;
                    }
                    .header {
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        padding: 30px;
                        text-align: center;
                        color: white;
                    }
                    .logo {
                        max-height: 80px;
                        width: auto;
                        margin-bottom: 15px;
                        border-radius: 8px;
                    }
                    .status-badge {
                        background: #28a745;
                        color: white;
                        padding: 8px 16px;
                        border-radius: 20px;
                        font-size: 14px;
                        font-weight: 600;
                        display: inline-block;
                        margin: 10px 0;
                    }
                    .candidate-name {
                        font-size: 24px;
                        font-weight: 700;
                        color: white;
                        margin: 15px 0 10px 0;
                    }
                    .content {
                        padding: 40px;
                    }
                    .update-box {
                        background: #f8f9fa;
                        border-left: 4px solid #667eea;
                        padding: 20px;
                        margin: 20px 0;
                        border-radius: 0 8px 8px 0;
                    }
                    .status-transition {
                        background: #e3f2fd;
                        border: 1px solid #bbdefb;
                        padding: 15px;
                        border-radius: 8px;
                        margin: 15px 0;
                    }
                    .footer {
                        background: #f8f9fa;
                        padding: 30px;
                        text-align: center;
                        border-top: 1px solid #eee;
                    }
                    .company-info {
                        color: #666;
                        font-size: 13px;
                        line-height: 1.6;
                    }
                    .powered-by {
                        margin-top: 20px;
                        padding-top: 20px;
                        border-top: 1px solid #ddd;
                        font-size: 12px;
                        color: #999;
                    }
                    @media only screen and (max-width: 600px) {
                        .email-container { width: 100% !important; margin: 0 !important; }
                        .content { padding: 20px !important; }
                        .header { padding: 20px !important; }
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
                                                                                                        <table cellpadding="0" cellspacing="0" width="700" class="wrapper" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; margin-top: 3%; box-shadow: 0 8px 32px rgba(0,0,0,0.12);">
                                                <tr>
                                                    <td style="padding: 30px; text-align: center;">
                                                        <!-- Enhanced Header Section -->
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                            <tr>
                                                                <td style="text-align: center; padding-bottom: 20px;">
                                                                    <img style="max-height: 80px; width: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(255,255,255,0.2);" src="{$companyLogo}" alt="{$companyBusinessName} Logo">
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td style="text-align: center;">
                                                                    <div style="background: rgba(255,255,255,0.2); padding: 12px 24px; border-radius: 25px; display: inline-block; margin: 10px 0;">
                                                                        <span style="color: white; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">📧 Status Update</span>
                                                                    </div>
                                                                    {$candidateHeaderHtml}
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                                <!-- Content Section with Modern Card Design -->
                                                <tr>
                                                    <td style="background: white; padding: 0;">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                                            <tr>
                                                                <td style="padding: 40px;">
                                                                    <!-- Status Update Content Card -->
                                                                    <div style="background: #f8f9fa; border-radius: 12px; padding: 30px; border-left: 4px solid #667eea; margin: 20px 0;">
                                                                        <div style="font-size: 18px; font-weight: 600; color: #333; margin-bottom: 15px; display: flex; align-items: center;">
                                                                            <span style="background: #667eea; color: white; border-radius: 50%; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; margin-right: 12px; font-size: 16px;">🔄</span>
                                                                            Automation Update
                                                                        </div>
                                                                        <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef; font-size: 16px; line-height: 1.6; color: #495057;">
                                                                            {$emailContent}
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <!-- Enhanced Signature Section -->
                                                            <tr>
                                                                <td style="background: white; padding-bottom: 40px;">
                                                                    <div style="text-align: center; border-top: 2px solid #f0f0f0; padding-top: 25px;">
                                                                        <p style="font-size: 16px; color: #666; margin: 0 0 8px 0;">Best regards,</p>
                                                                        <p style="font-size: 20px; font-weight: 600; color: #333; margin: 0; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                                                            <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); background-clip: text; -webkit-background-clip: text; color: transparent;">{$signatureText}</span>
                                                                        </p>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            <!-- Modern Footer -->
                                                            <tr>
                                                                <td style="background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;">
                                                                    <!-- Company Information -->
                                                                    <div style="margin-bottom: 20px;">
                                                                        <p style="font-size: 14px; color: #666; margin: 0 0 10px 0; font-weight: 500;">{$footerText}</p>
                                                                        <p style="font-size: 13px; color: #888; margin: 0;">
                                                                            Copyright © 2025 {$websiteLinkHtml} • All rights reserved
                                                                        </p>
                                                                    </div>
                                                                    
                                                                    <!-- Powered by Sequifi -->
                                                                    <div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px;">
                                                                        <table style="margin: 0 auto;" cellpadding="0" cellspacing="0">
                                                                            <tr>
                                                                                <td style="vertical-align: middle; padding-right: 8px;">
                                                                                    <span style="font-size: 12px; color: #999; font-weight: 500;">Powered by</span>
                                                                                </td>
                                                                                <td style="vertical-align: middle;">
                                                                                    <img src="https://dh9m456rx9q0m.cloudfront.net/public_images/sequifi-logo.png" 
                                                                                         alt="Sequifi" style="height: 20px; width: auto; opacity: 0.7;">
                                                                                </td>
                                                                            </tr>
                                                                        </table>
                                                                    </div>
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
        HTML;

        return $template;

    }
}
