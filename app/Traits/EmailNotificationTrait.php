<?php

namespace App\Traits;

use App\Models\DomainSetting;
use App\Models\EmailConfiguration;
use App\Models\EmailNotificationSetting;
use App\Models\OtherImportantLog;
use App\Models\Settings;
use Aws\Exception\AwsException;
use Aws\Ses\SesClient;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Log;

trait EmailNotificationTrait
{
    use PushNotificationTrait;

    // default = true for send mail without checking email settings
    public function sendEmailNotification($data, $default = false)
    {
        // Validate required fields
        if (! isset($data['email']) || ! isset($data['subject']) || ! isset($data['template'])) {
            $errorDetails = [
                'message' => 'Missing required email parameters',
                'data' => $data,
            ];
            $log = new OtherImportantLog;
            $log->ApiName = 'sendEmailNotification_validation';
            $log->response_data = json_encode($errorDetails);
            $log->save();

            return 'Error: Missing required email parameters';
        }

        // Extract and prepare email data
        // createLogFile("mail-log", "start");
        $to_email = $data['email'];
        $cc_email = isset($data['cc']) ? $data['cc'] : '';
        $cc_emails_arr = isset($data['cc_emails_arr']) ? $data['cc_emails_arr'] : [];
        $bcc_email = isset($data['bcc']) ? $data['bcc'] : '';

        // Validate email format
        if (! filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
            $errorDetails = [
                'message' => 'Invalid email format',
                'email' => $to_email,
            ];
            $log = new OtherImportantLog;
            $log->ApiName = 'sendEmailNotification_validation';
            $log->response_data = json_encode($errorDetails);
            $log->save();

            return 'Error: Invalid email format';
        }

        // Extract domain part of email for domain validation
        $emailId = explode('@', $to_email);
        $subject = $data['subject'];
        $template = $data['template'];
        $is_email_testing = config('mail.testing', false);

        // Get email configuration from database
        $mailConfig = EmailConfiguration::where('status', 1)->first();

        // Get the hostname based on configuration
        $smtpHost = $mailConfig ? $mailConfig->host_name : ($is_email_testing == 1
            ? config('mail.mailers.smtp.host', 'smtp.sendgrid.net')
            : config('mail.mailers.smtp.host', 'email-smtp.us-west-1.amazonaws.com'));

        $smtpPort = $mailConfig ? $mailConfig->smtp_port : ($is_email_testing == 1 ? config('mail.mailers.smtp.port', '587') : config('mail.mailers.smtp.port', '587'));
        $smtpUsername = $mailConfig ? $mailConfig->user_name : ($is_email_testing == 1 ? config('mail.mailers.smtp.username', 'apikey') : config('mail.mailers.smtp.username'));

        \Log::info('Attempting to send email', [
            'to' => $to_email,
            'subject' => $subject,
            'email_testing' => $is_email_testing,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_username' => $smtpUsername,
            'config_source' => $mailConfig ? 'database' : 'environment',
            'sent_at' => now()->toDateTimeString(),
        ]);

        if ($is_email_testing == 1) {
            try {
                // When email_testing is 1, always use SendGrid development credentials
                \Log::info('Email log 01.1: Using SendGrid development credentials', [
                    'to_email' => $to_email,
                    'subject' => $subject,
                ]);

                // Configure mail settings using SendGrid development credentials
                config([
                    'mail.mailers.smtp.host' => config('mail.mailers.smtp.host', 'smtp.sendgrid.net'),
                    'mail.mailers.smtp.port' => config('mail.mailers.smtp.port', '587'),
                    'mail.mailers.smtp.encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                    'mail.mailers.smtp.username' => custom_decrypt(config('mail.mailers.smtp.username', 'apikey')),
                    'mail.mailers.smtp.password' => custom_decrypt(config('mail.mailers.smtp.password')),
                    'mail.from.address' => config('mail.from.address', 'no-return@sequifi.com'),
                    'mail.from.name' => config('mail.from.name', 'Sequifi Test'),
                ]);

                // Force the mailer to use SMTP
                config(['mail.default' => 'smtp']);

                $from_email = config('mail.from.address', 'no-return@sequifi.com');
                $from_name = config('mail.from.name', 'Sequifi Test');

                $mailResponse = Mail::html("$template", function (Message $message) use ($to_email, $subject, $cc_email, $bcc_email, $cc_emails_arr, $from_email, $from_name) {
                    $message->to($to_email);
                    if ($cc_email) {
                        $message->cc($cc_email);
                    }
                    if ($bcc_email) {
                        $message->bcc($bcc_email);
                    }
                    if ($cc_emails_arr) {
                        foreach ($cc_emails_arr as $cc_email) {
                            $message->cc($cc_email);
                        }
                    }
                    $message->from($from_email, $from_name);
                    $message->subject($subject);
                });

                // Log successful email sending
                \Log::info('Email sent successfully with SendGrid development credentials', [
                    'to' => $to_email,
                    'subject' => $subject,
                    'smtp_host' => config('mail.mailers.smtp.host', 'smtp.sendgrid.net'),
                    'smtp_port' => config('mail.mailers.smtp.port', '587'),
                    'sent_at' => now()->toDateTimeString(),
                ]);

                // createLogFile("mail-log", "end");
                return $mailResponse;
            } catch (\Exception $e) {
                // Check if it's an SMTP authentication error
                $errorMessage = $e->getMessage();
                $isAuthError = strpos($errorMessage, 'authenticate') !== false ||
                              strpos($errorMessage, 'authentication') !== false ||
                              strpos($errorMessage, 'AUTH') !== false;

                // Simplify error message for auth errors
                if ($isAuthError) {
                    $errorMessage = 'Failed to authenticate on SMTP server.';
                }

                // Log email sending failure
                \Log::error('Email sending failed', [
                    'to' => $to_email,
                    'subject' => $subject,
                    'smtp_host' => config('mail.mailers.smtp.host', 'smtp.sendgrid.net'),
                    'smtp_port' => config('mail.mailers.smtp.port', '587'),
                    'config_source' => 'environment',
                    'error' => $errorMessage,
                    'error_original' => $e->getMessage(),
                    'failed_at' => now()->toDateTimeString(),
                ]);

                return $errorMessage;
            }
        } else {
            \Log::info('Email log 01: Checking email settings', [
                'to_email' => $to_email,
                'domain' => isset($emailId[1]) ? $emailId[1] : 'invalid_email',
                'default' => $default,
            ]);

            $emailSetting = EmailNotificationSetting::where('company_id', '1')->where('status', '1')->first();
            if (($emailSetting != '' && $emailSetting != null) || $default === true) {
                $domain = DomainSetting::where('domain_name', $emailId[1])->where('status', 1)->count();
                // sending mail if allow to all mail domain are domain setting is active
                if (($emailSetting && $emailSetting->email_setting_type == 1) || $domain > 0 || $default === true) {
                    // First try to send email using AWS SES with IAM role
                    $useIamRole = config('services.ses.use_iam_role', false);
                    $awsRegion = config('services.ses.region', 'us-east-1');

                    if ($useIamRole) {
                        try {
                            \Log::info('Email log 02: Attempting to send email using AWS SES with IAM role', [
                                'to_email' => $to_email,
                                'subject' => $subject,
                                'aws_region' => $awsRegion,
                            ]);

                            // Set up SES client with IAM role credentials
                            $sesOptions = [
                                'version' => 'latest',
                                'region' => $awsRegion,
                            ];

                            $ses = new SesClient($sesOptions);

                            // Prepare from email and name
                            $mailSetting = EmailConfiguration::where('status', 1)->first();
                            $fromEmail = $mailSetting ? $mailSetting->email_from_address : config('mail.from.address');
                            $fromName = $mailSetting ? $mailSetting->email_from_name : config('mail.from.name', 'Sequifi');

                            // Prepare recipients
                            $toAddresses = [$to_email];
                            $ccAddresses = [];
                            $bccAddresses = [];

                            if ($cc_email) {
                                $ccAddresses[] = $cc_email;
                            }

                            if (! empty($cc_emails_arr)) {
                                $ccAddresses = array_merge($ccAddresses, $cc_emails_arr);
                            }

                            if ($bcc_email) {
                                $bccAddresses[] = $bcc_email;
                            }

                            $emailParams = [
                                'Source' => "$fromName <$fromEmail>",
                                'Destination' => [
                                    'ToAddresses' => $toAddresses,
                                ],
                                'Message' => [
                                    'Subject' => [
                                        'Data' => $subject,
                                        'Charset' => 'UTF-8',
                                    ],
                                    'Body' => [
                                        'Html' => [
                                            'Data' => $template,
                                            'Charset' => 'UTF-8',
                                        ],
                                    ],
                                ],
                            ];

                            // Add CC recipients if any
                            if (! empty($ccAddresses)) {
                                $emailParams['Destination']['CcAddresses'] = $ccAddresses;
                            }

                            // Add BCC recipients if any
                            if (! empty($bccAddresses)) {
                                $emailParams['Destination']['BccAddresses'] = $bccAddresses;
                            }

                            // Send the email
                            $result = $ses->sendEmail($emailParams);

                            \Log::info('Email log 03: AWS SES email sent successfully', [
                                'to_email' => $to_email,
                                'message_id' => $result['MessageId'] ?? 'unknown',
                                'sent_at' => now()->toDateTimeString(),
                            ]);

                            // EMAIL SENT SUCCESSFULLY - RETURN IMMEDIATELY TO PREVENT FALLBACK ATTEMPTS
                            return $result['MessageId'] ?? true;
                        } catch (AwsException $e) {
                            \Log::error('Email log 04: AWS SES email failed, switching to test environment credentials', [
                                'to_email' => $to_email,
                                'error' => $e->getMessage(),
                                'aws_error_code' => $e->getAwsErrorCode(),
                                'aws_error_type' => $e->getAwsErrorType(),
                                'failed_at' => now()->toDateTimeString(),
                            ]);

                            // Switch to test environment credentials
                            try {
                                \Log::info('Email log 04.1: Using test environment credentials after SES failure', [
                                    'to_email' => $to_email,
                                    'subject' => $subject,
                                ]);

                                // Use test environment SMTP (SendGrid) credentials
                                config([
                                    'mail.mailers.smtp.host' => config('mail.mailers.smtp.host', 'smtp.sendgrid.net'),
                                    'mail.mailers.smtp.port' => config('mail.mailers.smtp.port', '587'),
                                    'mail.mailers.smtp.encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                                    'mail.mailers.smtp.username' => custom_decrypt(config('mail.mailers.smtp.username', 'apikey')),
                                    'mail.mailers.smtp.password' => custom_decrypt(config('mail.mailers.smtp.password')),
                                    'mail.from.address' => config('mail.from.address', 'no-return@sequifi.com'),
                                    'mail.from.name' => config('mail.from.name', 'Sequifi Test'),
                                ]);

                                // Force the mailer to use SMTP
                                config(['mail.default' => 'smtp']);

                                $from_email = config('mail.from.address', 'no-return@sequifi.com');
                                $from_name = config('mail.from.name', 'Sequifi Test');

                                $mailResponse = Mail::html("$template", function (Message $message) use ($from_name, $from_email, $subject, $to_email, $cc_email, $bcc_email, $cc_emails_arr) {
                                    $message->to($to_email, $subject)->subject($subject);
                                    if ($cc_email) {
                                        $message->cc($cc_email);
                                    }
                                    if ($bcc_email) {
                                        $message->bcc($bcc_email);
                                    }
                                    if ($cc_emails_arr) {
                                        foreach ($cc_emails_arr as $cc_email) {
                                            $message->cc($cc_email);
                                        }
                                    }
                                    $message->from($from_email, $from_name);
                                });

                                \Log::info('Email log 04.2: Email sent successfully with test environment credentials', [
                                    'to_email' => $to_email,
                                    'sent_at' => now()->toDateTimeString(),
                                ]);

                                // EMAIL SENT SUCCESSFULLY - RETURN IMMEDIATELY TO PREVENT FURTHER FALLBACK ATTEMPTS
                                return $mailResponse;
                            } catch (\Exception $testEnvException) {
                                \Log::error('Email log 04.3: Test environment email failed, continuing to standard fallback', [
                                    'to_email' => $to_email,
                                    'error' => $testEnvException->getMessage(),
                                    'failed_at' => now()->toDateTimeString(),
                                ]);

                                // Continue to the standard SMTP fallback
                            }
                        } catch (\Exception $e) {
                            \Log::error('Email log 05: AWS SES unexpected error, falling back to SMTP', [
                                'to_email' => $to_email,
                                'error' => $e->getMessage(),
                                'failed_at' => now()->toDateTimeString(),
                            ]);

                            // Fall back to SMTP if SES fails
                            // Continue to the SMTP code below
                        }
                    }

                    // Fallback to SMTP (either because SES failed or IAM role is not being used)
                    try {
                        $mailSetting = EmailConfiguration::where('status', 1)->first();
                        if ($mailSetting != '' && $mailSetting != null) {
                            \Log::info('Email log 06: Using SMTP fallback with custom email configuration', [
                                'mail_setting_id' => $mailSetting->id ?? 'unknown',
                                'to_email' => $to_email,
                                'subject' => $subject,
                            ]);

                            // Configure mail settings using database configuration
                            config([
                                'mail.mailers.smtp.host' => $mailSetting->host_name,
                                'mail.mailers.smtp.port' => $mailSetting->smtp_port,
                                'mail.mailers.smtp.username' => $mailSetting->user_name,
                                'mail.mailers.smtp.password' => custom_decrypt($mailSetting->password),
                                'mail.from.address' => $mailSetting->email_from_address,
                                'mail.from.name' => $mailSetting->email_from_name,
                            ]);

                            $from_email = $mailSetting->email_from_address;
                            $from_name = $mailSetting->email_from_name;
                        } else {
                            \Log::info('Email log 07: Using SMTP fallback with environment configuration', [
                                'to_email' => $to_email,
                                'subject' => $subject,
                            ]);

                            // Use config values
                            config([
                                'mail.mailers.smtp.host' => config('mail.mailers.smtp.host', 'email-smtp.us-west-1.amazonaws.com'),
                                'mail.mailers.smtp.port' => config('mail.mailers.smtp.port', '587'),
                                'mail.mailers.smtp.encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                                'mail.mailers.smtp.username' => config('mail.mailers.smtp.username'),
                                'mail.mailers.smtp.password' => config('mail.mailers.smtp.password'),
                            ]);

                            $from_email = config('mail.from.address');
                            $from_name = config('mail.from.name', 'Sequifi');
                        }

                        // Force the mailer to use SMTP
                        config(['mail.default' => 'smtp']);

                        // FINAL SMTP FALLBACK ATTEMPT
                        $mailResponse = Mail::html("$template", function (Message $message) use ($from_name, $from_email, $subject, $to_email, $cc_email, $bcc_email, $cc_emails_arr) {
                            $message->to($to_email, $subject)->subject($subject);
                            if ($cc_email) {
                                $message->cc($cc_email);
                            }
                            if ($bcc_email) {
                                $message->bcc($bcc_email);
                            }
                            if ($cc_emails_arr) {
                                foreach ($cc_emails_arr as $cc_email) {
                                    $message->cc($cc_email);
                                }
                            }
                            $message->from($from_email, $from_name);
                        });

                        \Log::info('Email log 09: SMTP fallback email sent successfully', [
                            'to_email' => $to_email,
                            'sent_at' => now()->toDateTimeString(),
                        ]);

                        return $mailResponse;
                    } catch (\Exception $e) {
                        \Log::error('Email log 08: SMTP fallback also failed', [
                            'to_email' => $to_email,
                            'error' => $e->getMessage(),
                            'failed_at' => now()->toDateTimeString(),
                        ]);

                        return $e->getMessage();
                    }
                }
            }

            return false;
        }
    }

    // Rest of your code remains the same
}
